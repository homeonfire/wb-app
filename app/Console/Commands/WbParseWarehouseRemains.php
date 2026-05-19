<?php

namespace App\Console\Commands;

use App\Models\Sku;
use App\Models\Store;
use App\Models\SkuWarehouseStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WbParseWarehouseRemains extends Command
{
    protected $signature = 'wb:parse-remains {store_id} {task_id}';
    protected $description = 'Парсинг агрегированных остатков по SKU без разбивки по городам';

    public function handle()
    {
        $storeId = $this->argument('store_id');
        $taskId = $this->argument('task_id');
        $store = Store::findOrFail($storeId);

        $fileName = "wb_reports/remains_{$taskId}.json";
        
        if (!Storage::disk('local')->exists($fileName)) {
            $this->error("❌ Файл отчета не найден.");
            return;
        }

        $this->info("📖 Чтение файла отчета...");
        $jsonContent = Storage::disk('local')->get($fileName);
        $data = json_decode($jsonContent, true);
        $items = $data['data'] ?? $data ?? [];

        if (empty($items)) {
            $this->error("❌ Файл пустой.");
            return;
        }

        $this->info("🔍 Загрузка сопоставлений баркодов...");
        $skusMap = Sku::pluck('id', 'barcode')->toArray();

        $upsertData = [];
        $now = now();
        $skippedCount = 0;

        $this->info("🔄 Агрегация остатков...");

        foreach ($items as $item) {
            $barcode = $item['barcode'] ?? null;
            if (!$barcode) continue;

            $skuId = $skusMap[$barcode] ?? null;
            if (!$skuId) {
                $skippedCount++;
                continue;
            }

            $warehouses = $item['warehouses'] ?? [];

            // Базовые нули для текущего SKU
            $quantity = 0;
            $inWayToClient = 0;
            $inWayFromClient = 0;

            // Вытаскиваем только 3 нужные нам метрики
            foreach ($warehouses as $wh) {
                $whName = $wh['warehouseName'] ?? '';
                $rawQty = $wh['quantity'] ?? 0;

                if ($whName === 'Всего находится на складах') {
                    $quantity = $rawQty;
                } elseif ($whName === 'В пути до получателей') {
                    $inWayToClient = $rawQty;
                } elseif ($whName === 'В пути возвраты на склад WB') {
                    $inWayFromClient = $rawQty;
                }
            }

            // Формируем ОДНУ строку для этого SKU
            $upsertData[] = [
                'sku_id'             => $skuId,
                'warehouse_name'     => null, // Как заказывал — пишем null
                'quantity'           => $quantity,
                'in_way_to_client'   => $inWayToClient,
                'in_way_from_client' => $inWayFromClient,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        if (empty($upsertData)) {
            $this->warn("ℹ️ Нет данных для записи.");
            return;
        }

        $this->line("💾 Запись в базу данных " . count($upsertData) . " SKU...");

        // Очищаем старые остатки для этого магазина перед записью новых
        $storeSkuIds = Sku::whereHas('product', function ($query) use ($storeId) {
            $query->where('store_id', $storeId);
        })->pluck('id')->toArray();

        if (!empty($storeSkuIds)) {
            DB::table('sku_warehouse_stocks')->whereIn('sku_id', $storeSkuIds)->delete();
        }

        // Заливаем данные с обновленным ключом конфликта по 'sku_id'
        foreach (array_chunk($upsertData, 1000) as $chunk) {
            SkuWarehouseStock::upsert(
                $chunk,
                ['sku_id'], // Уникальный ключ теперь только sku_id
                ['quantity', 'in_way_to_client', 'in_way_from_client', 'updated_at']
            );
        }

        $this->info("🎉 Все остатки успешно схлопнуты и записаны!");
        if ($skippedCount > 0) {
            $this->warn("⚠️ Пропущено баркодов (нет в базе): {$skippedCount}");
        }
    }
}