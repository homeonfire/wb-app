<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Store;
use App\Models\WarehouseStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WbSyncStocks extends Command
{
    protected $signature = 'wb:sync-stocks';
    protected $description = 'Синхронизация остатков FBO по складам (Новая таблица)';

    public function handle()
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            $this->warn("\n==================================================");
            $this->warn("📦 Магазин: {$store->name} (ID: {$store->id})");
            $this->warn("==================================================");

            if (empty($store->api_key_standard)) {
                $this->error("   ❌ Нет API ключа (api_key_standard). Пропускаем.");
                continue;
            }

            try {
                // Получаем все товары магазина
                $products = Product::where('store_id', $store->id)->get(['id', 'nm_id']);
                if ($products->isEmpty()) {
                    $this->warn("   ⚠️ В базе нет товаров. Пропускаем.");
                    continue;
                }

                $productMap = $products->keyBy('nm_id');
                $nmIds = $products->pluck('nm_id')->toArray();
                
                // Разбиваем массив nmId строго по 1000 штук (лимит WB)
                $chunks = array_chunk($nmIds, 1000); 
                $stocksUpsertData = [];
                $now = now();

                foreach ($chunks as $index => $chunkNmIds) {
                    $retryCount = 0;
                    $isSuccess = false;

                    while (!$isSuccess) {
                        try {
                            $this->line("   👉 Запрос остатков. Пачка #" . ($index + 1) . " (Артикулов: " . count($chunkNmIds) . ")...");

                            $payload = [
                                'nmIds'  => $chunkNmIds,
                                'limit'  => 250000,
                                'offset' => 0
                            ];

                            $startTime = microtime(true);
                            $response = Http::withHeaders([
                                'Authorization' => $store->api_key_standard,
                                'Content-Type'  => 'application/json',
                            ])->timeout(30)->post('https://seller-analytics-api.wildberries.ru/api/analytics/v1/stocks-report/wb-warehouses', $payload);
                            
                            $duration = round(microtime(true) - $startTime, 2);

                            if ($response->status() === 429) {
                                $retryCount++;
                                $this->warn("   🔥 Лимит API WB. Ждем... Попытка {$retryCount}/10");
                                if ($retryCount > 10) break;
                                $this->waitTimer(31, "Остываем после 429 ошибки");
                                continue;
                            }

                            if (!$response->successful()) {
                                $this->error("   🔴 ОШИБКА API HTTP: " . $response->status() . " " . $response->body());
                                break;
                            }

                            $this->line("   ✅ Ответ получен за {$duration} сек.");
                            $data = $response->json();
                            $items = $data['data']['items'] ?? [];

                            foreach ($items as $item) {
                                $nmId = $item['nmId'] ?? null;
                                $productId = $productMap[$nmId]->id ?? null;
                                
                                if (!$productId) continue;

                                $stocksUpsertData[] = [
                                    'product_id'         => $productId,
                                    'nm_id'              => $nmId,
                                    'chrt_id'            => $item['chrtId'],
                                    'warehouse_id'       => $item['warehouseId'],
                                    'warehouse_name'     => $item['warehouseName'],
                                    'region_name'        => $item['regionName'] ?? null,
                                    'quantity'           => $item['quantity'] ?? 0,
                                    'in_way_to_client'   => $item['inWayToClient'] ?? 0,
                                    'in_way_from_client' => $item['inWayFromClient'] ?? 0,
                                    'created_at'         => $now,
                                    'updated_at'         => $now,
                                ];
                            }

                            $isSuccess = true;
                            
                            if ($index < count($chunks) - 1) {
                                $this->waitTimer(31, "Лимит WB: пауза перед следующей пачкой");
                            }

                        } catch (\Throwable $e) {
                            $this->error("   🚨 Ошибка: " . $e->getMessage());
                            $this->waitTimer(10, "Пауза после ошибки соединения");
                        }
                    }
                }

                if (!empty($stocksUpsertData)) {
                    $this->line("   -> Сохранение " . count($stocksUpsertData) . " записей остатков...");

                    // Обнуляем старые остатки (чтобы исчезли товары, которых больше нет на складе)
                    WarehouseStock::whereIn('product_id', $products->pluck('id'))->update([
                        'quantity' => 0,
                        'in_way_to_client' => 0,
                        'in_way_from_client' => 0
                    ]);

                    // Массовая вставка (Upsert)
                    foreach (array_chunk($stocksUpsertData, 1000) as $chunk) {
                        WarehouseStock::upsert(
                            $chunk,
                            ['nm_id', 'chrt_id', 'warehouse_id'], // Составной ключ
                            ['quantity', 'in_way_to_client', 'in_way_from_client', 'updated_at']
                        );
                    }
                    $this->info("   ✅ Остатки FBO обновлены.");
                } else {
                    $this->line("   ℹ️ Нет остатков для сохранения.");
                }

            } catch (\Exception $e) {
                $this->error("   ❌ Критическая ошибка: " . $e->getMessage());
            }
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