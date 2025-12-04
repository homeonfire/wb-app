<?php

namespace App\Console\Commands;

use App\Models\OrderRaw;
use App\Models\Store;
use App\Services\WbService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WbSyncOrders extends Command
{
    // Добавили флаг --store
    protected $signature = 'wb:sync-orders 
                            {--days= : Принудительно загрузить за X дней} 
                            {--store= : ID магазина для точечной загрузки}';
                            
    protected $description = 'Синхронизация заказов (Looping / ordersFromDate)';

    public function handle()
    {
        date_default_timezone_set('Europe/Moscow');

        $storeId = $this->option('store');

        // Логика выбора магазинов
        if ($storeId) {
            $stores = Store::where('id', $storeId)->get();
            if ($stores->isEmpty()) {
                $this->error("❌ Магазин с ID {$storeId} не найден.");
                return 1;
            }
            $this->info("🎯 Режим одного магазина: ID {$storeId}");
        } else {
            $stores = Store::all();
        }

        foreach ($stores as $store) {
            $this->line("------------------------------------------------");
            $this->info("📦 Магазин: {$store->name} (ID: {$store->id})");
            
            if (empty($store->api_key_stat)) {
                $this->warn("   ⚠️ Нет ключа Статистики. Пропускаем.");
                continue;
            }

            try {
                // 1. ОПРЕДЕЛЯЕМ СТАРТОВУЮ ДАТУ
                $lastOrder = OrderRaw::where('store_id', $store->id)
                    ->orderBy('last_change_date', 'desc')
                    ->first();
                
                if ($this->option('days')) {
                    $startDate = Carbon::now()->subDays((int)$this->option('days'));
                    $this->line("   🚩 Режим: принудительно за " . $this->option('days') . " дн.");
                } elseif ($lastOrder && $lastOrder->last_change_date) {
                    $startDate = Carbon::parse($lastOrder->last_change_date)->subMinutes(30);
                    $this->line("   🚩 Режим: догрузка с последнего изменения.");
                } else {
                    $startDate = Carbon::now()->subDays(30);
                    $this->line("   🚩 Режим: полная загрузка (30 дней).");
                }

                $wb = new WbService($store);
                
                $currentDateFrom = clone $startDate;
                $hasMoreData = true;
                $batchNum = 1;
                $totalLoaded = 0;

                // 2. ЦИКЛ ЗАГРУЗКИ
                while ($hasMoreData) {
                    $dateStr = $currentDateFrom->format('Y-m-d\TH:i:s');
                    $this->line("");
                    $this->log("Batch #{$batchNum}: Запрос ordersFromDate с: <info>{$dateStr}</info>");

                    $startTime = microtime(true);

                    $orders = $wb->api->Statistics()->ordersFromDate($currentDateFrom);
                    
                    $duration = round(microtime(true) - $startTime, 2);
                    
                    if (!is_array($orders)) {
                        $orders = []; 
                    }

                    $count = count($orders);
                    $this->log("✅ Ответ за {$duration} сек. Записей: <comment>{$count}</comment>");

                    if ($count === 0) {
                        $this->log("⏹️ Новых данных нет.");
                        $hasMoreData = false;
                        break;
                    }

                    $maxLastChangeDate = null;

                    foreach (array_chunk($orders, 500) as $chunk) {
                        DB::transaction(function () use ($chunk, $store, &$maxLastChangeDate) {
                            foreach ($chunk as $item) {
                                OrderRaw::updateOrCreate(
                                    ['srid' => $item->srid],
                                    [
                                        'store_id' => $store->id,
                                        'order_date' => $item->date,
                                        'last_change_date' => $item->lastChangeDate,
                                        'nm_id' => $item->nmId,
                                        'barcode' => $item->barcode,
                                        'total_price' => $item->totalPrice,
                                        'discount_percent' => $item->discountPercent,
                                        'warehouse_name' => $item->warehouseName,
                                        'oblast_okrug_name' => $item->oblastOkrugName,
                                        'finished_price' => $item->finishedPrice,
                                        'is_cancel' => $item->isCancel,
                                        'cancel_dt' => $item->cancelDate,
                                    ]
                                );

                                $itemChangeDate = Carbon::parse($item->lastChangeDate);
                                if (!$maxLastChangeDate || $itemChangeDate->gt($maxLastChangeDate)) {
                                    $maxLastChangeDate = $itemChangeDate;
                                }
                            }
                        });
                    }

                    $totalLoaded += $count;
                    $this->log("💾 Сохранено. Итого за сессию: {$totalLoaded}");

                    // 3. СДВИГАЕМ ДАТУ
                    if ($maxLastChangeDate) {
                        if ($maxLastChangeDate->lte($currentDateFrom)) {
                             $newDate = $currentDateFrom->addSecond();
                        } else {
                             $newDate = $maxLastChangeDate;
                        }

                        $this->line("   ➡️ Следующий запрос с: " . $newDate->format('Y-m-d H:i:s'));
                        $currentDateFrom = $newDate;
                    } else {
                        $hasMoreData = false;
                    }

                    $batchNum++;
                    
                    if ($count > 2000) {
                        sleep(2);
                    }
                }

            } catch (\Throwable $e) {
                $this->error("❌ Ошибка: " . $e->getMessage());
            }
        }
        
        $this->info("\n🏁 Готово.");
    }

    private function log($msg)
    {
        $time = date('H:i:s');
        $this->line("   [{$time}] {$msg}");
    }
}