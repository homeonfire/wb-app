<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreResource\Pages;
use App\Filament\Resources\StoreResource\RelationManagers;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Магазины';
    protected static ?string $modelLabel = 'Магазин';
    protected static ?string $pluralModelLabel = 'Магазины';

    // ОТКЛЮЧАЕМ фильтрацию по текущему магазину для этого ресурса
    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('name')
                ->label('Название')
                ->required(),
            Forms\Components\TextInput::make('slug')
                ->label('Slug (код)')
                ->required(),

            Forms\Components\Section::make('API Ключи WB')
                ->schema([
                    Forms\Components\TextInput::make('api_key_standard')
                        ->label('Стандартный ключ (Контент, Цены, Маркетплейс)')
                        ->password() // Скрываем символы
                        ->revealable(), // Кнопка "показать"

                    Forms\Components\TextInput::make('api_key_stat')
                        ->label('Ключ Статистика')
                        ->password()
                        ->revealable(),

                    Forms\Components\TextInput::make('api_key_advert')
                        ->label('Ключ Реклама')
                        ->password()
                        ->revealable(),
                ])
        ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Код (Slug)')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
