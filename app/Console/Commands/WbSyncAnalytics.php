<?php

namespace App\Console\Commands;

use App\Models\ProductAnalytic;
use App\Models\Store;
use App\Services\WbService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WbSyncAnalytics extends Command
{
    protected $signature = 'wb:sync-analytics {--days=3 : За сколько дней грузить}';
    protected $description = 'Загрузка полной воронки продаж, финансов и стоков';

    public function handle()
    {
        date_default_timezone_set('Europe/Moscow');

        $stores = Store::all();
        $days = (int) $this->option('days');
        
        // WB отдает статистику с задержкой, но берем диапазон
        $dateFrom = Carbon::now()->subDays($days);
        $dateTo = Carbon::now();

        $this->log("🚀 СТАРТ ПОЛНОЙ ВЫГРУЗКИ. Период: {$dateFrom->format('Y-m-d')} - {$dateTo->format('Y-m-d')}");

        foreach ($stores as $store) {
            $this->line("----------------------------------------------------------------");
            $this->info("🏪 Магазин: {$store->name} (ID: {$store->id})");

            if (empty($store->api_key_standard)) {
                $this->warn("⚠️ Нет API ключа (Standard). Пропускаем.");
                continue;
            }

            try {
                $wb = new WbService($store);
                
                $currentDate = clone $dateFrom;
                
                while ($currentDate <= $dateTo) {
                    $dayStart = $currentDate->copy()->startOfDay();
                    $dayEnd = $currentDate->copy()->endOfDay();
                    $dateStr = $dayStart->format('Y-m-d');

                    $this->line("");
                    $this->info("📅 Обработка даты: {$dateStr}");

                    $page = 1;
                    $retryCount = 0;
                    $isDayDone = false;

                    while (!$isDayDone) {
                        try {
                            $params = [
                                'limit' => 100,
                                'page' => $page
                            ];

                            $this->log("Запрос страницы {$page}...");
                            
                            $response = $wb->api->Analytics()->nmReportDetail(
                                $dayStart, 
                                $dayEnd, 
                                $params
                            );
                            
                            $retryCount = 0; // Сброс счетчика при успехе

                            $cards = $response->data->cards ?? [];
                            $count = count($cards);

                            if ($count === 0) {
                                $this->log("Данные закончились или отсутствуют за этот день.");
                                $isDayDone = true;
                                break;
                            }

                            $this->log("Получено записей: {$count}. Сохраняем...");
                            $this->saveAnalytics($store, $cards, $dateStr);
                            
                            $isNextPage = $response->data->isNextPage ?? false;

                            if ($isNextPage) {
                                $page++;
                                $this->warn("   Есть следующая страница. Ждем 60 сек (лимит WB)...");
                                $this->waitTimer(60); 
                            } else {
                                $isDayDone = true;
                            }

                        } catch (\Throwable $e) {
                            $msg = $e->getMessage();
                            
                            if (str_contains(strtolower($msg), 'too many requests') || str_contains($msg, '429')) {
                                $retryCount++;
                                $this->error("🔥 ЛИМИТ ЗАПРОСОВ (429). Попытка {$retryCount}/5");
                                
                                if ($retryCount > 5) {
                                    $this->error("❌ Слишком много ошибок. Пропускаем день.");
                                    $isDayDone = true;
                                } else {
                                    $this->waitTimer(60 + ($retryCount * 10), "Остываем");
                                }
                            } else {
                                $this->error("🔴 ОШИБКА API: " . $msg);
                                $isDayDone = true; // Прерываем день при критической ошибке
                            }
                        }
                    }

                    $currentDate->addDay();
                    
                    // Пауза между днями, чтобы не спамить
                    if ($currentDate <= $dateTo) {
                        $this->waitTimer(5, "Пауза перед след. датой");
                    }
                }

            } catch (\Throwable $e) {
                $this->error("💥 Глобальная ошибка магазина: " . $e->getMessage());
            }
        }
        
        $this->info("🏁 ГОТОВО.");
    }

    private function saveAnalytics(Store $store, array $cards, string $date)
    {
        // Используем транзакцию для скорости и надежности
        DB::transaction(function () use ($store, $cards, $date) {
            foreach ($cards as $row) {
                // Основной блок статистики
                $stats = $row->statistics->selectedPeriod ?? null;
                // Блок конверсий
                $conversions = $stats->conversions ?? null;
                // Блок стоков
                $stocks = $row->stocks ?? null;
                // Блок объекта (предмет)
                $object = $row->object ?? null;

                if (!$stats) continue;

                ProductAnalytic::updateOrCreate(
                    [
                        'store_id' => $store->id, 
                        'nm_id' => $row->nmID, 
                        'date' => $date
                    ],
                    [
                        // Инфо
                        'vendor_code' => $row->vendorCode ?? null,
                        'brand_name'  => $row->brandName ?? null,
                        'object_id'   => $object->id ?? null,
                        'object_name' => $object->name ?? null,

                        // Воронка (Количества)
                        'open_card_count'   => $stats->openCardCount ?? 0,
                        'add_to_cart_count' => $stats->addToCartCount ?? 0,
                        'orders_count'      => $stats->ordersCount ?? 0,
                        'buyouts_count'     => $stats->buyoutsCount ?? 0,
                        'cancel_count'      => $stats->cancelCount ?? 0,

                        // Финансы (Суммы)
                        'orders_sum_rub'  => $stats->ordersSumRub ?? 0,
                        'buyouts_sum_rub' => $stats->buyoutsSumRub ?? 0,
                        'cancel_sum_rub'  => $stats->cancelSumRub ?? 0,
                        'avg_price_rub'   => $stats->avgPriceRub ?? 0,

                        // Средние
                        'avg_orders_count_per_day' => $stats->avgOrdersCountPerDay ?? 0,

                        // Конверсии
                        'conversion_open_to_cart_percent'  => $conversions->addToCartPercent ?? 0,
                        'conversion_cart_to_order_percent' => $conversions->cartToOrderPercent ?? 0,
                        'conversion_buyouts_percent'       => $conversions->buyoutsPercent ?? 0,

                        // Стоки
                        'stocks_mp' => $stocks->stocksMp ?? 0,
                        'stocks_wb' => $stocks->stocksWb ?? 0,
                    ]
                );
            }
        });
    }

    private function log($msg)
    {
        $time = date('H:i:s');
        $this->line("   <comment>[{$time}]</comment> {$msg}");
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
        $this->newLine(2);
    }
}