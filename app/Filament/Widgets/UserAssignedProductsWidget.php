<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UserAssignedProductsWidget extends BaseWidget
{
    // Растягиваем таблицу на всю ширину дашборда
    protected int | string | array $columnSpan = 'full';

    // Заголовок виджета
    protected static ?string $heading = 'Мои товары';

    // Сортировка (чем выше число, тем ниже виджет на странице)
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // 👇 ДОБАВИЛ ->getQuery()
                auth()->user()->managedProducts()->getQuery()
                    ->where('products.store_id', filament()->getTenant()->id)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('main_image_url')
                    ->label('Фото')
                    ->circular(),

                Tables\Columns\TextColumn::make('vendor_code')
                    ->label('Артикул')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('brand')
                    ->label('Бренд')
                    ->sortable(),
            ])
            ->recordUrl(
                fn (Product $record): string => ProductResource::getUrl('view', ['record' => $record])
            );
    }

    // Показывать виджет, только если у пользователя есть привязанные товары
    // (Если хотите показывать пустую таблицу — удалите этот метод)
    public static function canView(): bool
    {
        // Проверка: есть ли у юзера товары в этом магазине?
        return auth()->user()->managedProducts()
            ->where('products.store_id', filament()->getTenant()->id)
            ->exists();
    }
}