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
        
        $dateFrom = Carbon::now()->subDays($days);
        $dateTo = Carbon::now();

        $this->info("🚀 СТАРТ СКРИПТА. Период: {$dateFrom->format('Y-m-d')} - {$dateTo->format('Y-m-d')}");

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
                    $this->info("📅 [{$dateStr}] Начинаем обработку дня");

                    $page = 1;
                    $retryCount = 0;
                    $isDayDone = false;

                    while (!$isDayDone) {
                        try {
                            $params = [
                                'limit' => 100,
                                'page' => $page
                            ];

                            $this->line("   👉 [Стр. {$page}] Отправка запроса к API WB (nmReportDetail)...");
                            
                            $startTime = microtime(true);

                            // --- ЗАПРОС К API ---
                            $response = $wb->api->Analytics()->nmReportDetail(
                                $dayStart, 
                                $dayEnd, 
                                $params
                            );
                            // --------------------

                            $duration = round(microtime(true) - $startTime, 2);
                            $this->info("   ✅ [Стр. {$page}] Ответ получен за {$duration} сек.");

                            $retryCount = 0; // Сброс счетчика ошибок при успехе

                            $cards = $response->data->cards ?? [];
                            $count = count($cards);

                            $this->line("   📦 [Стр. {$page}] В ответе записей: {$count}");

                            if ($count === 0) {
                                $this->warn("   ⏹️ [Стр. {$page}] Список пуст. Данные за день закончились.");
                                $isDayDone = true;
                                break;
                            }

                            $this->line("   💾 [Стр. {$page}] Сохраняем в БД...");
                            $this->saveAnalytics($store, $cards, $dateStr);
                            $this->info("   ✨ [Стр. {$page}] Сохранено.");
                            
                            $isNextPage = $response->data->isNextPage ?? false;

                            if ($isNextPage) {
                                $page++;
                                $this->warn("   ⏭️ Флаг isNextPage=true. Ждем 60 сек перед следующей страницей...");
                                $this->waitTimer(60, "Лимит WB между страницами"); 
                            } else {
                                $this->info("   🏁 [{$dateStr}] Флаг isNextPage=false. День загружен полностью.");
                                $isDayDone = true;
                            }

                        } catch (\Throwable $e) {
                            // --- БЛОК ОБРАБОТКИ ОШИБОК ---
                            $msg = $e->getMessage();
                            $this->error("   🚨 ПОЙМАНО ИСКЛЮЧЕНИЕ: " . $msg);
                            
                            // Проверка на 429 Too Many Requests
                            if (str_contains(strtolower($msg), 'too many requests') || str_contains($msg, '429')) {
                                $retryCount++;
                                $this->error("   🔥 ОШИБКА 429 (ЛИМИТ). Попытка восстановления {$retryCount}/5");
                                
                                if ($retryCount > 5) {
                                    $this->error("   ❌ Превышено максимальное кол-во попыток (5). Пропускаем день {$dateStr} и идем дальше.");
                                    $isDayDone = true;
                                } else {
                                    $sleepTime = 60 + ($retryCount * 10); // Увеличиваем время ожидания с каждой ошибкой
                                    $this->waitTimer($sleepTime, "Остываем после 429 ошибки");
                                }
                            } else {
                                // Другие ошибки (например 401, 500)
                                $this->error("   🔴 КРИТИЧЕСКАЯ ОШИБКА (не 429). Прерываем обработку дня.");
                                $isDayDone = true; 
                            }
                        }
                    }

                    $currentDate->addDay();
                    
                    // Пауза между сменой дат (на всякий случай)
                    if ($currentDate <= $dateTo) {
                        $this->waitTimer(5, "Короткая пауза перед следующей датой");
                    }
                }

            } catch (\Throwable $e) {
                $this->error("💥 Глобальная ошибка при обработке магазина: " . $e->getMessage());
            }
        }
        
        $this->info("🏁 СКРИПТ ПОЛНОСТЬЮ ЗАВЕРШЕН.");
    }

    private function saveAnalytics(Store $store, array $cards, string $date)
    {
        DB::transaction(function () use ($store, $cards, $date) {
            foreach ($cards as $row) {
                $stats = $row->statistics->selectedPeriod ?? null;
                $conversions = $stats->conversions ?? null;
                $stocks = $row->stocks ?? null;
                $object = $row->object ?? null;

                if (!$stats) continue;

                ProductAnalytic::updateOrCreate(
                    [
                        'store_id' => $store->id, 
                        'nm_id' => $row->nmID, 
                        'date' => $date
                    ],
                    [
                        'vendor_code' => $row->vendorCode ?? null,
                        'brand_name'  => $row->brandName ?? null,
                        'object_id'   => $object->id ?? null,
                        'object_name' => $object->name ?? null,

                        'open_card_count'   => $stats->openCardCount ?? 0,
                        'add_to_cart_count' => $stats->addToCartCount ?? 0,
                        'orders_count'      => $stats->ordersCount ?? 0,
                        'buyouts_count'     => $stats->buyoutsCount ?? 0,
                        'cancel_count'      => $stats->cancelCount ?? 0,

                        'orders_sum_rub'  => $stats->ordersSumRub ?? 0,
                        'buyouts_sum_rub' => $stats->buyoutsSumRub ?? 0,
                        'cancel_sum_rub'  => $stats->cancelSumRub ?? 0,
                        'avg_price_rub'   => $stats->avgPriceRub ?? 0,

                        'avg_orders_count_per_day' => $stats->avgOrdersCountPerDay ?? 0,

                        'conversion_open_to_cart_percent'  => $conversions->addToCartPercent ?? 0,
                        'conversion_cart_to_order_percent' => $conversions->cartToOrderPercent ?? 0,
                        'conversion_buyouts_percent'       => $conversions->buyoutsPercent ?? 0,

                        'stocks_mp' => $stocks->stocksMp ?? 0,
                        'stocks_wb' => $stocks->stocksWb ?? 0,
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
        $this->newLine(2);
    }
}