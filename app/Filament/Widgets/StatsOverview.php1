<?php

namespace App\Filament\Widgets;

use App\Models\OrderRaw;
use App\Models\SaleRaw;
use App\Models\SkuWarehouseStock;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Получаем текущий магазин
        $store = Filament::getTenant();

        if (!$store) {
            return [];
        }

        // 1. Заказы за сегодня
        $ordersToday = OrderRaw::where('store_id', $store->id)
            ->whereDate('order_date', Carbon::today(-1))
            ->where('is_cancel', false) // Исключаем отмены
            ->sum('total_price'); // Сумма до скидки СПП (можно менять на finished_price)

        // 2. Продажи (выкупы) за сегодня
        $salesToday = SaleRaw::where('store_id', $store->id)
            ->whereDate('sale_date', Carbon::today(-1))
            ->sum('price_with_disc'); // Реальная цена продажи

        // 3. Остатки на WB (нужно пройтись через связь Product -> Sku -> Stock)
        // Но у нас нет прямой связи Store -> SkuWarehouseStock.
        // Проще посчитать сумму quantity всех записей, где sku принадлежит продукту этого магазина.
        // Это сложный запрос, упростим:
        // Мы можем использовать таблицу products, и через нее зайти в остатки.

        $stockCount = 0;
        // Получаем ID всех SKU этого магазина
        $skuIds = \App\Models\Sku::whereHas('product', function ($query) use ($store) {
            $query->where('store_id', $store->id);
        })->pluck('id');

        $stockCount = SkuWarehouseStock::whereIn('sku_id', $skuIds)->sum('quantity');


        return [
            Stat::make('Заказы сегодня', number_format($ordersToday, 0, '.', ' ') . ' ₽')
                ->description('Сумма заказов')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('info'),

            Stat::make('Продажи вчера', number_format($salesToday, 0, '.', ' ') . ' ₽')
                ->description('Фактические выкупы')
                ->color('success'),

            Stat::make('Остаток FBO', number_format($stockCount, 0, '.', ' ') . ' шт.')
                ->description('Товаров на складах WB')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('warning'),
        ];
    }
}