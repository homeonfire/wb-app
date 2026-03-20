<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ProductsAssignManagerImport implements ToCollection, WithStartRow
{
    protected $userId;

    // Получаем ID менеджера при запуске импорта
    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function collection(Collection $rows)
    {
        Log::info("=== НАЧАЛО ПРИВЯЗКИ ТОВАРОВ К МЕНЕДЖЕРУ ID: {$this->userId} ===");

        foreach ($rows as $index => $row) {
            // Столбец B — это индекс 1 (A=0, B=1)
            $nmId = $row[1] ?? null; 

            if (empty($nmId) || !is_numeric($nmId)) {
                continue;
            }

            // Пытаемся найти товар в базе
            $product = Product::where('nm_id', $nmId)->first();

            if ($product) {
                // Привязываем менеджера к товару
                // Метод syncWithoutDetaching добавляет связь, не удаляя других менеджеров (если они уже привязаны к этому товару)
                $product->users()->syncWithoutDetaching([$this->userId]);
                
                Log::info("Товар nm_id: {$nmId} успешно привязан к менеджеру {$this->userId}");
            } else {
                Log::warning("Товар nm_id: {$nmId} не найден в базе данных.");
            }
        }
        
        Log::info("=== КОНЕЦ ПРИВЯЗКИ ===");
    }

    public function startRow(): int
    {
        return 2; // Пропускаем строку с заголовками
    }
}