<?php

namespace App\Filament\Resources\LogisticsResource\Pages;

use App\Filament\Resources\LogisticsResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageLogistics extends ManageRecords
{
    protected static string $resource = LogisticsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
