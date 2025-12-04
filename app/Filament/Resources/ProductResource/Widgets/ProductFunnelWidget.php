<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\Product;
use App\Models\ProductAnalytic;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class ProductFunnelWidget extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record) return [];

        /** @var Product $product */
        $product = $this->record;

        // Берем данные за последние 30 дней
        $analytics = ProductAnalytic::where('nm_id', $product->nm_id)
            ->where('date', '>=', now()->subDays(30))
            ->get();

        $views = $analytics->sum('open_card_count');
        $carts = $analytics->sum('add_to_cart_count');
        $orders = $analytics->sum('orders_count');

        // Расчет конверсий
        $cr_cart = $views > 0 ? ($carts / $views) * 100 : 0; // Конверсия в корзину
        $cr_order = $carts > 0 ? ($orders / $carts) * 100 : 0; // Конверсия в заказ
        $cr_total = $views > 0 ? ($orders / $views) * 100 : 0; // Общая конверсия

        return [
            Stat::make('Просмотры (Open Card)', number_format($views, 0, '.', ' '))
                ->description('Трафик за 30 дней')
                ->color('gray'),

            Stat::make('Конверсия в Корзину', number_format($cr_cart, 1) . '%')
                ->description("В корзину: {$carts}")
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color($cr_cart > 5 ? 'success' : 'warning'),

            Stat::make('Конверсия в Заказ', number_format($cr_order, 1) . '%')
                ->description("Заказали: {$orders}")
                ->descriptionIcon('heroicon-m-check')
                ->color($cr_order > 20 ? 'success' : 'danger'),

            Stat::make('Общая конверсия (CR)', number_format($cr_total, 2) . '%')
                ->description('Заказы / Просмотры')
                ->color('primary'),
        ];
    }
}