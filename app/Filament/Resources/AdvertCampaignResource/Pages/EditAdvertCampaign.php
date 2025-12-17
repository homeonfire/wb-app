<?php

namespace App\Filament\Resources\AdvertCampaignResource\Pages;

use App\Filament\Resources\AdvertCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdvertCampaign extends EditRecord
{
    protected static string $resource = AdvertCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
