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
            $this->warn("\n==================================================");
            $this->warn("🏪 Магазин: {$store->name} (ID: {$store->id})");
            $this->warn("==================================================");

            if (empty($store->api_key_standard)) {
                $this->error("⚠️ Нет API ключа (Standard). Пропускаем.");
                continue;
            }

            try {
                $currentDate = clone $dateFrom;
                
                while ($currentDate <= $dateTo) {
                    $dateStr = $currentDate->format('Y-m-d');
                    
                    $this->info("\n📅 [{$dateStr}] Начинаем обработку дня");

                    $page = 1; // В API v3 используется page вместо offset
                    $retryCount = 0;
                    $isDayDone = false;

                    while (!$isDayDone) {
                        try {
                            $this->line("   👉 [Страница {$page}] Отправка запроса к API WB v3...");
                            
                            // Тело запроса по стандарту Swagger API v3
                            $payload = [
                                'period' => [
                                    'begin' => $dateStr . ' 00:00:00',
                                    'end'   => $dateStr . ' 23:59:59'
                                ],
                                'page' => $page
                            ];

                            $startTime = microtime(true);

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
                                
                                $sleepTime = 60 + ($retryCount * 10);
                                $this->waitTimer($sleepTime, "Остываем после 429 ошибки");
                                continue; 
                            }

                            if (!$response->successful()) {
                                $this->error("   🔴 ОШИБКА API HTTP: " . $response->status() . " " . $response->body());
                                $isDayDone = true;
                                break;
                            }

                            $this->line("   ✅ Ответ получен за {$duration} сек.");
                            $retryCount = 0; 

                            $data = $response->json();
                            // В v3 данные обычно лежат в data.cards или data.products
                            // В зависимости от точной реализации WB, проверяем оба варианта:
                            $products = $data['data']['cards'] ?? $data['data']['products'] ?? [];
                            $count = count($products);

                            $this->line("   📦 В ответе карточек: {$count}");

                            if ($count === 0) {
                                $this->info("   🏁 [{$dateStr}] Данные закончились.");
                                $isDayDone = true;
                                break;
                            }

                            $this->line("   💾 Сохраняем в БД...");
                            $this->saveAnalytics($store, $products, $dateStr);
                            
                            // Проверяем флаг следующей страницы (WB v3 часто отдает isNextPage)
                            $isNextPage = $data['data']['isNextPage'] ?? true;
                            
                            if (!$isNextPage || $count < 100) {
                                $this->info("   🏁 [{$dateStr}] Выгружена последняя часть дня.");
                                $isDayDone = true;
                            } else {
                                $page++; // Переходим к следующей странице
                                $this->waitTimer(21, "Пауза между страницами");
                            }

                        } catch (\Throwable $e) {
                            $this->error("   🚨 КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
                            $this->waitTimer(21, "Пауза после ошибки соединения");
                        }
                    }

                    $currentDate->addDay();
                    
                    if ($currentDate <= $dateTo) {
                        $this->waitTimer(21, "Короткая пауза перед следующей датой");
                    }
                }

            } catch (\Throwable $e) {
                $this->error("💥 Глобальная ошибка при обработке магазина: " . $e->getMessage());
            }
        }
        
        $this->info("\n🏁 СКРИПТ СИНХРОНИЗАЦИИ АНАЛИТИКИ ПОЛНОСТЬЮ ЗАВЕРШЕН.");
    }

    private function saveAnalytics(Store $store, array $products, string $date)
    {
        $now = now();
        $analyticsData = [];

        foreach ($products as $row) {
            // Разбираем структуру (поддерживает как старые ключи, так и новые из v3)
            $productInfo = $row['product'] ?? $row; // На случай если WB отдал плоский массив
            $stat = $row['statistic']['selected'] ?? $row['statistics']['selected'] ?? [];
            $conversions = $stat['conversions'] ?? [];
            $stocks = $productInfo['stocks'] ?? [];

            // Если nmId нет, пропускаем (ключ может быть nmId или nmID)
            $nmId = $productInfo['nmId'] ?? $productInfo['nmID'] ?? null;
            if (empty($nmId)) continue;

            $analyticsData[] = [
                'store_id' => $store->id, 
                'nm_id'    => $nmId, 
                'date'     => $date,
                
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

                'stocks_mp' => $stocks['mp'] ?? 0,
                'stocks_wb' => $stocks['wb'] ?? 0,
                
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($analyticsData)) {
            return;
        }

        $this->line("   -> Массовое сохранение " . count($analyticsData) . " метрик в БД...");

        // Разбиваем на чанки по 500 записей, чтобы не превысить лимит биндингов в PostgreSQL
        foreach (array_chunk($analyticsData, 500) as $chunk) {
            ProductAnalytic::upsert(
                $chunk,
                ['store_id', 'nm_id', 'date'], // Составной уникальный ключ для проверки дублей
                [
                    // Поля, которые будут обновляться, если запись на эту дату уже есть
                    'vendor_code', 'brand_name', 'object_id', 'object_name',
                    'open_card_count', 'add_to_cart_count', 'orders_count', 'buyouts_count', 'cancel_count',
                    'orders_sum_rub', 'buyouts_sum_rub', 'cancel_sum_rub', 'avg_price_rub', 'avg_orders_count_per_day',
                    'conversion_open_to_cart_percent', 'conversion_cart_to_order_percent', 'conversion_buyouts_percent',
                    'stocks_mp', 'stocks_wb', 'updated_at'
                ]
            );
        }
        
        $this->info("   ✨ Успешно закоммичено!");
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