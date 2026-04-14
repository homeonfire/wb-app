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

        // 5. Высчитываем % выкупа для каждого дня
        $buyoutPercents = [];
        foreach ($ordersCount as $key => $order) {
            $orderAgg = $order->aggregate;
            $saleAgg = $salesCount[$key]->aggregate ?? 0;
            
            // Если были заказы, считаем процент, иначе 0
            $percent = $orderAgg > 0 ? round(($saleAgg / $orderAgg) * 100, 2) : 0;
            
            // Защита от аномалий (когда выкупов в день больше, чем заказов из-за задержек доставки)
            // По желанию можно убрать эту проверку, если хочешь видеть спайки > 100%
            if ($percent > 100) $percent = 100;

            $buyoutPercents[] = $percent;
        }

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
                    'order' => 3, 
                ],
                [
                    'label' => 'Сумма заказов (₽)',
                    'data' => $ordersSum->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#3b82f6', // Синий
                    'borderDash' => [5, 5], 
                    'fill' => false,
                    'yAxisID' => 'y',
                    'order' => 4,
                ],

                // --- ПРАВАЯ ШКАЛА 1 (ШТУКИ) ---
                [
                    'label' => 'Выкупы (шт)',
                    'data' => $salesCount->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#f59e0b', // Оранжевый
                    'backgroundColor' => '#f59e0b',
                    'type' => 'bar', 
                    'yAxisID' => 'y1', 
                    'barPercentage' => 0.5,
                    'order' => 2,
                ],
                [
                    'label' => 'Заказы (шт)',
                    'data' => $ordersCount->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#8b5cf6', // Фиолетовый
                    'borderWidth' => 2,
                    'pointRadius' => 4, 
                    'fill' => false,
                    'yAxisID' => 'y1',
                    'order' => 1, 
                ],

                // --- ПРАВАЯ ШКАЛА 2 (ПРОЦЕНТЫ) ---
                [
                    'label' => 'Процент выкупа (%)',
                    'data' => $buyoutPercents,
                    'borderColor' => '#ec4899', // Розовый
                    'backgroundColor' => '#ec4899',
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                    'fill' => false,
                    'yAxisID' => 'y2', // Новая ось
                    'order' => 0, // Самый верхний слой
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
                    'min' => 0, // Ограничиваем шкалу от 0
                    'max' => 100, // До 100% для наглядности
                    'grid' => [
                        'drawOnChartArea' => false, // Чтобы линии сетки не превращали график в кашу
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