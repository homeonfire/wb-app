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
    protected $description = 'Загрузка воронки продаж (API v3 /sales-funnel/products)';

    public function handle()
    {
        date_default_timezone_set('Europe/Moscow');

        $stores = Store::all();
        $days = (int) $this->option('days');
        
        $dateFrom = Carbon::now()->subDays($days);
        $dateTo = Carbon::now();

        $this->info("🚀 СТАРТ СКРИПТА (API v3 Products). Период: {$dateFrom->format('Y-m-d')} - {$dateTo->format('Y-m-d')}");

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
                
                // Перебираем дни по одному, чтобы в БД ложилась посуточная аналитика
                while ($currentDate <= $dateTo) {
                    $dateStr = $currentDate->format('Y-m-d');
                    $this->info("\n📅 [{$dateStr}] Начинаем выгрузку");

                    $page = 1; // В этом методе API v3 пагинация идет через page
                    $retryCount = 0;
                    $isDayDone = false;

                    while (!$isDayDone) {
                        try {
                            $this->line("   👉 [Страница {$page}] Отправка запроса к WB API v3...");
                            
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

                            // Обработка 429 Лимита (ШТАТНЫЙ РЕЖИМ ДЛЯ WB)
                            if ($response->status() === 429) {
                                $retryCount++;
                                $this->warn("   🔥 Лимит API (1 запрос в минуту). Ждем свою очередь. Попытка {$retryCount}/20");
                                
                                if ($retryCount > 20) {
                                    $this->error("   ❌ Лимит не отпускает слишком долго. Пропускаем день {$dateStr}.");
                                    $isDayDone = true;
                                    break;
                                }
                                
                                // WB требует ровно 1 минуту тишины. Ждем 62 секунды для надежности.
                                $this->waitTimer(62, "Ожидание сброса лимита WB");
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
                            $cards = $data['data']['cards'] ?? [];
                            $count = count($cards);

                            $this->line("   📦 В ответе карточек: {$count}");

                            if ($count === 0) {
                                $this->info("   🏁 [{$dateStr}] Карточек нет.");
                                $isDayDone = true;
                                break;
                            }

                            $this->line("   💾 Сохраняем в БД...");
                            $this->saveAnalytics($store, $cards, $dateStr);
                            
                            $isNextPage = $data['data']['isNextPage'] ?? false;
                            
                            if (!$isNextPage) {
                                $this->info("   🏁 [{$dateStr}] Выгружена последняя страница дня.");
                                $isDayDone = true;
                                // ВАЖНО: Если день закончен, мы все равно должны подождать минуту
                                // перед тем, как запросить следующий день, иначе поймаем 429
                                $this->waitTimer(61, "Обязательная пауза WB перед следующим днем");
                            } else {
                                $page++;
                                // И здесь тоже! 1 страница = 1 запрос = 1 минута ожидания.
                                $this->waitTimer(61, "Обязательная пауза WB перед следующей страницей");
                            }

                        } catch (\Throwable $e) {
                            $this->error("   🚨 КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
                            $this->waitTimer(30, "Пауза после ошибки соединения");
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
        
        $this->info("\n🏁 СКРИПТ АНАЛИТИКИ ПОЛНОСТЬЮ ЗАВЕРШЕН.");
    }

    private function saveAnalytics(Store $store, array $cards, string $dateStr)
    {
        $now = now();
        $analyticsData = [];

        foreach ($cards as $card) {
            $nmId = $card['nmID'] ?? $card['nmId'] ?? null;
            if (empty($nmId)) continue;

            $stats = $card['statistics']['selectedPeriod'] ?? [];
            $conversions = $stats['conversions'] ?? [];
            $stocks = $card['stocks'] ?? [];

            $analyticsData[] = [
                'store_id' => $store->id, 
                'nm_id'    => $nmId, 
                'date'     => $dateStr,
                
                'vendor_code' => $card['vendorCode'] ?? null,
                'brand_name'  => $card['brandName'] ?? null,
                'object_id'   => $card['objectID'] ?? null,
                'object_name' => $card['objectName'] ?? null,

                // Основные метрики воронки
                'open_card_count'   => $stats['openCardCount'] ?? 0,
                'add_to_cart_count' => $stats['addToCartCount'] ?? 0,
                'orders_count'      => $stats['ordersCount'] ?? 0,
                'buyouts_count'     => $stats['buyoutsCount'] ?? 0,
                'cancel_count'      => $stats['cancelCount'] ?? 0,

                // Финансы
                'orders_sum_rub'  => $stats['ordersSumRub'] ?? 0,
                'buyouts_sum_rub' => $stats['buyoutsSumRub'] ?? 0,
                'cancel_sum_rub'  => $stats['cancelSumRub'] ?? 0,
                'avg_price_rub'   => $stats['avgPriceRub'] ?? 0,

                'avg_orders_count_per_day' => $stats['avgOrdersCountPerDay'] ?? 0,

                // Конверсии
                'conversion_open_to_cart_percent'  => $conversions['addToCartConversion'] ?? 0,
                'conversion_cart_to_order_percent' => $conversions['cartToOrderConversion'] ?? 0,
                'conversion_buyouts_percent'       => $conversions['buyoutConversion'] ?? 0,

                // Остатки (отдает этот эндпоинт)
                'stocks_mp' => $stocks['stocksMp'] ?? 0,
                'stocks_wb' => $stocks['stocksWb'] ?? 0,
                
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($analyticsData)) {
            return;
        }

        $this->line("   -> Массовое сохранение " . count($analyticsData) . " записей...");

        foreach (array_chunk($analyticsData, 500) as $chunk) {
            ProductAnalytic::upsert(
                $chunk,
                ['store_id', 'nm_id', 'date'], // Уникальные ключи
                [
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