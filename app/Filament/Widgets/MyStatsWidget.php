<?php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MyStatsWidget extends BaseWidget
{
    public $orders;
    public $sales;

    protected function getStats(): array
    {
        return [
            Stat::make('Заказы (шт)', $this->orders)
                ->description('За выбранный период')
                ->color('primary')
                ->chart([7, 10, 15, $this->orders]), // Можно сделать реальный график

            Stat::make('Оплаты (шт)', $this->sales)
                ->description('Фактически выкуплено')
                ->color('success'),
        ];
    }
}