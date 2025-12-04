<?php

namespace App\Console\Commands;

use App\Models\SaleRaw;
use App\Models\Store;
use App\Services\WbService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WbSyncSales extends Command
{
    // Добавили флаг --store
    protected $signature = 'wb:sync-sales 
                            {--days= : Принудительно загрузить за X дней} 
                            {--store= : ID магазина для точечной загрузки}';
                            
    protected $description = 'Синхронизация продаж (Looping / salesFromDate)';

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
            $this->info("💰 Магазин: {$store->name} (ID: {$store->id})");
            
            if (empty($store->api_key_stat)) {
                continue;
            }

            try {
                // 1. СТАРТОВАЯ ДАТА
                $lastSale = SaleRaw::where('store_id', $store->id)
                    ->orderBy('last_change_date', 'desc')
                    ->first();
                
                if ($this->option('days')) {
                    $startDate = Carbon::now()->subDays((int)$this->option('days'));
                    $this->line("   🚩 Режим: принудительно за " . $this->option('days') . " дн.");
                } elseif ($lastSale && $lastSale->last_change_date) {
                    $startDate = Carbon::parse($lastSale->last_change_date)->subMinutes(30);
                    $this->line("   🚩 Режим: догрузка.");
                } else {
                    $startDate = Carbon::now()->subDays(30);
                    $this->line("   🚩 Режим: полная загрузка (30 дней).");
                }

                $wb = new WbService($store);
                
                $currentDateFrom = clone $startDate;
                $hasMoreData = true;
                $batchNum = 1;
                $totalLoaded = 0;

                // 2. ЦИКЛ
                while ($hasMoreData) {
                    $dateStr = $currentDateFrom->format('Y-m-d\TH:i:s');
                    $this->line("");
                    $this->log("Batch #{$batchNum}: Запрос salesFromDate с: <info>{$dateStr}</info>");

                    $startTime = microtime(true);

                    $sales = $wb->api->Statistics()->salesFromDate($currentDateFrom);
                    
                    $duration = round(microtime(true) - $startTime, 2);
                    if (!is_array($sales)) $sales = [];

                    $count = count($sales);
                    $this->log("✅ Ответ за {$duration} сек. Записей: <comment>{$count}</comment>");

                    if ($count === 0) {
                        $this->log("⏹️ Новых данных нет.");
                        $hasMoreData = false;
                        break;
                    }

                    $maxLastChangeDate = null;

                    foreach (array_chunk($sales, 500) as $chunk) {
                        DB::transaction(function () use ($chunk, $store, &$maxLastChangeDate) {
                            foreach ($chunk as $item) {
                                SaleRaw::updateOrCreate(
                                    ['sale_id' => $item->saleID],
                                    [
                                        'store_id' => $store->id,
                                        'sale_date' => $item->date,
                                        'last_change_date' => $item->lastChangeDate,
                                        'nm_id' => $item->nmId,
                                        'barcode' => $item->barcode,
                                        'total_price' => $item->totalPrice,
                                        'discount_percent' => $item->discountPercent,
                                        'price_with_disc' => $item->priceWithDisc,
                                        'for_pay' => $item->forPay,
                                        'finished_price' => $item->finishedPrice,
                                        'warehouse_name' => $item->warehouseName,
                                        'region_name' => $item->regionName,
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
                    $this->log("💾 Сохранено. Итого: {$totalLoaded}");

                    // 3. СДВИГ ДАТЫ
                    if ($maxLastChangeDate) {
                        if ($maxLastChangeDate->lte($currentDateFrom)) {
                             $newDate = $currentDateFrom->addSecond();
                        } else {
                             $newDate = $maxLastChangeDate;
                        }
                        $currentDateFrom = $newDate;
                        $this->line("   ➡️ Следующий запрос с: " . $newDate->format('Y-m-d H:i:s'));
                    } else {
                        $hasMoreData = false;
                    }

                    $batchNum++;
                    if ($count > 2000) sleep(2);
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