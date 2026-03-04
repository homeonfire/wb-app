<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class LogisticsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;

    public function __construct()
    {
        // Устанавливаем дату начала — 14 дней назад
        $this->startDate = Carbon::now()->subDays(14);
    }

    public function collection()
    {
        // Загружаем продукты с необходимыми связями
        return Product::with(['skus.stock', 'skus.warehouseStocks', 'skus.orders', 'skus.sales'])->get();
    }

    public function headings(): array
    {
        return [
            'Баркод',
            'Товар',
            'Артикул',
            'Завод',
            'Карго',
            'Склад',
            'На WB',
            'Заказали (14д)',
            'Выкупили (14д)',
            'Процент выкупа',
            'Средняя цена',
        ];
    }

    /**
    * @param Product $product
    */
    public function map($product): array
    {
        $rows = [];
        foreach ($product->skus as $sku) {
            // Считаем заказы за последние 2 недели
            $ordersCount = $sku->orders()
                ->where('order_date', '>=', $this->startDate)
                ->count();

            // Считаем выкупы (продажи) за последние 2 недели
            $sales = $sku->sales()
                ->where('sale_date', '>=', $this->startDate)
                ->get();

            $salesCount = $sales->count();

            // Считаем среднюю цену продажи
            $avgPrice = $salesCount > 0 ? $sales->avg('finished_price') : 0;

            // Считаем процент выкупа
            $buyoutPercent = $ordersCount > 0 
                ? round(($salesCount / $ordersCount) * 100, 1) . '%' 
                : '0%';

            $rows[] = [
                $sku->barcode,
                $product->title,
                $product->vendor_code,
                $sku->stock?->at_factory ?? 0,
                $sku->stock?->in_transit_general ?? 0,
                $sku->stock?->stock_own ?? 0,
                $sku->warehouseStocks->sum('quantity'),
                $ordersCount,
                $salesCount,
                $buyoutPercent,
                round($avgPrice, 2),
            ];
        }
        return $rows;
    }
}