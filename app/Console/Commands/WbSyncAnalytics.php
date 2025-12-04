<?php

namespace App\Console\Commands;

use App\Models\ProductAnalytic;
use App\Models\Store;
use App\Services\WbService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Exception\ClientException;

class WbSyncAnalytics extends Command
{
    protected $signature = 'wb:sync-analytics {--days=3 : За сколько дней грузить}';
    protected $description = 'Загрузка воронки (исправленные поля JSON)';

    public function handle()
    {
        date_default_timezone_set('Europe/Moscow');

        $stores = Store::all();
        $days = (int) $this->option('days');
        
        $dateFrom = Carbon::now()->subDays($days);
        $dateTo = Carbon::now();

        $this->log("🚀 СТАРТ. Период: {$dateFrom->format('Y-m-d')} - {$dateTo->format('Y-m-d')}");

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
                    $this->info("📅 Дата: {$dateStr}");

                    $page = 1;
                    $retryCount = 0;
                    $isDayDone = false;

                    while (!$isDayDone) {
                        try {
                            $this->log("Запрос страницы {$page} (Попытка " . ($retryCount + 1) . ")...");
                            
                            $params = [
                                'limit' => 100,
                                'page' => $page
                            ];

                            $startTime = microtime(true);
                            
                            $response = $wb->api->Analytics()->nmReportDetail(
                                $dayStart, 
                                $dayEnd, 
                                $params
                            );
                            
                            $duration = round(microtime(true) - $startTime, 2);
                            $this->log("✅ Ответ API получен за {$duration} сек.");

                            // Сброс счетчика ошибок при успехе
                            $retryCount = 0;

                            $cards = $response->data->cards ?? [];
                            $count = count($cards);

                            if ($count === 0) {
                                $this->log("Пустой список (cards). Данные за день загружены.");
                                $isDayDone = true;
                                break;
                            }

                            $this->log("Сохранение {$count} записей...");
                            $this->saveAnalytics($store, $cards, $dateStr);
                            
                            $isNextPage = $response->data->isNextPage ?? false;

                            if ($isNextPage) {
                                $page++;
                                $this->warn("   Есть следующая страница. Ждем 60 сек...");
                                $this->waitTimer(60); 
                            } else {
                                $isDayDone = true;
                            }

                        } catch (\Throwable $e) {
                            $msg = $e->getMessage();
                            
                            // Ловим лимиты (429 / Too many requests)
                            if (str_contains(strtolower($msg), 'too many requests') || str_contains($msg, '429')) {
                                $retryCount++;
                                $this->error("🔥 ЛИМИТ ЗАПРОСОВ (Too many requests)!");
                                
                                if ($retryCount > 5) {
                                    $this->error("❌ 5 неудачных попыток. Пропускаем день.");
                                    $isDayDone = true;
                                } else {
                                    $sleepTime = 60 + ($retryCount * 10);
                                    $this->waitTimer($sleepTime, "Остываем (Попытка {$retryCount}/5)");
                                }
                            } else {
                                $this->error("🔴 КРИТИЧЕСКАЯ ОШИБКА: " . $msg);
                                $isDayDone = true;
                            }
                        }
                    }

                    $currentDate->addDay();

                    if ($currentDate <= $dateTo) {
                        $this->waitTimer(65, "Переход к след. дате");
                    }
                }

            } catch (\Throwable $e) {
                $this->error("💥 Глобальная ошибка сервиса: " . $e->getMessage());
            }
        }
    }

    private function saveAnalytics(Store $store, array $cards, string $date)
    {
        DB::transaction(function () use ($store, $cards, $date) {
            foreach ($cards as $row) {
                $stats = $row->statistics->selectedPeriod ?? null;
                
                // 👇 ИСПРАВЛЕННЫЙ БЛОК MAPPING'А 👇
                ProductAnalytic::updateOrCreate(
                    ['store_id' => $store->id, 'nm_id' => $row->nmID, 'date' => $date],
                    [
                        // Было openCard, стало openCardCount (как в JSON)
                        'open_card_count' => $stats->openCardCount ?? 0,
                        
                        // Было addToCart, стало addToCartCount
                        'add_to_cart_count' => $stats->addToCartCount ?? 0,
                        
                        // Было orders, стало ordersCount
                        'orders_count' => $stats->ordersCount ?? 0,
                        
                        'buyouts_count' => $stats->buyoutsCount ?? 0,
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