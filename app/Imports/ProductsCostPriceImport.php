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
        Log::info('=== НАЧАЛО ИМПОРТА СЕБЕСТОИМОСТИ ===');
        Log::info('Всего строк получено из Excel (без учета заголовка): ' . $rows->count());

        foreach ($rows as $index => $row) {
            // Вычисляем реальный номер строки в Excel для удобства дебага
            // $index начинается с 0, плюс мы пропускаем 1-ю строку заголовков
            $excelRowNumber = $index + 2; 

            $nmId = $row[0] ?? null;       // Столбец A
            $costPrice = $row[3] ?? null;  // Столбец D

            Log::info("Строка Excel {$excelRowNumber}: Прочитано [nm_id => '{$nmId}', cost_price => '{$costPrice}']");

            // Проверка 1: Есть ли артикул и число ли это?
            if (empty($nmId) || !is_numeric($nmId)) {
                Log::warning("  -> Строка {$excelRowNumber} ПРОПУЩЕНА: Артикул WB пуст или содержит буквы.");
                continue;
            }

            // Проверка 2: Есть ли себестоимость?
            if ($costPrice === null || $costPrice === '') {
                Log::warning("  -> Строка {$excelRowNumber} ПРОПУЩЕНА: Себестоимость пустая.");
                continue;
            }

            // Очищаем цену (заменяем запятую на точку и приводим к числу)
            $cleanCostPrice = (float) str_replace(',', '.', (string) $costPrice);

            // Пытаемся найти товар в базе
            $product = Product::where('nm_id', $nmId)->first();

            if ($product) {
                $oldPrice = $product->cost_price;
                
                // Обновляем товар
                $product->update([
                    'cost_price' => $cleanCostPrice,
                ]);
                
                Log::info("  -> УСПЕХ: Товар (ID: {$product->id}) обновлен. Цена: {$oldPrice} -> {$cleanCostPrice}");
            } else {
                // Если товара с таким nm_id нет в нашей базе
                Log::error("  -> ОШИБКА: Товар с артикулом WB '{$nmId}' НЕ НАЙДЕН в базе данных.");
            }
        }

        Log::info('=== КОНЕЦ ИМПОРТА СЕБЕСТОИМОСТИ ===');
    }

    public function startRow(): int
    {
        return 2;
    }
}