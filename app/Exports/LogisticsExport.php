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
    protected $funnelStats;
    protected $storeId;

    public function __construct($storeId)
    {
        $this->storeId = $storeId;
        $this->startDate = Carbon::now()->subDays(14);

        // 1. Получаем массив баркодов для текущего магазина
        $barcodes = DB::table('skus')
            ->join('products', 'skus.product_id', '=', 'products.id')
            ->where('products.store_id', $this->storeId)
            ->whereNotNull('skus.barcode')
            ->pluck('skus.barcode')
            ->toArray();

        // 2. Фактические заказы по штрихкодам
        $this->ordersStats = DB::table('order_raws')
            ->where('order_date', '>=', $this->startDate)
            ->whereIn('barcode', $barcodes)
            ->select('barcode', DB::raw('COUNT(*) as count_orders'))
            ->groupBy('barcode')
            ->pluck('count_orders', 'barcode'); 

        // 3. Фактические продажи по штрихкодам
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

        // 4. НОВОЕ: Данные воронки (Аналитика) за 14 дней. Группируем по nm_id (артикулу WB)
        $this->funnelStats = DB::table('product_analytics')
            ->where('store_id', $this->storeId)
            ->where('date', '>=', $this->startDate)
            ->select(
                'nm_id',
                DB::raw('SUM(open_card_count) as sum_open'),
                DB::raw('SUM(add_to_cart_count) as sum_cart'),
                DB::raw('SUM(orders_count) as sum_orders'),
                DB::raw('SUM(cancel_count) as sum_cancel')
            )
            ->groupBy('nm_id')
            ->get()
            ->keyBy('nm_id');
    }

    public function collection()
    {
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
            'Заказали факт (14д)',
            'Выкупили факт (14д)',
            'Процент выкупа факт',
            'Средняя цена',
            
            // НОВЫЕ СТОЛБЦЫ
            'Открыли карточку (14д)',
            'В корзину (14д)',
            'Заказы аналитика (14д)',
            'Отмены (14д)',
            'Конверсия в корзину',
            'Конверсия в заказ',
        ];
    }

    /**
    * @param Product $product
    */
    public function map($product): array
    {
        $rows = [];

        // Получаем воронку для всего товара (nm_id)
        $funnel = $this->funnelStats->get($product->nm_id);
        $sumOpen = $funnel->sum_open ?? 0;
        $sumCart = $funnel->sum_cart ?? 0;
        $sumOrdersAnalytics = $funnel->sum_orders ?? 0;
        $sumCancel = $funnel->sum_cancel ?? 0;

        // Высчитываем реальные средние конверсии за 2 недели
        $convToCart = $sumOpen > 0 ? round(($sumCart / $sumOpen) * 100, 1) . '%' : '0%';
        $convToOrder = $sumCart > 0 ? round(($sumOrdersAnalytics / $sumCart) * 100, 1) . '%' : '0%';

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
                
                // Выводим воронку (одинаковая для всех размеров одного артикула)
                $sumOpen,
                $sumCart,
                $sumOrdersAnalytics,
                $sumCancel,
                $convToCart,
                $convToOrder,
            ];
        }
        return $rows;
    }
}