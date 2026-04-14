<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\OrderRaw;
use App\Models\SaleRaw;
use App\Models\ProductAnalytic;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class ProductSalesChart extends ChartWidget
{
    protected static ?string $heading = 'Динамика продаж и заказов';
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '320px'; // Чуть увеличили высоту для "воздуха"
    
    public ?Model $record = null;

    protected function getData(): array
    {
        if (!$this->record) return [];

        $start = now()->subDays(30);
        $end = now();

        $revenue = Trend::query(SaleRaw::where('nm_id', $this->record->nm_id))
            ->dateColumn('sale_date')
            ->between($start, $end)
            ->perDay()
            ->sum('price_with_disc');

        $ordersSum = Trend::query(OrderRaw::where('nm_id', $this->record->nm_id)->where('is_cancel', false))
            ->dateColumn('order_date')
            ->between($start, $end)
            ->perDay()
            ->sum('total_price');

        $funnelSalesCount = Trend::query(ProductAnalytic::where('nm_id', $this->record->nm_id))
            ->dateColumn('date')
            ->between($start, $end)
            ->perDay()
            ->sum('buyouts_count');

        $funnelOrdersCount = Trend::query(ProductAnalytic::where('nm_id', $this->record->nm_id))
            ->dateColumn('date')
            ->between($start, $end)
            ->perDay()
            ->sum('orders_count');

        $buyoutPercents = [];
        foreach ($funnelOrdersCount as $key => $order) {
            $orderAgg = $order->aggregate;
            $saleAgg = $funnelSalesCount[$key]->aggregate ?? 0;
            $percent = $orderAgg > 0 ? round(($saleAgg / $orderAgg) * 100, 2) : 0;
            if ($percent > 100) $percent = 100;
            $buyoutPercents[] = $percent;
        }

        return [
            'datasets' => [
                // --- ЛЕВАЯ ШКАЛА (ДЕНЬГИ) ---
                [
                    'label' => 'Выручка факт (₽)',
                    'data' => $revenue->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10b981', // Зеленый
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4, // Плавная линия
                    'pointRadius' => 0, // Прячем точки
                    'pointHoverRadius' => 6, // Показываем при наведении
                    'pointHitRadius' => 15,
                    'yAxisID' => 'y',
                    'order' => 3, 
                ],
                [
                    'label' => 'Заказы факт (₽)',
                    'data' => $ordersSum->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#3b82f6', // Синий
                    'borderDash' => [5, 5], 
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 6,
                    'hidden' => true, // <-- СКРЫВАЕМ ПО УМОЛЧАНИЮ (чтобы не мешало)
                    'yAxisID' => 'y',
                    'order' => 4,
                ],

                // --- ПРАВАЯ ШКАЛА 1 (ШТУКИ) ---
                [
                    'label' => 'Выкупы воронка (шт)',
                    'data' => $funnelSalesCount->map(fn (TrendValue $value) => $value->aggregate),
                    'backgroundColor' => '#f59e0b', // Оранжевый
                    'type' => 'bar', 
                    'yAxisID' => 'y1', 
                    'barPercentage' => 0.4, // Делаем столбики тоньше
                    'borderRadius' => 4, // Слегка закругляем верхушки столбиков
                    'order' => 2,
                ],
                [
                    'label' => 'Заказы воронка (шт)',
                    'data' => $funnelOrdersCount->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#8b5cf6', // Фиолетовый
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4, // Плавная линия
                    'pointRadius' => 0, // Прячем точки
                    'pointHoverRadius' => 6,
                    'yAxisID' => 'y1',
                    'order' => 1, 
                ],

                // --- ПРАВАЯ ШКАЛА 2 (ПРОЦЕНТЫ) ---
                [
                    'label' => '% выкупа (воронка)',
                    'data' => $buyoutPercents,
                    'borderColor' => '#ec4899', // Розовый
                    'backgroundColor' => '#ec4899',
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4, // Плавная линия
                    'pointRadius' => 0, // Прячем точки
                    'pointHoverRadius' => 6,
                    'yAxisID' => 'y2',
                    'order' => 0,
                ],
            ],
            'labels' => $revenue->map(fn (TrendValue $value) => \Carbon\Carbon::parse($value->date)->format('d.m')), // Короткая дата: 15.04
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            // Настройки идеального тултипа (показывает все данные за день при наведении)
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Рубли (₽)',
                        'color' => '#10b981', // Красим текст оси в цвет выручки для понятности
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Штуки (шт)',
                        'color' => '#8b5cf6',
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
                        'color' => '#ec4899',
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