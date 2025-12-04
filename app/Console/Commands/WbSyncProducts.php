<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Sku;
use App\Models\Store;
use App\Services\WbService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WbSyncProducts extends Command
{
    // Имя команды
    protected $signature = 'wb:sync-products';
    
    // Описание
    protected $description = 'Полная синхронизация товаров с Wildberries (через курсор)';

    public function handle()
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            $this->info("🚀 Запуск для магазина: {$store->name}");

            if (empty($store->api_key_standard)) {
                $this->error("❌ Нет API ключа! Пропускаем.");
                continue;
            }

            try {
                $wb = new WbService($store);
                
                // Переменные для цикла
                $limit = 100;
                $updatedAt = '';
                $nmId = 0;
                $totalLoaded = 0;
                
                do {
                    // 1. Делаем запрос к API
                    // Передаем updatedAt и nmId от предыдущего шага (курсор)
                    $response = $wb->content()->getCardsList(
                        limit: $limit,
                        updatedAt: $updatedAt,
                        nmId: $nmId
                    );

                    $cards = $response->cards ?? [];
                    $count = count($cards);

                    if ($count === 0) {
                        break; // Товары кончились
                    }

                    // 2. Сохраняем пачку в БД
                    $this->processCards($store, $cards);
                    
                    $totalLoaded += $count;
                    
                    // Данные из курсора для следующего запроса
                    $cursor = $response->cursor;
                    $updatedAt = $cursor->updatedAt;
                    $nmId = $cursor->nmID;
                    $totalInWb = $cursor->total; // Всего товаров на WB по данным API

                    $this->info("   ✅ Загружено: {$count} шт. (Всего: {$totalLoaded} / ~{$totalInWb})");

                    // 3. Пауза перед следующим запросом (защита от rate limit)
                    if ($count >= $limit) {
                        $this->comment("   ⏳ Пауза 2 сек...");
                        sleep(2); 
                    }

                } while ($count >= $limit); // Если пришло меньше лимита, значит это последняя страница

                $this->info("🏁 Готово! Всего обработано товаров: {$totalLoaded}");

            } catch (\Exception $e) {
                $this->error("❌ Ошибка при работе с API: " . $e->getMessage());
            }
            
            $this->newLine();
        }
    }

    private function processCards(Store $store, array $cards)
    {
        // Оборачиваем пачку в транзакцию для скорости и надежности
        DB::transaction(function () use ($store, $cards) {
            foreach ($cards as $card) {
                // 1. Товар
                $product = Product::updateOrCreate(
                    [
                        'nm_id' => $card->nmID,
                    ],
                    [
                        'store_id' => $store->id,
                        'vendor_code' => $card->vendorCode,
                        'title' => $card->title ?? 'Без названия',
                        'brand' => $card->brand ?? null,
                        // Берем фото, если есть массив фото и он не пуст
                        'main_image_url' => ($card->photos[0]->big ?? null), 
                    ]
                );

                // 2. Размеры (SKU)
                foreach ($card->sizes as $size) {
                    // У размера может быть несколько баркодов, берем все
                    if (!empty($size->skus)) {
                        foreach ($size->skus as $barcode) {
                            Sku::updateOrCreate(
                                [
                                    'barcode' => $barcode,
                                ],
                                [
                                    'product_id' => $product->id,
                                    'tech_size' => $size->techSize ?? $size->wbSize ?? '-',
                                ]
                            );
                        }
                    }
                }
            }
        });
    }
}