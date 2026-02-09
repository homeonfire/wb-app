<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LogisticsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        // Загружаем продукты со всеми нужными связями для скорости
        return Product::with(['skus.stock', 'skus.warehouseStocks'])->get();
    }

    public function headings(): array
    {
        return [
            'Баркод',
            'Товар',
            'Бренд',
            'Артикул',
            'Завод',
            'Карго',
            'Склад',
            'В пути WB',
            'На WB',
            'К клиенту',
        ];
    }

    /**
    * @var Product $product
    */
    public function map($product): array
    {
        $rows = [];
        foreach ($product->skus as $sku) {
            $rows[] = [
                $sku->barcode, // Главная графа
                $product->title,
                $product->brand,
                $product->vendor_code,
                $sku->stock?->at_factory ?? 0,
                $sku->stock?->in_transit_general ?? 0,
                $sku->stock?->stock_own ?? 0,
                $sku->stock?->in_transit_to_wb ?? 0,
                $sku->warehouseStocks->sum('quantity'),
                $sku->warehouseStocks->sum('in_way_to_client'),
            ];
        }
        return $rows;
    }
}