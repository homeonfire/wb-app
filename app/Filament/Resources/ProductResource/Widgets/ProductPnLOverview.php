<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\Product;
use App\Models\SaleRaw;
use App\Models\OrderRaw;
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

        // --- ОПТИМИЗАЦИЯ 1: Получаем выручку и количество выкупов ОДНИМ запросом ---
        // Вместо двух походов в базу (sum и count), просим БД посчитать всё за раз
        $salesStats = SaleRaw::where('nm_id', $product->nm_id)
            ->selectRaw('COALESCE(SUM(price_with_disc), 0) as revenue, COUNT(*) as buyouts_count')
            ->first();

        $revenue = (float) $salesStats->revenue;
        $buyoutsCount = (int) $salesStats->buyouts_count;

        // --- ОПТИМИЗАЦИЯ 2: Быстрый подсчет заказов ---
        $ordersCount = OrderRaw::where('nm_id', $product->nm_id)
            ->where('is_cancel', false)
            ->count();

        // --- ОПТИМИЗАЦИЯ 3: Жадная загрузка (Eager Loading) для остатков ---
        // Загружаем все размеры (SKU) товара и их остатки за 2 быстрых запроса, 
        // вместо того чтобы делать отдельный запрос на каждый размер.
        $totalStock = $product->skus()->with(['stock', 'warehouseStocks'])->get()->sum(function ($sku) {
            // Если есть детализация по складам — берем её
            if ($sku->warehouseStocks && $sku->warehouseStocks->isNotEmpty()) {
                return $sku->warehouseStocks->sum('quantity');
            }
            // Иначе пробуем взять общий остаток из связи stock
            return $sku->stock->quantity ?? 0;
        });

        // 3. Расчет маржинальности
        $cogs = $buyoutsCount * $product->cost_price; // Себестоимость проданного
        $grossProfit = $revenue - $cogs; // Прибыль
        $margin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;

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

            // 4. Общий остаток товара (НОВОЕ)
            Stat::make('Общий остаток', number_format($totalStock, 0, '.', ' ') . ' шт.')
                ->description('По всем складам и размерам')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('primary'),

            // 5. Маржинальность
            Stat::make('Маржинальность', number_format($margin, 1) . '%')
                ->description('Rider (ROI)')
                ->color($margin > 20 ? 'success' : 'danger'),
        ];
    }
}