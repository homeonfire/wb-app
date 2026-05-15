<?php

namespace App\Filament\Resources\WarehouseStockResource\Pages;

use App\Filament\Resources\WarehouseStockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseStock extends EditRecord
{
    protected static string $resource = WarehouseStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
