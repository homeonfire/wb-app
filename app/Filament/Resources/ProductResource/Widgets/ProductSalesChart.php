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
    protected static ?string $maxHeight = '320px';
    
    public ?Model $record = null;

    protected function getData(): array
    {
        if (!$this->record) return [];

        $start = now()->subDays(30);
        $end = now();

        // 1. Выручка (Продажи в рублях - ФАКТ)
        $revenue = Trend::query(SaleRaw::where('nm_id', $this->record->nm_id))
            ->dateColumn('sale_date')
            ->between($start, $end)
            ->perDay()
            ->sum('price_with_disc');

        // 2. Сумма заказов (Заказы в рублях - ФАКТ)
        $ordersSum = Trend::query(OrderRaw::where('nm_id', $this->record->nm_id)->where('is_cancel', false))
            ->dateColumn('order_date')
            ->between($start, $end)
            ->perDay()
            ->sum('total_price');

        // 3. Кол-во выкупов (Штуки - ФАКТ)
        $salesCount = Trend::query(SaleRaw::where('nm_id', $this->record->nm_id))
            ->dateColumn('sale_date')
            ->between($start, $end)
            ->perDay()
            ->count();

        // 4. Кол-во заказов (Штуки - ФАКТ)
        $ordersCount = Trend::query(OrderRaw::where('nm_id', $this->record->nm_id)->where('is_cancel', false))
            ->dateColumn('order_date')
            ->between($start, $end)
            ->perDay()
            ->count();

        // 5. Высчитываем % выкупа по фактическим данным
        $buyoutPercents = [];
        foreach ($ordersCount as $key => $order) {
            $orderAgg = $order->aggregate;
            $saleAgg = $salesCount[$key]->aggregate ?? 0;
            
            $percent = $orderAgg > 0 ? round(($saleAgg / $orderAgg) * 100, 2) : 0;
            
            // Срезаем аномалии свыше 100% (когда выкупили старые заказы в день, где нет новых),
            // чтобы шкала графика не сжималась в полоску
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
                    'tension' => 0.4, // Плавные изгибы линии
                    'pointRadius' => 0, // Убираем точки (появятся при наведении)
                    'pointHoverRadius' => 6,
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
                    'hidden' => true, // Скрываем по умолчанию, чтобы разгрузить график
                    'yAxisID' => 'y',
                    'order' => 4,
                ],

                // --- ПРАВАЯ ШКАЛА 1 (ШТУКИ) ---
                [
                    'label' => 'Выкупы факт (шт)',
                    'data' => $salesCount->map(fn (TrendValue $value) => $value->aggregate),
                    'backgroundColor' => '#f59e0b', // Оранжевый
                    'type' => 'bar', 
                    'yAxisID' => 'y1', 
                    'barPercentage' => 0.4, // Делаем столбики тоньше
                    'borderRadius' => 4, // Скругляем углы столбиков
                    'order' => 2,
                ],
                [
                    'label' => 'Заказы факт (шт)',
                    'data' => $ordersCount->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#8b5cf6', // Фиолетовый
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4, // Плавная линия
                    'pointRadius' => 0,
                    'pointHoverRadius' => 6,
                    'yAxisID' => 'y1',
                    'order' => 1, 
                ],

                // --- ПРАВАЯ ШКАЛА 2 (ПРОЦЕНТЫ) ---
                [
                    'label' => '% выкупа (факт)',
                    'data' => $buyoutPercents,
                    'borderColor' => '#ec4899', // Розовый
                    'backgroundColor' => '#ec4899',
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4, // Плавная линия
                    'pointRadius' => 0,
                    'pointHoverRadius' => 6,
                    'yAxisID' => 'y2',
                    'order' => 0,
                ],
            ],
            'labels' => $revenue->map(fn (TrendValue $value) => \Carbon\Carbon::parse($value->date)->format('d.m')), // Короткий формат даты: 15.04
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            // Включаем единый тултип при наведении (показывает все метрики за день разом)
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
                        'color' => '#10b981', // Красим текст в цвет основной линии
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
                        'drawOnChartArea' => false, // Отключаем сетку, чтобы не было "каши"
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