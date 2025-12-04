<?php

namespace App\Filament\Widgets;

use App\Models\OrderRaw;
use App\Models\SaleRaw;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Filament\Facades\Filament;

class DashboardSalesChart extends ChartWidget
{
    protected static ?string $heading = 'Динамика выручки и заказов (30 дней)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full'; // На всю ширину
    protected static ?string $maxHeight = '400px';

    protected function getData(): array
    {
        $store = Filament::getTenant();
        $start = now()->subDays(30);
        $end = now();

        // Данные по Выкупам (Деньги)
        $salesData = Trend::query(SaleRaw::where('store_id', $store->id)) // 👈 Добавили query(...)
    ->dateColumn('sale_date')
    ->between($start, $end)
    ->perDay()
    ->sum('price_with_disc');

$ordersData = Trend::query(OrderRaw::where('store_id', $store->id)) // 👈 Добавили query(...)
    ->dateColumn('order_date')
    ->between($start, $end)
    ->perDay()
    ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Выручка (₽)',
                    'data' => $salesData->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10b981', // Green
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Заказы (шт)',
                    'data' => $ordersData->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#6366f1', // Indigo
                    'type' => 'bar', // Столбики
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $salesData->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'position' => 'left',
                    'title' => ['display' => true, 'text' => 'Рубли'],
                ],
                'y1' => [
                    'position' => 'right',
                    'title' => ['display' => true, 'text' => 'Штуки'],
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }
}