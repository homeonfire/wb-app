<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Sku;
use App\Models\Store;
use App\Services\WbService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;

class WbSyncProducts extends Command
{
    protected $signature = 'wb:sync-products';
    protected $description = 'Полная синхронизация товаров с Wildberries (с дампом SQL-запросов)';

    public function handle()
    {
        // Включаем перехват и вывод ВСЕХ SQL-запросов в консоль
        DB::listen(function (QueryExecuted $query) {
            $sql = $query->sql;
            $bindings = $query->bindings;

            // Подставляем значения биндингов в строку для читаемости
            foreach ($bindings as $binding) {
                $value = is_null($binding) ? 'NULL' : (is_numeric($binding) ? $binding : "'" . addslashes((string)$binding) . "'");
                $sql = preg_replace('/\?/', $value, $sql, 1);
            }

            $this->comment("   [SQL " . round($query->time, 2) . "ms] {$sql}");
        });

        $this->info("▶ Получаем список магазинов...");
        $stores = Store::all();

        foreach ($stores as $store) {
            $this->warn("\n==================================================");
            $this->warn("🚀 ЗАПУСК ДЛЯ МАГАЗИНА: {$store->name} (ID: {$store->id})");
            $this->warn("==================================================");

            if (empty($store->api_key_standard)) {
                $this->error("❌ Нет API ключа! Пропускаем.");
                continue;
            }

            try {
                $wb = new WbService($store);
                $limit = 100;
                $updatedAt = '';
                $nmId = 0;
                
                do {
                    $this->line("\n📡 Запрос к API WB... (updatedAt: '{$updatedAt}', nmId: {$nmId})");
                    $response = $wb->content()->getCardsList(
                        limit: $limit,
                        updatedAt: $updatedAt,
                        nmId: $nmId
                    );
                    $cards = $response->cards ?? [];
                    $count = count($cards);

                    if ($count === 0) break;

                    $this->line("💾 Начинаем сохранение ({$count} шт.)...");
                    $this->processCards($store, $cards);
                    
                    $cursor = $response->cursor;
                    if ($updatedAt === $cursor->updatedAt && $nmId === $cursor->nmID) {
                        $this->error("❌ Курсор зациклился.");
                        break;
                    }

                    $updatedAt = $cursor->updatedAt;
                    $nmId = $cursor->nmID;

                } while ($count >= $limit);

            } catch (\Exception $e) {
                $this->error("❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
            }
        }
    }

    private function processCards(Store $store, array $cards)
    {
        $now = now();
        $productsData = [];
        $nmIds = [];

        // 1. Собираем все товары в один массив
        foreach ($cards as $card) {
            $nmIds[] = $card->nmID;
            $productsData[] = [
                'nm_id' => $card->nmID,
                'store_id' => $store->id,
                'vendor_code' => $card->vendorCode ?? 'Без артикула',
                'title' => $card->title ?? 'Без названия',
                'brand' => $card->brand ?? null,
                'main_image_url' => ($card->photos[0]->big ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Если товаров нет, выходим
        if (empty($productsData)) return;

        // 2. Вставляем ВСЕ товары ОДНИМ запросом (или обновляем, если nm_id уже есть)
        $this->line("   -> Массовое сохранение " . count($productsData) . " товаров...");
        Product::upsert(
            $productsData, 
            ['nm_id'], // Уникальное поле для проверки конфликта
            ['store_id', 'vendor_code', 'title', 'brand', 'main_image_url', 'updated_at'] // Поля для обновления
        );

        // 3. Получаем ID вставленных товаров из базы (чтобы привязать к ним SKU)
        $productMap = Product::whereIn('nm_id', $nmIds)->pluck('id', 'nm_id')->toArray();

        // 4. Собираем все SKU в один массив
        $skusData = [];
        foreach ($cards as $card) {
            $productId = $productMap[$card->nmID] ?? null;
            if (!$productId) continue;

            if (isset($card->sizes) && is_array($card->sizes)) {
                foreach ($card->sizes as $size) {
                    if (!empty($size->skus)) {
                        foreach ($size->skus as $barcode) {
                            $skusData[] = [
                                'barcode' => $barcode,
                                'product_id' => $productId,
                                'tech_size' => $size->techSize ?? $size->wbSize ?? '-',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }
            }
        }

        // 5. Вставляем ВСЕ SKU ОДНИМ запросом
        if (!empty($skusData)) {
            $this->line("   -> Массовое сохранение " . count($skusData) . " SKU...");
            // Разбиваем на чанки по 1000 шт, чтобы не упереться в лимит параметров PostgreSQL
            foreach (array_chunk($skusData, 1000) as $chunk) {
                Sku::upsert(
                    $chunk,
                    ['barcode'], // Уникальное поле
                    ['product_id', 'tech_size', 'updated_at'] // Поля для обновления
                );
            }
        }
        
        $this->info("   ✅ Пачка закоммичена моментально!");
    }
}