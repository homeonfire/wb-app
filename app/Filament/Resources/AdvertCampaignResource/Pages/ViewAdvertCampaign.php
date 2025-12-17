<?php

namespace App\Filament\Resources\AdvertCampaignResource\Pages;

use App\Filament\Resources\AdvertCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAdvertCampaign extends ViewRecord
{
    protected static string $resource = AdvertCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Можно добавить кнопку "Назад" или "Обновить"
        ];
    }
}