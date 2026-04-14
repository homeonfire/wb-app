<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\OrderRaw;
use App\Models\SaleRaw;
use App\Models\ProductAnalytic; // <-- Подключаем воронку
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class ProductSalesChart extends ChartWidget
{
    protected static ?string $heading = 'Динамика продаж и заказов';
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '320px'; // Чуть больше высоты для "воздуха"
    
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
        $salesCountFact = Trend::query(SaleRaw::where('nm_id', $this->record->nm_id))
            ->dateColumn('sale_date')
            ->between($start, $end)
            ->perDay()
            ->count();

        // 4. Кол-во заказов (Штуки - ФАКТ)
        $ordersCountFact = Trend::query(OrderRaw::where('nm_id', $this->record->nm_id)->where('is_cancel', false))
            ->dateColumn('order_date')
            ->between($start, $end)
            ->perDay()
            ->count();

        // --- 5. ДАННЫЕ ИЗ ВОРОНКИ (ТОЛЬКО ДЛЯ РАСЧЕТА ПРОЦЕНТА ВЫКУПА) ---
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

        // Высчитываем % выкупа по ВОРОНКЕ
        $buyoutPercents = [];
        foreach ($funnelOrdersCount as $key => $order) {
            $orderAgg = $order->aggregate;
            $saleAgg = $funnelSalesCount[$key]->aggregate ?? 0;
            
            $percent = $orderAgg > 0 ? round(($saleAgg / $orderAgg) * 100, 2) : 0;
            if ($percent > 100) $percent = 100; // Срез аномалий

            $buyoutPercents[] = $percent;
        }

        return [
            'datasets' => [
                // --- ЛЕВАЯ ШКАЛА (ДЕНЬГИ - ФАКТ) ---
                [
                    'label' => 'Выручка (₽)',
                    'data' => $revenue->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10b981', // Зеленый
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4, // Плавные линии
                    'pointRadius' => 0, // Убираем точки до наведения
                    'pointHoverRadius' => 6,
                    'yAxisID' => 'y',
                    'order' => 3, 
                ],
                [
                    'label' => 'Сумма заказов (₽)',
                    'data' => $ordersSum->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#3b82f6', // Синий
                    'borderDash' => [5, 5], 
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 6,
                    'hidden' => true, // <-- СКРЫТО ПО УМОЛЧАНИЮ для чистоты графика
                    'yAxisID' => 'y',
                    'order' => 4,
                ],

                // --- ПРАВАЯ ШКАЛА 1 (ШТУКИ - ФАКТ) ---
                [
                    'label' => 'Выкупы факт (шт)',
                    'data' => $salesCountFact->map(fn (TrendValue $value) => $value->aggregate),
                    'backgroundColor' => '#f59e0b', // Оранжевый
                    'type' => 'bar', 
                    'yAxisID' => 'y1', 
                    'barPercentage' => 0.4, // Тонкие столбики
                    'borderRadius' => 4,
                    'order' => 2,
                ],
                [
                    'label' => 'Заказы факт (шт)',
                    'data' => $ordersCountFact->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#8b5cf6', // Фиолетовый
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 6,
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
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 6,
                    'yAxisID' => 'y2',
                    'order' => 0,
                ],
            ],
            'labels' => $revenue->map(fn (TrendValue $value) => \Carbon\Carbon::parse($value->date)->format('d.m')), // Короткая дата "15.04"
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            // Идеальный тултип: одна плашка со всеми метриками за день
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
                        'color' => '#10b981',
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
                        'drawOnChartArea' => false, // Убираем кашу из сеток
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