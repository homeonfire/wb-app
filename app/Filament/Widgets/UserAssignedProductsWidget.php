<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Product;
use App\Models\OrderRaw;
use App\Models\SaleRaw;
use Filament\Facades\Filament;
use Carbon\Carbon;

class UserAssignedProductsWidget extends Widget
{
    // Указываем новый кастомный шаблон
    protected static string $view = 'filament.widgets.store-plan-overview';
    
    // Растягиваем на всю ширину
    protected int | string | array $columnSpan = 'full';
    
    // Поднимаем виджет в самый верх Инфопанели
    protected static ?int $sort = 1;

    protected function getViewData(): array
    {
        $storeId = Filament::getTenant()->id;
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        // 1. Получаем ТОЛЬКО ТЕ ТОВАРЫ, у которых ЕСТЬ ПЛАН на этот месяц
        $products = Product::where('store_id', $storeId)
            ->whereHas('plans', function($q) use ($start) {
                $q->where('year', $start->year)->where('month', $start->month);
            })
            ->with(['plans' => function($q) use ($start) {
                $q->where('year', $start->year)->where('month', $start->month);
            }])
            ->get()
            ->keyBy('nm_id');

        $nmIds = $products->keys()->toArray();

        // Если планов ни у кого нет, отдаем пустые данные, чтобы не было ошибки БД
        if (empty($nmIds)) {
            return $this->getEmptyData($start);
        }

        // 2. Считаем ОБЩИЕ ПЛАНЫ
        $ordersPlan = 0;
        $salesPlan = 0;
        $marginPlan = 0;

        foreach ($products as $product) {
            $plan = $product->plans->first();
            if ($plan) {
                $ordersPlan += $plan->orders_plan ?? 0;
                $salesPlan += $plan->sales_plan ?? 0;
                $marginPlan += $plan->margin_plan ?? 0;
            }
        }

        // 3. Считаем ОБЩИЙ ФАКТ (ТОЛЬКО по товарам с планом)
        // Заказы
        $ordersFact = OrderRaw::whereIn('nm_id', $nmIds)
            ->whereBetween('order_date', [$start, $end])
            ->count();
        
        // Выкупы и выручка (группируем одним запросом для скорости)
        $salesAgg = SaleRaw::whereIn('nm_id', $nmIds)
            ->whereBetween('sale_date', [$start, $end])
            ->selectRaw('nm_id, SUM(price_with_disc) as rev, COUNT(*) as cnt')
            ->groupBy('nm_id')
            ->get();
            
        $salesFact = $salesAgg->sum('cnt');
        

        return [
    'monthName' => $start->translatedFormat('F Y'),
    'overall_percent' => $salesPlan > 0 ? round(($salesFact / $salesPlan) * 100) : ($salesFact > 0 ? 100 : 0),
    'metrics' => [
        [
            'label' => 'Заказы',
            'fact' => $ordersFact,
            'plan' => $ordersPlan,
            'unit' => 'шт.',
            'percent' => $ordersPlan > 0 ? round(($ordersFact / $ordersPlan) * 100) : ($ordersFact > 0 ? 100 : 0),
        ],
        [
            'label' => 'Выкупы',
            'fact' => $salesFact,
            'plan' => $salesPlan,
            'unit' => 'шт.',
            'percent' => $salesPlan > 0 ? round(($salesFact / $salesPlan) * 100) : ($salesFact > 0 ? 100 : 0),
        ],
        [
            'label' => 'Выручка', // Заменили маржу на выручку
            'fact' => $salesAgg->sum('rev'), 
            'plan' => $products->sum(fn($p) => $p->plans->first()->sales_plan ?? 0), // Либо твое поле плана выручки
            'unit' => '₽',
            'percent' => $salesPlan > 0 ? round(($salesAgg->sum('rev') / $salesPlan) * 100) : 0, 
        ],
    ]
];
    }

    // Вспомогательный метод для пустых планов (в начале месяца)
    private function getEmptyData(Carbon $start): array
    {
        return [
            'monthName' => $start->translatedFormat('F Y'),
            'overall_percent' => 0,
            'metrics' => [
                ['label' => 'Общие заказы', 'fact' => 0, 'plan' => 0, 'unit' => 'шт.', 'percent' => 0],
                ['label' => 'Общие выкупы', 'fact' => 0, 'plan' => 0, 'unit' => 'шт.', 'percent' => 0],
                ['label' => 'Общая маржа', 'fact' => 0, 'plan' => 0, 'unit' => '₽', 'percent' => 0],
            ]
        ];
    }
}