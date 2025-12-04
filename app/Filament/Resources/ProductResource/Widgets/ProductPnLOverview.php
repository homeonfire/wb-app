<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\Product;
use App\Models\SaleRaw;
use App\Models\OrderRaw; // <--- Не забудь добавить этот импорт!
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

        // 1. Данные по Продажам (Выкупы)
        $salesQuery = SaleRaw::where('nm_id', $product->nm_id);
        
        $revenue = $salesQuery->sum('price_with_disc'); // Выручка
        $buyoutsCount = $salesQuery->count(); // Количество выкупов (шт)

        // 2. Данные по Заказам
        $ordersCount = OrderRaw::where('nm_id', $product->nm_id)
            ->where('is_cancel', false)
            ->count(); // Количество заказов (шт)

        // 3. Расчет маржинальности (скрытый)
        $cogs = $buyoutsCount * $product->cost_price; // Себестоимость проданного
        $grossProfit = $revenue - $cogs; // Прибыль
        $margin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;

        return [
            // 1. Выручка
            Stat::make('Выручка', number_format($revenue, 0, '.', ' ') . ' ₽')
                ->color('info'),

            // 2. Количество заказов (ВМЕСТО Себестоимости)
            Stat::make('Количество заказов', number_format($ordersCount, 0, '.', ' ') . ' шт.')
                ->description('Оформлено заказов')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),

            // 3. Количество выкупов (ВМЕСТО Прибыли)
            Stat::make('Количество выкупов', number_format($buyoutsCount, 0, '.', ' ') . ' шт.')
                ->description('Фактические продажи')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            // 4. Маржинальность (Оставляем)
            Stat::make('Маржинальность', number_format($margin, 1) . '%')
                ->description('Rider (ROI)')
                ->color($margin > 20 ? 'success' : 'danger'),
        ];
    }
}