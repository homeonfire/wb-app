<?php

namespace App\Filament\Resources\WarehouseStockResource\Pages;

use App\Filament\Resources\WarehouseStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseStocks extends ListRecords
{
    protected static string $resource = WarehouseStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
