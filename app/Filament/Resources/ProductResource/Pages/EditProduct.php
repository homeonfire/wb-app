<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

// Не забудьте импортировать ваш новый виджет
use App\Filament\Resources\ProductResource\Widgets\ProductPlansWidget;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
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