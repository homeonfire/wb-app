<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductAnalytic;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WbSyncAnalytics extends Command
{
    protected $signature = 'wb:sync-analytics {--days=7 : За сколько дней грузить (максимум 7 для History API)}';
    protected $description = 'Загрузка воронки продаж по дням (API v3 /sales-funnel/products/history)';

    public function handle()
    {
        date_default_timezone_set('Europe/Moscow');

        $stores = Store::all();
        // Метод history поддерживает максимум 7 дней
        $days = min(7, (int) $this->option('days')); 
        
        $dateFrom = Carbon::now()->subDays($days);
        $dateTo = Carbon::now();

        $this->info("🚀 СТАРТ СКРИПТА (API v3 History). Период: {$dateFrom->format('Y-m-d')} - {$dateTo->format('Y-m-d')}");

        foreach ($stores as $store) {
            $this->warn("\n==================================================");
            $this->warn("🏪 Магазин: {$store->name} (ID: {$store->id})");
            $this->warn("==================================================");

            if (empty($store->api_key_standard)) {
                $this->error("⚠️ Нет API ключа (Standard). Пропускаем.");
                continue;
            }

            try {
                // Берем nm_id товаров этого магазина из нашей базы
                $nmIds = Product::where('store_id', $store->id)->pluck('nm_id')->toArray();
                
                if (empty($nmIds)) {
                    $this->warn("   ⚠️ В базе нет товаров для этого магазина. Сначала запусти wb:sync-products.");
                    continue;
                }

                // ВАЖНО: Метод history требует строго не больше 20 nmId в одном запросе!
                $chunks = array_chunk($nmIds, 20);
                
                $this->info("   📊 Найдено товаров: " . count($nmIds) . ". Разбито на " . count($chunks) . " пачек (по 20 шт).");

                foreach ($chunks as $index => $chunkNmIds) {
                    $retryCount = 0;
                    $isChunkDone = false;

                    while (!$isChunkDone) {
                        try {
                            $this->line("   👉 Запрос пачки #" . ($index + 1) . " из " . count($chunks) . "...");
                            
                            $payload = [
                                'selectedPeriod' => [
                                    'start' => $dateFrom->format('Y-m-d'),
                                    'end'   => $dateTo->format('Y-m-d')
                                ],
                                'nmIds' => $chunkNmIds,
                                'aggregationLevel' => 'day',
                                'skipDeletedNm' => false
                            ];

                            $startTime = microtime(true);

                            $response = Http::withHeaders([
                                'Authorization' => $store->api_key_standard,
                                'Content-Type'  => 'application/json',
                                'Accept'        => 'application/json',
                            ])->timeout(30)->post('https://seller-analytics-api.wildberries.ru/api/analytics/v3/sales-funnel/products/history', $payload);
                            
                            $duration = round(microtime(true) - $startTime, 2);

                            // Обработка 429 Лимита (3 запроса в минуту, 1 в 20 сек)
                            if ($response->status() === 429) {
                                $retryCount++;
                                $this->warn("   🔥 429 Too Many Requests. Ждем интервал WB. Попытка {$retryCount}/10");
                                
                                if ($retryCount > 10) {
                                    $this->error("   ❌ Лимит не отпускает. Пропускаем пачку.");
                                    $isChunkDone = true;
                                    break;
                                }
                                
                                $this->waitTimer(22, "Ожидание сброса лимита");
                                continue; 
                            }

                            if (!$response->successful()) {
                                $this->error("   🔴 ОШИБКА API HTTP: " . $response->status() . " " . $response->body());
                                $isChunkDone = true;
                                break;
                            }

                            $retryCount = 0; 
                            $data = $response->json();
                            // В методе history карточки лежат либо в data.cards, либо просто в data
                            $cards = $data['data']['cards'] ?? $data['data'] ?? $data ?? [];
                            $count = count($cards);

                            if ($count > 0) {
                                $this->saveAnalytics($store, $cards);
                            } else {
                                $this->line("   ℹ️ По этой пачке нет статистики за период.");
                            }

                            $isChunkDone = true;

                            // ОБЯЗАТЕЛЬНАЯ ПАУЗА. WB требует 20 сек между любыми запросами
                            if ($index < count($chunks) - 1) {
                                $this->waitTimer(21, "Требование WB: 20 сек между запросами");
                            }

                        } catch (\Throwable $e) {
                            $this->error("   🚨 ОШИБКА: " . $e->getMessage());
                            $this->waitTimer(30, "Пауза после ошибки");
                        }
                    }
                }

            } catch (\Throwable $e) {
                $this->error("💥 Глобальная ошибка при обработке магазина: " . $e->getMessage());
            }
        }
        
        $this->info("\n🏁 СКРИПТ АНАЛИТИКИ ПОЛНОСТЬЮ ЗАВЕРШЕН.");
    }

    private function saveAnalytics(Store $store, array $cards)
    {
        $now = now();
        $analyticsData = [];

        foreach ($cards as $card) {
            // Данные о товаре теперь лежат внутри объекта 'product'
            $productInfo = $card['product'] ?? [];
            $nmId = $productInfo['nmId'] ?? null;
            
            if (empty($nmId)) continue;

            $history = $card['history'] ?? [];
            if (empty($history)) continue;

            foreach ($history as $stat) {
                // Дата теперь называется 'date'
                $statDate = $stat['date'] ?? null;
                if (!$statDate) continue;

                $orderCount = $stat['orderCount'] ?? 0;
                $orderSum = $stat['orderSum'] ?? 0;
                
                // Так как WB не отдает среднюю цену в этом методе, считаем её сами
                $avgPrice = $orderCount > 0 ? round($orderSum / $orderCount, 2) : 0;

                $analyticsData[] = [
                    'store_id' => $store->id, 
                    'nm_id'    => $nmId, 
                    'date'     => date('Y-m-d', strtotime($statDate)),
                    
                    'vendor_code' => $productInfo['vendorCode'] ?? null,
                    'brand_name'  => $productInfo['brandName'] ?? null,
                    'object_id'   => $productInfo['subjectId'] ?? null,
                    'object_name' => $productInfo['subjectName'] ?? null,

                    'open_card_count'   => $stat['openCount'] ?? 0,
                    'add_to_cart_count' => $stat['cartCount'] ?? 0,
                    'orders_count'      => $orderCount,
                    'buyouts_count'     => $stat['buyoutCount'] ?? 0,
                    'cancel_count'      => $stat['cancelCount'] ?? 0,

                    'orders_sum_rub'  => $orderSum,
                    'buyouts_sum_rub' => $stat['buyoutSum'] ?? 0,
                    'cancel_sum_rub'  => $stat['cancelSum'] ?? 0,
                    'avg_price_rub'   => $avgPrice,

                    'avg_orders_count_per_day' => 0, // В этом методе нет этого поля

                    'conversion_open_to_cart_percent'  => $stat['addToCartConversion'] ?? 0,
                    'conversion_cart_to_order_percent' => $stat['cartToOrderConversion'] ?? 0,
                    'conversion_buyouts_percent'       => $stat['buyoutPercent'] ?? 0,

                    'stocks_mp' => 0, // Не затираем старые остатки
                    'stocks_wb' => 0,
                    
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (empty($analyticsData)) return;

        $this->line("   -> Подготовлено " . count($analyticsData) . " записей для БД...");

        foreach (array_chunk($analyticsData, 500) as $chunk) {
            ProductAnalytic::upsert(
                $chunk,
                ['store_id', 'nm_id', 'date'], 
                [
                    'vendor_code', 'brand_name', 'object_id', 'object_name',
                    'open_card_count', 'add_to_cart_count', 'orders_count', 'buyouts_count', 'cancel_count',
                    'orders_sum_rub', 'buyouts_sum_rub', 'cancel_sum_rub', 'avg_price_rub',
                    'conversion_open_to_cart_percent', 'conversion_cart_to_order_percent', 'conversion_buyouts_percent',
                    'updated_at'
                ]
            );
        }
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