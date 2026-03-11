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
    protected $startDate14;
    protected $startDate30;
    protected $ordersStats14;
    protected $salesStats14;
    protected $ordersStats30;
    protected $salesStats30;
    protected $funnelStats;
    protected $storeId;

    public function __construct($storeId)
    {
        $this->storeId = $storeId;
        $this->startDate14 = Carbon::now()->subDays(14);
        $this->startDate30 = Carbon::now()->subDays(30);

        $barcodes = DB::table('skus')
            ->join('products', 'skus.product_id', '=', 'products.id')
            ->where('products.store_id', $this->storeId)
            ->whereNotNull('skus.barcode')
            ->pluck('skus.barcode')
            ->toArray();

        $this->ordersStats14 = DB::table('order_raws')
            ->where('order_date', '>=', $this->startDate14)
            ->whereIn('barcode', $barcodes)
            ->select('barcode', DB::raw('COUNT(*) as count_orders'))
            ->groupBy('barcode')
            ->pluck('count_orders', 'barcode'); 

        $this->salesStats14 = DB::table('sale_raws')
            ->where('sale_date', '>=', $this->startDate14)
            ->whereIn('barcode', $barcodes)
            ->select(
                'barcode', 
                DB::raw('COUNT(*) as count_sales'), 
                DB::raw('AVG(finished_price) as avg_price')
            )
            ->groupBy('barcode')
            ->get()
            ->keyBy('barcode'); 

        $this->ordersStats30 = DB::table('order_raws')
            ->where('order_date', '>=', $this->startDate30)
            ->whereIn('barcode', $barcodes)
            ->select('barcode', DB::raw('COUNT(*) as count_orders'))
            ->groupBy('barcode')
            ->pluck('count_orders', 'barcode');

        $this->salesStats30 = DB::table('sale_raws')
            ->where('sale_date', '>=', $this->startDate30)
            ->whereIn('barcode', $barcodes)
            ->select('barcode', DB::raw('COUNT(*) as count_sales'))
            ->groupBy('barcode')
            ->pluck('count_sales', 'barcode');

        // Расширяем запрос к воронке: добавляем расчет средней цены продавца
        $this->funnelStats = DB::table('product_analytics')
            ->where('store_id', $this->storeId)
            ->where('date', '>=', $this->startDate14)
            ->select(
                'nm_id',
                DB::raw('SUM(open_card_count) as sum_open'),
                DB::raw('SUM(add_to_cart_count) as sum_cart'),
                DB::raw('SUM(orders_count) as sum_orders'),
                DB::raw('SUM(cancel_count) as sum_cancel'),
                // Считаем среднюю цену из воронки (игнорируя дни с нулями)
                DB::raw('AVG(NULLIF(avg_price_rub, 0)) as avg_price_seller') 
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
            'Размер',           // <--- НОВАЯ КОЛОНКА
            'Товар',
            'Артикул продавца',
            'Артикул WB',       
            'Фабрика',             
            'В пути с фабрики',    
            'Склад',
            'В пути WB',
            'На WB',
            'К клиенту',
            'От клиента',
            'Заказали факт (14д)',
            'Выкупили факт (14д)',
            'Процент выкупа (30д)',
            'Ср. цена (факт с СПП 14д)', 
            'Ср. цена (наша без СПП 14д)', 
            
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

        // Данные воронки для всего товара
        $funnel = $this->funnelStats->get($product->nm_id);
        $sumOpen = $funnel->sum_open ?? 0;
        $sumCart = $funnel->sum_cart ?? 0;
        $sumOrdersAnalytics = $funnel->sum_orders ?? 0;
        $sumCancel = $funnel->sum_cancel ?? 0;
        $avgPriceSeller = $funnel->avg_price_seller ?? 0;

        $convToCart = $sumOpen > 0 ? round(($sumCart / $sumOpen) * 100, 1) . '%' : '0%';
        $convToOrder = $sumCart > 0 ? round(($sumOrdersAnalytics / $sumCart) * 100, 1) . '%' : '0%';

        // Добавляем $index, чтобы понимать, какой это по счету размер у товара
        foreach ($product->skus as $index => $sku) {
            $barcode = $sku->barcode ?? 'Без баркода';
            $size = $sku->tech_size ?? '-'; 

            $ordersCount14 = $this->ordersStats14->get($barcode) ?? 0;
            $saleData14 = $this->salesStats14->get($barcode);
            $salesCount14 = $saleData14 ? ($saleData14->count_sales ?? 0) : 0;
            $avgPriceFact = $saleData14 ? ($saleData14->avg_price ?? 0) : 0; 

            $ordersCount30 = $this->ordersStats30->get($barcode) ?? 0;
            $salesCount30 = $this->salesStats30->get($barcode) ?? 0;

            $buyoutPercent30 = $ordersCount30 > 0 
                ? round(($salesCount30 / $ordersCount30) * 100, 1) . '%' 
                : '0%';

            $atFactory = $sku->stock?->at_factory ?? 0;
            $inTransitGeneral = $sku->stock?->in_transit_general ?? 0;
            $stockOwn = $sku->stock?->stock_own ?? 0;
            $inTransitToWb = $sku->stock?->in_transit_to_wb ?? 0;

            $onWb = $sku->warehouseStocks ? $sku->warehouseStocks->sum('quantity') : 0;
            $toClient = $sku->warehouseStocks ? $sku->warehouseStocks->sum('in_way_to_client') : 0;
            $fromClient = $sku->warehouseStocks ? $sku->warehouseStocks->sum('in_way_from_client') : 0;

            // Флаг: true, если это ПЕРВЫЙ размер данного артикула
            $isFirstSku = ($index === 0);

            $rows[] = [
                $barcode,
                $size,
                $product->title ?? '-',
                $product->vendor_code ?? '-',
                $product->nm_id ?? '-', 
                $atFactory,
                $inTransitGeneral,
                $stockOwn,
                $inTransitToWb,
                $onWb,
                $toClient,
                $fromClient,
                $ordersCount14,
                $salesCount14,
                $buyoutPercent30, 
                round($avgPriceFact, 2), 
                
                // --- НИЖЕ ВЫВОДИМ ДАННЫЕ ТОЛЬКО ДЛЯ ПЕРВОЙ СТРОКИ ---
                // Если не первая строка, выводим пустую строку ('')
                $isFirstSku ? round($avgPriceSeller, 2) : '', 
                $isFirstSku ? $sumOpen : '',
                $isFirstSku ? $sumCart : '',
                $isFirstSku ? $sumOrdersAnalytics : '',
                $isFirstSku ? $sumCancel : '',
                $isFirstSku ? $convToCart : '',
                $isFirstSku ? $convToOrder : '',
            ];
        }
        return $rows;
    }
}