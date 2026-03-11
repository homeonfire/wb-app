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
    protected $storeId;

    public function __construct($storeId)
    {
        $this->storeId = $storeId;
        $this->startDate = Carbon::now()->subDays(14);

        // Получаем массив баркодов ТОЛЬКО для текущего магазина (для жесткой оптимизации)
        $barcodes = DB::table('skus')
            ->join('products', 'skus.product_id', '=', 'products.id')
            ->where('products.store_id', $this->storeId)
            ->whereNotNull('skus.barcode')
            ->pluck('skus.barcode')
            ->toArray();

        // 1. Считаем заказы только по нашим баркодам
        $this->ordersStats = DB::table('order_raws')
            ->where('order_date', '>=', $this->startDate)
            ->whereIn('barcode', $barcodes)
            ->select('barcode', DB::raw('COUNT(*) as count_orders'))
            ->groupBy('barcode')
            ->pluck('count_orders', 'barcode'); 

        // 2. Считаем продажи и среднюю цену только по нашим баркодам
        $this->salesStats = DB::table('sale_raws')
            ->where('sale_date', '>=', $this->startDate)
            ->whereIn('barcode', $barcodes)
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
        // Выгружаем товары СТРОГО текущего магазина
        return Product::where('store_id', $this->storeId)
            ->with(['skus.stock', 'skus.warehouseStocks'])
            ->get();
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
            'В пути WB',
            'На WB',
            'К клиенту',
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

            $ordersCount = $this->ordersStats->get($barcode) ?? 0;
            
            $saleData = $this->salesStats->get($barcode);
            $salesCount = $saleData ? ($saleData->count_sales ?? 0) : 0;
            $avgPrice = $saleData ? ($saleData->avg_price ?? 0) : 0;

            $buyoutPercent = $ordersCount > 0 
                ? round(($salesCount / $ordersCount) * 100, 1) . '%' 
                : '0%';

            $atFactory = $sku->stock?->at_factory ?? 0;
            $inTransitGeneral = $sku->stock?->in_transit_general ?? 0;
            $stockOwn = $sku->stock?->stock_own ?? 0;
            $inTransitToWb = $sku->stock?->in_transit_to_wb ?? 0;

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