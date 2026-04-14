<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\OrderRaw;
use App\Models\SaleRaw;
use App\Models\ProductAnalytic; // <-- Подключаем модель воронки
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class ProductSalesChart extends ChartWidget
{
    protected static ?string $heading = 'Динамика продаж и заказов';
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '300px';
    
    public ?Model $record = null;

    protected function getData(): array
    {
        if (!$this->record) return [];

        $start = now()->subDays(30);
        $end = now();

        // 1. Выручка (ФАКТИЧЕСКАЯ из SaleRaw - реальные деньги)
        $revenue = Trend::query(SaleRaw::where('nm_id', $this->record->nm_id))
            ->dateColumn('sale_date')
            ->between($start, $end)
            ->perDay()
            ->sum('price_with_disc');

        // 2. Сумма заказов (ФАКТИЧЕСКАЯ из OrderRaw - на какую сумму заказали)
        $ordersSum = Trend::query(OrderRaw::where('nm_id', $this->record->nm_id)->where('is_cancel', false))
            ->dateColumn('order_date')
            ->between($start, $end)
            ->perDay()
            ->sum('total_price');

        // 3. Кол-во выкупов (ИЗ ВОРОНКИ - сглаженные данные)
        $salesCount = Trend::query(ProductAnalytic::where('nm_id', $this->record->nm_id))
            ->dateColumn('date')
            ->between($start, $end)
            ->perDay()
            ->sum('buyouts_count'); // В Trend используем sum(), так как берем значение из колонки

        // 4. Кол-во заказов (ИЗ ВОРОНКИ - сглаженные данные)
        $ordersCount = Trend::query(ProductAnalytic::where('nm_id', $this->record->nm_id))
            ->dateColumn('date')
            ->between($start, $end)
            ->perDay()
            ->sum('orders_count');

        // 5. Высчитываем корректный % выкупа по воронке
        $buyoutPercents = [];
        foreach ($ordersCount as $key => $order) {
            $orderAgg = $order->aggregate;
            $saleAgg = $salesCount[$key]->aggregate ?? 0;
            
            $percent = $orderAgg > 0 ? round(($saleAgg / $orderAgg) * 100, 2) : 0;
            
            // Срезаем аномалии WB, если они проскакивают в воронке
            if ($percent > 100) $percent = 100;

            $buyoutPercents[] = $percent;
        }

        return [
            'datasets' => [
                // --- ЛЕВАЯ ШКАЛА (ДЕНЬГИ - ФАКТ) ---
                [
                    'label' => 'Выручка факт (₽)',
                    'data' => $revenue->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10b981', // Зеленый
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'yAxisID' => 'y',
                    'order' => 3, 
                ],
                [
                    'label' => 'Заказы факт (₽)',
                    'data' => $ordersSum->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#3b82f6', // Синий
                    'borderDash' => [5, 5], 
                    'fill' => false,
                    'yAxisID' => 'y',
                    'order' => 4,
                ],

                // --- ПРАВАЯ ШКАЛА 1 (ШТУКИ - ВОРОНКА) ---
                [
                    'label' => 'Выкупы воронка (шт)',
                    'data' => $salesCount->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#f59e0b', // Оранжевый
                    'backgroundColor' => '#f59e0b',
                    'type' => 'bar', 
                    'yAxisID' => 'y1', 
                    'barPercentage' => 0.5,
                    'order' => 2,
                ],
                [
                    'label' => 'Заказы воронка (шт)',
                    'data' => $ordersCount->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#8b5cf6', // Фиолетовый
                    'borderWidth' => 2,
                    'pointRadius' => 4, 
                    'fill' => false,
                    'yAxisID' => 'y1',
                    'order' => 1, 
                ],

                // --- ПРАВАЯ ШКАЛА 2 (ПРОЦЕНТЫ - ВОРОНКА) ---
                [
                    'label' => '% выкупа (воронка)',
                    'data' => $buyoutPercents,
                    'borderColor' => '#ec4899', // Розовый
                    'backgroundColor' => '#ec4899',
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                    'fill' => false,
                    'yAxisID' => 'y2',
                    'order' => 0,
                ],
            ],
            'labels' => $revenue->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Рубли (₽)',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Штуки (шт)',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false, 
                    ],
                ],
                'y2' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Процент (%)',
                    ],
                    'min' => 0, 
                    'max' => 100, 
                    'grid' => [
                        'drawOnChartArea' => false, 
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}