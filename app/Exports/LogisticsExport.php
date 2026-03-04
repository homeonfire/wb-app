<?php

namespace App\Exports;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LogisticsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $ordersStats;
    protected $salesStats;

    public function __construct()
    {
        $this->startDate = Carbon::now()->subDays(14);

        // 1. Считаем заказы сразу в БД (возвращает простой массив: ['штрихкод' => количество])
        $this->ordersStats = DB::table('order_raws')
            ->where('order_date', '>=', $this->startDate)
            ->select('barcode', DB::raw('COUNT(*) as count_orders'))
            ->groupBy('barcode')
            ->pluck('count_orders', 'barcode'); // pluck создает ассоциативный массив для мгновенного поиска

        // 2. Считаем продажи и среднюю цену в БД (возвращает коллекцию объектов с ключами по штрихкоду)
        $this->salesStats = DB::table('sale_raws')
            ->where('sale_date', '>=', $this->startDate)
            ->select(
                'barcode', 
                DB::raw('COUNT(*) as count_sales'), 
                DB::raw('AVG(finished_price) as avg_price')
            )
            ->groupBy('barcode')
            ->get()
            ->keyBy('barcode'); // keyBy позволяет обращаться к данным со скоростью O(1)
    }

    public function collection()
    {
        // Убрали загрузку orders и sales из with()! Теперь грузим только легкие остатки.
        return Product::with(['skus.stock', 'skus.warehouseStocks'])->get();
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
            $barcode = $sku->barcode;

            // Мгновенно достаем предрассчитанные данные из массивов
            $ordersCount = $this->ordersStats->get($barcode) ?? 0;
            
            $saleData = $this->salesStats->get($barcode);
            $salesCount = $saleData ? $saleData->count_sales : 0;
            $avgPrice = $saleData ? $saleData->avg_price : 0;

            // Считаем процент выкупа
            $buyoutPercent = $ordersCount > 0 
                ? round(($salesCount / $ordersCount) * 100, 1) . '%' 
                : '0%';

            $rows[] = [
                $barcode,
                $product->title,
                $product->vendor_code,
                $sku->stock?->at_factory ?? 0,
                $sku->stock?->in_transit_general ?? 0,
                $sku->stock?->stock_own ?? 0,
                $sku->warehouseStocks->sum('quantity'), // Остатки на FBO грузятся быстро, их оставляем
                $ordersCount,
                $salesCount,
                $buyoutPercent,
                round($avgPrice, 2),
            ];
        }
        return $rows;
    }
}