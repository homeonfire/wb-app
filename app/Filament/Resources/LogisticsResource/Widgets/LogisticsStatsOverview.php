<?php

namespace App\Filament\Resources\LogisticsResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class LogisticsStatsOverview extends BaseWidget
{
    // Отключаем автообновление (polling), чтобы не грузить базу лишний раз
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        // 1. В производстве (Завод)
        // Просто суммируем колонку at_factory из таблицы остатков
        $factoryQty = DB::table('sku_stocks')->sum('at_factory');

        // 2. Едет Карго (В пути с завода)
        $cargoQty = DB::table('sku_stocks')->sum('in_transit_general');

        // 3. Деньги в пути (Самое интересное!)
        // Нам нужно умножить "кол-во в пути" на "себестоимость товара".
        // Для этого объединяем таблицы: sku_stocks -> skus -> products
        $moneyInTransit = DB::table('sku_stocks')
            ->join('skus', 'sku_stocks.sku_id', '=', 'skus.id')
            ->join('products', 'skus.product_id', '=', 'products.id')
            ->sum(DB::raw('sku_stocks.in_transit_general * products.cost_price'));

        return [
            Stat::make('В производстве', number_format($factoryQty, 0, '.', ' '))
                ->description('Единиц товара (Завод)')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->chart([1, 3, 2, 5, 4, $factoryQty > 0 ? $factoryQty / 10 : 1]) // Декоративный график
                ->color('primary'), // Фиолетовый

            Stat::make('Едет Карго', number_format($cargoQty, 0, '.', ' '))
                ->description('Единиц товара (В пути)')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'), // Синий

            Stat::make('Заморожено в пути', number_format($moneyInTransit, 0, '.', ' ') . ' ₽')
                ->description('Себестоимость груза (Карго)')
                ->descriptionIcon('heroicon-m-currency-ruble')
                ->color('success'), // Зеленый
        ];
    }
}