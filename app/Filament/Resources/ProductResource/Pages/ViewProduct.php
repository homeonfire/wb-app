<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    // ЭТОТ МЕТОД УДАЛЯЕМ, так как виджеты теперь внутри infolist
    /*
    protected function getFooterWidgets(): array
    {
        return [
            ProductPnLOverview::class,
            ProductSalesChart::class,
        ];
    }
    */
}