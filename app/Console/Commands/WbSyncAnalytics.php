<?php

namespace App\Console\Commands;

use App\Models\ProductAnalytic;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WbSyncAnalytics extends Command
{
    protected $signature = 'wb:sync-analytics {--days=3 : За сколько дней грузить}';
    protected $description = 'Загрузка полной воронки продаж, финансов и стоков (API v3)';

    public function handle()
    {
        date_default_timezone_set('Europe/Moscow');

        $stores = Store::all();
        $days = (int) $this->option('days');
        
        $dateFrom = Carbon::now()->subDays($days);
        $dateTo = Carbon::now();

        $this->info("🚀 СТАРТ СКРИПТА (API v3). Период: {$dateFrom->format('Y-m-d')} - {$dateTo->format('Y-m-d')}");

        foreach ($stores as $store) {
            $this->line("----------------------------------------------------------------");
            $this->info("🏪 Магазин: {$store->name} (ID: {$store->id})");

            if (empty($store->api_key_standard)) {
                $this->warn("⚠️ Нет API ключа (Standard). Пропускаем.");
                continue;
            }

            try {
                $currentDate = clone $dateFrom;
                
                while ($currentDate <= $dateTo) {
                    $dateStr = $currentDate->format('Y-m-d');
                    
                    // Для pastPeriod берем тот же день год назад (согласно докам WB)
                    // Ограничиваем pastPeriod: WB не принимает даты старше 365 дней от СЕГОДНЯ
                    $pastDate = $currentDate->copy()->subYear();
                    $minDate = Carbon::now()->subDays(364);

                    if ($pastDate->lt($minDate)) {
                        $pastDate = clone $minDate;
                    }
                    $pastDateStr = $pastDate->format('Y-m-d');

                    $this->line("");
                    $this->info("📅 [{$dateStr}] Начинаем обработку дня");

                    $limit = 100; // Рекомендуемый лимит для стабильной выгрузки
                    $offset = 0;
                    $retryCount = 0;
                    $isDayDone = false;

                    while (!$isDayDone) {
                        try {
                            $this->line("   👉 [Смещение {$offset}] Отправка запроса к API WB v3...");
                            
                            $payload = [
                                'selectedPeriod' => ['start' => $dateStr, 'end' => $dateStr],
                                'pastPeriod'     => ['start' => $pastDateStr, 'end' => $pastDateStr],
                                'limit'          => $limit,
                                'offset'         => $offset,
                            ];

                            $startTime = microtime(true);

                            // --- ПРЯМОЙ HTTP ЗАПРОС ---
                            $response = Http::withHeaders([
                                'Authorization' => $store->api_key_standard,
                                'Content-Type'  => 'application/json',
                                'Accept'        => 'application/json',
                            ])->timeout(30)->post('https://seller-analytics-api.wildberries.ru/api/analytics/v3/sales-funnel/products', $payload);
                            
                            $duration = round(microtime(true) - $startTime, 2);

                            // Обработка 429 Too Many Requests (Лимиты)
                            if ($response->status() === 429) {
                                $retryCount++;
                                $this->error("   🔥 ОШИБКА 429 (ЛИМИТ). Попытка восстановления {$retryCount}/10");
                                
                                if ($retryCount > 10) {
                                    $this->error("   ❌ Превышено максимальное кол-во попыток (10). Пропускаем день {$dateStr}.");
                                    $isDayDone = true;
                                    break;
                                }
                                
                                // WB скидывает лимиты примерно каждую минуту. Даем паузу 60 сек + 10 сек за каждую неудачу
                                $sleepTime = 60 + ($retryCount * 10);
                                $this->waitTimer($sleepTime, "Остываем после 429 ошибки");
                                continue; // Повторяем запрос с тем же offset
                            }

                            // Обработка других ошибок (400, 401, 500)
                            if (!$response->successful()) {
                                $this->error("   🔴 ОШИБКА API HTTP: " . $response->status() . " " . $response->body());
                                $isDayDone = true;
                                break;
                            }

                            $this->info("   ✅ Ответ получен за {$duration} сек.");
                            $retryCount = 0; // Успех! Сбрасываем счетчик ошибок

                            $data = $response->json();
                            $products = $data['data']['products'] ?? [];
                            $count = count($products);

                            $this->line("   📦 В ответе карточек: {$count}");

                            // Если вернулось меньше лимита, значит это последняя страница
                            if ($count === 0) {
                                $this->info("   🏁 [{$dateStr}] Данные закончились.");
                                $isDayDone = true;
                                break;
                            }

                            $this->line("   💾 Сохраняем в БД...");
                            $this->saveAnalytics($store, $products, $dateStr);
                            $this->info("   ✨ Сохранено.");
                            
                            // Сдвигаем окно пагинации для следующего запроса
                            $offset += $limit;

                            // Если пришло меньше лимита, следующей страницы точно нет
                            if ($count < $limit) {
                                $this->info("   🏁 [{$dateStr}] Выгружена последняя часть дня.");
                                $isDayDone = true;
                            } else {
                                // Легкая пауза перед следующей страницей (чтобы не спамить)
                                $this->waitTimer(2, "Пауза между страницами");
                            }

                        } catch (\Throwable $e) {
                            $this->error("   🚨 КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
                            // Если упало из-за таймаута соединения, тоже уходим в паузу
                            $this->waitTimer(30, "Пауза после ошибки соединения");
                        }
                    }

                    $currentDate->addDay();
                    
                    // Пауза перед следующим днем
                    if ($currentDate <= $dateTo) {
                        $this->waitTimer(3, "Короткая пауза перед следующей датой");
                    }
                }

            } catch (\Throwable $e) {
                $this->error("💥 Глобальная ошибка при обработке магазина: " . $e->getMessage());
            }
        }
        
        $this->info("🏁 СКРИПТ ПОЛНОСТЬЮ ЗАВЕРШЕН.");
    }

    private function saveAnalytics(Store $store, array $products, string $date)
    {
        DB::transaction(function () use ($store, $products, $date) {
            foreach ($products as $row) {
                // Разбираем новую структуру v3
                $productInfo = $row['product'] ?? [];
                $stat = $row['statistic']['selected'] ?? [];
                $conversions = $stat['conversions'] ?? [];
                $stocks = $productInfo['stocks'] ?? [];

                // Если nmId нет, пропускаем
                if (empty($productInfo['nmId'])) continue;

                ProductAnalytic::updateOrCreate(
                    [
                        'store_id' => $store->id, 
                        'nm_id'    => $productInfo['nmId'], 
                        'date'     => $date
                    ],
                    [
                        'vendor_code' => $productInfo['vendorCode'] ?? null,
                        'brand_name'  => $productInfo['brandName'] ?? null,
                        'object_id'   => $productInfo['subjectId'] ?? null,
                        'object_name' => $productInfo['subjectName'] ?? null,

                        'open_card_count'   => $stat['openCount'] ?? 0,
                        'add_to_cart_count' => $stat['cartCount'] ?? 0,
                        'orders_count'      => $stat['orderCount'] ?? 0,
                        'buyouts_count'     => $stat['buyoutCount'] ?? 0,
                        'cancel_count'      => $stat['cancelCount'] ?? 0,

                        'orders_sum_rub'  => $stat['orderSum'] ?? 0,
                        'buyouts_sum_rub' => $stat['buyoutSum'] ?? 0,
                        'cancel_sum_rub'  => $stat['cancelSum'] ?? 0,
                        'avg_price_rub'   => $stat['avgPrice'] ?? 0,

                        'avg_orders_count_per_day' => $stat['avgOrdersCountPerDay'] ?? 0,

                        'conversion_open_to_cart_percent'  => $conversions['addToCartPercent'] ?? 0,
                        'conversion_cart_to_order_percent' => $conversions['cartToOrderPercent'] ?? 0,
                        'conversion_buyouts_percent'       => $conversions['buyoutPercent'] ?? 0,

                        // Остатки переехали прямо в $productInfo['stocks']
                        'stocks_mp' => $stocks['mp'] ?? 0,
                        'stocks_wb' => $stocks['wb'] ?? 0,
                    ]
                );
            }
        });
    }

    private function waitTimer(int $seconds, string $reason = "Ожидание")
    {
        $this->newLine();
        $this->info("⏳ {$reason} ({$seconds} сек)...");
        $bar = $this->output->createProgressBar($seconds);
        $bar->start();
        for ($i = 0; $i < $seconds; $i++) {
            sleep(1);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }
}