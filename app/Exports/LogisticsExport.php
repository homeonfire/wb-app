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

        // 1. Считаем заказы сразу в БД
        $this->ordersStats = DB::table('order_raws')
            ->where('order_date', '>=', $this->startDate)
            ->select('barcode', DB::raw('COUNT(*) as count_orders'))
            ->groupBy('barcode')
            ->pluck('count_orders', 'barcode'); 

        // 2. Считаем продажи и среднюю цену в БД
        $this->salesStats = DB::table('sale_raws')
            ->where('sale_date', '>=', $this->startDate)
            ->select(
                'barcode', 
                DB::raw('COUNT(*) as count_sales'), 
                DB::raw('AVG(finished_price) as avg_price')
            )
            ->groupBy('barcode')
            ->get()
            ->keyBy('barcode'); 
    }

    public function collection()
    {
        // Подгружаем только нужные связи остатков
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
            'В пути WB',      // Вернули графу
            'На WB',
            'К клиенту',      // Новая графа
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
            $barcode = $sku->barcode ?? 'Без баркода';

            // Мгновенно достаем предрассчитанные данные из массивов с фоллбеком на 0
            $ordersCount = $this->ordersStats->get($barcode) ?? 0;
            
            $saleData = $this->salesStats->get($barcode);
            $salesCount = $saleData ? ($saleData->count_sales ?? 0) : 0;
            $avgPrice = $saleData ? ($saleData->avg_price ?? 0) : 0;

            // Считаем процент выкупа
            $buyoutPercent = $ordersCount > 0 
                ? round(($salesCount / $ordersCount) * 100, 1) . '%' 
                : '0%';

            // Остатки (если связи нет или значение null — ставим 0)
            $atFactory = $sku->stock?->at_factory ?? 0;
            $inTransitGeneral = $sku->stock?->in_transit_general ?? 0;
            $stockOwn = $sku->stock?->stock_own ?? 0;
            $inTransitToWb = $sku->stock?->in_transit_to_wb ?? 0;

            // Склады WB (метод sum() у пустой коллекции сам возвращает 0, но на всякий случай проверяем саму связь)
            $onWb = $sku->warehouseStocks ? $sku->warehouseStocks->sum('quantity') : 0;
            $toClient = $sku->warehouseStocks ? $sku->warehouseStocks->sum('in_way_to_client') : 0;

            $rows[] = [
                $barcode,
                $product->title ?? '-',
                $product->vendor_code ?? '-',
                $atFactory,
                $inTransitGeneral,
                $stockOwn,
                $inTransitToWb,
                $onWb,
                $toClient,
                $ordersCount,
                $salesCount,
                $buyoutPercent,
                round($avgPrice, 2),
            ];
        }
        return $rows;
    }
}