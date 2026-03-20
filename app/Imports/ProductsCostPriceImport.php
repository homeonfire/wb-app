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
            // Безопасно получаем значения. Если столбца нет, вернется null
            $nmId = $row[0] ?? null;       // Столбец A (индекс 0)
            $costPrice = $row[3] ?? null;  // Столбец D (индекс 3)

            // Пропускаем строку, если нет артикула, он не числовой, ИЛИ если себестоимость не указана
            if (empty($nmId) || !is_numeric($nmId) || $costPrice === null || $costPrice === '') {
                continue;
            }

            // Очищаем цену (заменяем запятую на точку и приводим к числу)
            $cleanCostPrice = (float) str_replace(',', '.', (string) $costPrice);

            // Обновляем товар в базе
            Product::where('nm_id', $nmId)->update([
                'cost_price' => $cleanCostPrice,
            ]);
        }
    }
    
    public function startRow(): int
    {
        return 2; // Пропускаем строку с заголовками
    }
}