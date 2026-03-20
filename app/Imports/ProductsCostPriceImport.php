<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ProductsCostPriceImport implements ToCollection, WithStartRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $nmId = $row[0] ?? null;       
            $costPrice = $row[3] ?? null;  

            if (empty($nmId) || $costPrice === null || $costPrice === '') {
                continue;
            }

            $cleanCostPrice = (float) str_replace(',', '.', (string) $costPrice);

            // Пытаемся найти товар
            $product = Product::where('nm_id', $nmId)->first();

            if ($product) {
                // Если нашли - обновляем
                $product->update(['cost_price' => $cleanCostPrice]);
                \Log::info("Успешно обновлен nm_id: {$nmId}. Новая цена: {$cleanCostPrice}");
            } else {
                // Если НЕ нашли - пишем в лог ошибку
                \Log::warning("Товар не найден! В Excel был nm_id: '{$nmId}'");
            }
        }
    }

    public function startRow(): int
    {
        return 2; // Пропускаем строку с заголовками
    }
}