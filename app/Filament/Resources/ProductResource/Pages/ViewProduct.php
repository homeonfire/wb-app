<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

// Импорт виджета
use App\Filament\Resources\ProductResource\Widgets\ProductPlansWidget;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    // 👇 ДОБАВЛЯЕМ ЭТОТ МЕТОД
    protected function getFooterWidgets(): array
    {
        return [
            ProductPlansWidget::class,
        ];
    }
}