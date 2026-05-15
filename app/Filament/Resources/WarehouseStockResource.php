<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseStockResource\Pages;
use App\Models\WarehouseStock;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;

class WarehouseStockResource extends Resource
{
    protected static ?string $model = WarehouseStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Сводка по складам';
    protected static ?string $pluralLabel = 'Сводка по складам';
    protected static ?string $navigationGroup = 'Складская аналитика';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                DB::raw('MAX(id) as id'), // <--- ДОБАВЛЯЕМ ВОТ ЭТОТ ФЕЙКОВЫЙ ID
                'warehouse_name',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(in_way_to_client) as total_to_client'),
                DB::raw('SUM(in_way_from_client) as total_from_client'),
            ])
            ->whereHas('product', function (Builder $query) {
                $query->where('store_id', Filament::getTenant()->id);
            })
            ->groupBy('warehouse_name');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse_name')
                    ->label('Склад')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total_quantity')
                    ->label('Количество')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('total_to_client')
                    ->label('В пути к клиенту')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('total_from_client')
                    ->label('В пути от клиента')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
            ])
            // 1. ADD THIS LINE to prevent the default sort by "id"
            ->defaultSort('warehouse_name') 
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouseStocks::route('/'),
        ];
    }
}