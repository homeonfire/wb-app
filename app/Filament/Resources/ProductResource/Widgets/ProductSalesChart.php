<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\OrderRaw;
use App\Models\SaleRaw;
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

        // 1. Выручка (Продажи в рублях)
        $revenue = Trend::query(SaleRaw::where('nm_id', $this->record->nm_id))
            ->dateColumn('sale_date')
            ->between($start, $end)
            ->perDay()
            ->sum('price_with_disc');

        // 2. Сумма заказов (Заказы в рублях)
        $ordersSum = Trend::query(OrderRaw::where('nm_id', $this->record->nm_id)->where('is_cancel', false))
            ->dateColumn('order_date')
            ->between($start, $end)
            ->perDay()
            ->sum('total_price');

        // 3. Кол-во выкупов (Штуки)
        $salesCount = Trend::query(SaleRaw::where('nm_id', $this->record->nm_id))
            ->dateColumn('sale_date')
            ->between($start, $end)
            ->perDay()
            ->count();

        // 4. Кол-во заказов (Штуки)
        $ordersCount = Trend::query(OrderRaw::where('nm_id', $this->record->nm_id)->where('is_cancel', false))
            ->dateColumn('order_date')
            ->between($start, $end)
            ->perDay()
            ->count();

        return [
            'datasets' => [
                // --- ЛЕВАЯ ШКАЛА (ДЕНЬГИ) ---
                [
                    'label' => 'Выручка (₽)',
                    'data' => $revenue->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10b981', // Зеленый
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'yAxisID' => 'y', // Привязка к левой оси
                    'order' => 2, // Слои (чтобы не перекрывало)
                ],
                [
                    'label' => 'Сумма заказов (₽)',
                    'data' => $ordersSum->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#3b82f6', // Синий
                    'borderDash' => [5, 5], // Пунктирная линия
                    'fill' => false,
                    'yAxisID' => 'y',
                    'order' => 3,
                ],

                // --- ПРАВАЯ ШКАЛА (ШТУКИ) ---
                [
                    'label' => 'Выкупы (шт)',
                    'data' => $salesCount->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#f59e0b', // Оранжевый
                    'backgroundColor' => '#f59e0b',
                    'type' => 'bar', // Столбики для штук нагляднее
                    'yAxisID' => 'y1', // Привязка к правой оси
                    'barPercentage' => 0.5,
                    'order' => 1,
                ],
                [
                    'label' => 'Заказы (шт)',
                    'data' => $ordersCount->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#8b5cf6', // Фиолетовый
                    'borderWidth' => 2,
                    'pointRadius' => 4, // Точки пожирнее
                    'fill' => false,
                    'yAxisID' => 'y1',
                    'order' => 0, // Поверх всего
                ],
            ],
            'labels' => $revenue->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line'; // Базовый тип (мы миксуем его с bar внутри datasets)
    }

    // Настройка осей (Левая - Рубли, Правая - Штуки)
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
                        'drawOnChartArea' => false, // Убираем сетку для правой оси, чтобы не рябило
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