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
            $nmId = $row[0];       // Столбец A (Артикул WB)
            $costPrice = $row[3];  // Столбец D (Себестоимость)

            if (empty($nmId) || !is_numeric($nmId)) {
                continue;
            }

            $cleanCostPrice = (float) str_replace(',', '.', $costPrice);

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