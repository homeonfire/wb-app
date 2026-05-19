<?php

namespace App\Console\Commands;

use App\Models\Sku;
use App\Models\Store;
use App\Models\SkuWarehouseDetail; // Наша новая модель
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WbParseWarehouseDetails extends Command
{
    protected $signature = 'wb:parse-remains-details {store_id} {task_id}';
    protected $description = 'Парсинг разбивки остатков по физическим складам';

    public function handle()
    {
        $storeId = $this->argument('store_id');
        $taskId = $this->argument('task_id');
        $store = Store::findOrFail($storeId);

        $fileName = "wb_reports/remains_{$taskId}.json";
        
        if (!Storage::disk('local')->exists($fileName)) {
            $this->error("❌ Файл отчета не найден: {$fileName}");
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

        // Эти виртуальные склады мы пропускаем, так как они лежат в другой таблице
        $ignoreWarehouses = [
            'В пути до получателей',
            'В пути возвраты на склад WB',
            'Всего находится на складах'
        ];

        $this->info("🔄 Агрегация физических складов...");

        foreach ($items as $item) {
            $barcode = $item['barcode'] ?? null;
            if (!$barcode) continue;

            $skuId = $skusMap[$barcode] ?? null;
            if (!$skuId) {
                $skippedCount++;
                continue;
            }

            $warehouses = $item['warehouses'] ?? [];

            foreach ($warehouses as $wh) {
                $whName = $wh['warehouseName'] ?? '';
                $quantity = $wh['quantity'] ?? 0;

                // Пропускаем нули и виртуальные склады
                if ($quantity <= 0 || in_array($whName, $ignoreWarehouses)) {
                    continue;
                }

                $upsertData[] = [
                    'sku_id'         => $skuId,
                    'warehouse_name' => $whName,
                    'quantity'       => $quantity,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        if (empty($upsertData)) {
            $this->warn("ℹ️ Нет данных для записи (возможно везде нули).");
            return;
        }

        $this->line("💾 Запись в базу данных " . count($upsertData) . " строк складов...");

        // Очищаем старые остатки для этого магазина перед записью новых, 
        // чтобы исчезнувшие с конкретного склада товары корректно пропали из базы
        $storeSkuIds = Sku::whereHas('product', function ($query) use ($storeId) {
            $query->where('store_id', $storeId);
        })->pluck('id')->toArray();

        if (!empty($storeSkuIds)) {
            DB::table('sku_warehouse_details')->whereIn('sku_id', $storeSkuIds)->delete();
        }

        // Массовый Upsert пачками по 1000
        foreach (array_chunk($upsertData, 1000) as $chunk) {
            SkuWarehouseDetail::upsert(
                $chunk,
                ['sku_id', 'warehouse_name'], // Наш составной уникальный индекс
                ['quantity', 'updated_at']
            );
        }

        $this->info("🎉 Детализация по физическим складам успешно записана!");
        if ($skippedCount > 0) {
            $this->warn("⚠️ Пропущено баркодов (нет в базе): {$skippedCount}");
        }
    }
}