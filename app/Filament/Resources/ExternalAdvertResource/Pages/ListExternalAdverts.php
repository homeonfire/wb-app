<?php

namespace App\Filament\Resources\ExternalAdvertResource\Pages;

use App\Filament\Resources\ExternalAdvertResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExternalAdverts extends ListRecords
{
    protected static string $resource = ExternalAdvertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
