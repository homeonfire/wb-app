<?php

namespace App\Console\Commands;

use App\Models\Sku;
use App\Models\Store;
use App\Models\SkuWarehouseStock;
use App\Services\WbService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WbSyncData extends Command
{
    protected $signature = 'wb:sync-data';
    protected $description = 'Синхронизация Цен и скидок по баркодам';

    public function handle()
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            $this->info("💼 Магазин: {$store->name}");

            try {
                $wb = new WbService($store);

                // --- 1. ЦЕНЫ ---
                $this->info("   🔄 Обновляем цены...");
                $prices = $wb->api->Prices()->getPrices(); 

                if ($prices->data->listGoods ?? false) {
                    foreach ($prices->data->listGoods as $good) {
                        // $good->nmID - артикул товара
                        // $good->sizes - массив размеров с ценами

                        foreach ($good->sizes as $size) {
                            // Ищем наш SKU по nmID товара и techSize (или просто обновляем все SKU этого товара, если структура простая)
                            // Но надежнее искать через Product -> Sku

                            // У WB в методе цен нет баркода, это боль. Придется искать SKU через Product.
                            // Но у нас есть nmID. Найдем товар, потом его SKU с таким techSize.

                            $product = \App\Models\Product::where('nm_id', $good->nmID)->first();
                            if ($product) {
                                $targetSize = $size->techSizeName ?? null;
                                if ($targetSize) {
                                    $product->skus()->where('tech_size', $targetSize)->update([
                                        'price' => $size->price,
                                        'discount' => $good->discount
                                    ]);
                                }
                            }
                        }
                    }
                    $this->info("      ✅ Цены обновлены.");
                }

            } catch (\Exception $e) {
                $this->error("   ❌ Ошибка: " . $e->getMessage());
            }
        }
    }
}