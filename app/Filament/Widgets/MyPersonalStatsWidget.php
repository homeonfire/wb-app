<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\OrderRaw;
use App\Models\SaleRaw;
use Illuminate\Support\Facades\Auth;

class MyPersonalStatsWidget extends BaseWidget
{
    // 👇 ЭТОТ МЕТОД ДОЛЖЕН БЫТЬ ТОЛЬКО ОДИН
    public static function canView(): bool
    {
        // Показывать виджет ТОЛЬКО если мы находимся на странице "Мои товары"
        return request()->routeIs('filament.admin.pages.my-products');
    }

    protected function getStats(): array
    {
        // Проверка на случай, если виджет загружается где-то, где нет авторизации
        if (!Auth::check()) {
            return [];
        }

        $nmIds = Auth::user()->products()->pluck('nm_id');
        
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $myOrders = OrderRaw::whereIn('nm_id', $nmIds)
            ->whereBetween('order_date', [$start, $end])
            ->count();

        $mySales = SaleRaw::whereIn('nm_id', $nmIds)
            ->whereBetween('sale_date', [$start, $end])
            ->count();

        $myOrdersSum = OrderRaw::whereIn('nm_id', $nmIds)
            ->whereBetween('order_date', [$start, $end])
            ->sum('finished_price');

        return [
            Stat::make('Мои заказы (мес)', number_format($myOrders, 0, '.', ' '))
                ->description('Всего заказов')
                ->color('primary'),

            Stat::make('Мои выкупы (мес)', number_format($mySales, 0, '.', ' '))
                ->description('Всего продаж')
                ->color('success'),

            Stat::make('Сумма заказов', number_format($myOrdersSum, 0, '.', ' ') . ' ₽')
                ->description('Потенциальная выручка')
                ->color('warning'),
        ];
    }
}