<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log; // Обязательно добавляем фасад логов
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ProductsCostPriceImport implements ToCollection, WithStartRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $nmId = $row[0] ?? null;       // Столбец A (Артикул)
            $basePrice = $row[1] ?? null;  // Столбец B (Базовая цена)

            // Проверяем, есть ли артикул и базовая цена
            if (empty($nmId) || !is_numeric($nmId) || $basePrice === null || $basePrice === '') {
                continue;
            }

            // --- ОЧИСТКА БАЗОВОЙ ЦЕНЫ ИЗ СТОЛБЦА B ---
            $priceStr = (string) $basePrice;
            $priceStr = str_replace(',', '.', $priceStr);
            $priceStr = preg_replace('/[^0-9.]/', '', $priceStr);
            
            $cleanBasePrice = (float) $priceStr;
            
            // --- ДОБАВЛЯЕМ 300 ---
            $finalCostPrice = $cleanBasePrice;
            // --------------------------------

            // Пытаемся найти товар в базе
            $product = Product::where('nm_id', $nmId)->first();

            if ($product) {
                // Обновляем товар новой итоговой ценой
                $product->update([
                    'cost_price' => $finalCostPrice,
                ]);
                
                \Log::info("Успешно обновлен nm_id: {$nmId}. Цена из табл: {$cleanBasePrice} + 300 = Итоговая: {$finalCostPrice}");
            }
        }
    }

    public function startRow(): int
    {
        return 2;
    }
}