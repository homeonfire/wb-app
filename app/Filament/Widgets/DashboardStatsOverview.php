<?php

namespace App\Filament\Widgets;

use App\Models\OrderRaw;
use App\Models\Sku;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class DashboardStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $store = Filament::getTenant();
        
        if (!$store) {
            return [Stat::make('Нет магазина', '0')];
        }

        $today = Carbon::today('Europe/Moscow');
        $yesterday = Carbon::yesterday('Europe/Moscow');

        // 1. ЗАКАЗЫ
        $ordersToday = OrderRaw::where('store_id', $store->id)
            ->whereDate('order_date', $today)
            ->count();

        $ordersYesterday = OrderRaw::where('store_id', $store->id)
            ->whereDate('order_date', $yesterday)
            ->count();
        
        $ordersDiff = $ordersToday - $ordersYesterday;
        $ordersIcon = $ordersDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $ordersColor = $ordersDiff >= 0 ? 'success' : 'danger';

        // 2. ВЫРУЧКА
        $revenueToday = OrderRaw::where('store_id', $store->id)
            ->whereDate('order_date', $today)
            ->where('is_cancel', false)
            ->sum('total_price');
            
        $revenueYesterday = OrderRaw::where('store_id', $store->id)
            ->whereDate('order_date', $yesterday)
            ->where('is_cancel', false)
            ->sum('total_price');

        $revenueDiff = $revenueToday - $revenueYesterday;
        $revenueColor = $revenueDiff >= 0 ? 'success' : 'danger';
        $revenueIcon = $revenueDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        // 3. ОСТАТКИ (FBO)
        // 👇 ИСПРАВЛЕНИЕ ЗДЕСЬ
        // Вместо where('store_id'), используем whereHas('product', ...)
        $totalStock = Sku::whereHas('product', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })
            ->with('warehouseStocks')
            ->get()
            ->sum(function ($sku) {
                return $sku->warehouseStocks->sum('quantity');
            });

        return [
            Stat::make('Заказов сегодня', number_format($ordersToday, 0, '.', ' ') . ' шт.')
                ->description($ordersDiff >= 0 ? "+{$ordersDiff} к вчера" : number_format($ordersDiff, 0, '.', ' ') . " к вчера")
                ->descriptionIcon($ordersIcon)
                ->color($ordersColor)
                ->chart([$ordersYesterday, $ordersToday]),

            Stat::make('Сумма заказов сегодня', number_format($revenueToday, 0, '.', ' ') . ' ₽')
                ->description($revenueDiff >= 0 ? "+" . number_format($revenueDiff, 0, '.', ' ') : number_format($revenueDiff, 0, '.', ' '))
                ->descriptionIcon($revenueIcon)
                ->color($revenueColor),

            Stat::make('Товаров на WB (FBO)', number_format($totalStock, 0, '.', ' ') . ' шт.')
                ->description('Текущий остаток')
                ->icon('heroicon-o-archive-box')
                ->color('info'),
        ];
    }
}