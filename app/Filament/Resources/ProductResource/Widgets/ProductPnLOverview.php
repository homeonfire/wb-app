<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\Product;
use App\Models\SaleRaw;
use App\Models\OrderRaw;
use App\Models\ProductAnalytic; // 👇 Добавлен импорт модели аналитики
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class ProductPnLOverview extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        /** @var Product $product */
        $product = $this->record;

        // 1. Выручка и количество выкупов (по сырым продажам)
        $salesStats = SaleRaw::where('nm_id', $product->nm_id)
            ->selectRaw('COALESCE(SUM(price_with_disc), 0) as revenue, COUNT(*) as buyouts_count')
            ->first();

        $revenue = (float) $salesStats->revenue;
        $buyoutsCount = (int) $salesStats->buyouts_count;

        // 2. Быстрый подсчет заказов (по сырым заказам)
        $ordersCount = OrderRaw::where('nm_id', $product->nm_id)
            ->where('is_cancel', false)
            ->count();

        // 3. Жадная загрузка (Eager Loading) для остатков
        $totalStock = $product->skus()->with(['stock', 'warehouseStocks'])->get()->sum(function ($sku) {
            if ($sku->warehouseStocks && $sku->warehouseStocks->isNotEmpty()) {
                return $sku->warehouseStocks->sum('quantity');
            }
            return $sku->stock->quantity ?? 0;
        });

        // 4. Расчет маржинальности
        $cogs = $buyoutsCount * $product->cost_price; // Себестоимость проданного
        $grossProfit = $revenue - $cogs; // Прибыль
        $margin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;

        // 👇 5. НОВОЕ: Процент выкупа из Воронки
        $funnelStats = ProductAnalytic::where('nm_id', $product->nm_id)
            ->selectRaw('SUM(orders_count) as total_orders, SUM(buyouts_count) as total_buyouts')
            ->first();

        $funnelOrders = (int) ($funnelStats->total_orders ?? 0);
        $funnelBuyouts = (int) ($funnelStats->total_buyouts ?? 0);
        $buyoutPercent = $funnelOrders > 0 ? round(($funnelBuyouts / $funnelOrders) * 100, 1) : 0;

        return [
            // 1. Выручка
            Stat::make('Выручка', number_format($revenue, 0, '.', ' ') . ' ₽')
                ->color('info'),

            // 2. Количество заказов
            Stat::make('Количество заказов', number_format($ordersCount, 0, '.', ' ') . ' шт.')
                ->description('Оформлено заказов')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),

            // 3. Количество выкупов
            Stat::make('Количество выкупов', number_format($buyoutsCount, 0, '.', ' ') . ' шт.')
                ->description('Фактические продажи')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            // 👇 4. НОВОЕ: Процент выкупа (из воронки)
            Stat::make('% Выкупа (Воронка)', $buyoutPercent . '%')
                ->description("Из {$funnelOrders} зак. выкуплено {$funnelBuyouts}")
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($buyoutPercent >= 30 ? 'success' : 'warning'), // Можно настроить порог цвета

            // 5. Общий остаток товара
            Stat::make('Общий остаток', number_format($totalStock, 0, '.', ' ') . ' шт.')
                ->description('По всем складам и размерам')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('primary'),

            // 6. Маржинальность
            Stat::make('Маржинальность', number_format($margin, 1) . '%')
                ->description('Рентабельность (ROI)')
                ->color($margin > 20 ? 'success' : 'danger'),
        ];
    }
}