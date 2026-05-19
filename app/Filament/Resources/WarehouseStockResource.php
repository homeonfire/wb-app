<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseStockResource\Pages;
use App\Models\SkuWarehouseStock; // Основная плоская таблица с общими остатками
use App\Models\SkuWarehouseDetail; // Таблица детализации по городам
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Filament\Facades\Filament;

class WarehouseStockResource extends Resource
{
    // Переключаем на модель плоских остатков (1 строка = 1 SKU)
    protected static ?string $model = SkuWarehouseStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Аналитика по товарам';
    protected static ?string $pluralLabel = 'Аналитика по товарам';
    protected static ?string $navigationGroup = 'Складская аналитика';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        // Больше никакой сложной группировки в запросе не нужно, так как таблица уже плоская.
        // Просто подгружаем связи, чтобы избежать N+1 запросов.
        return parent::getEloquentQuery()
            ->with(['sku.product'])
            ->whereHas('sku.product', function (Builder $query) {
                $query->where('store_id', Filament::getTenant()->id);
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku.product.title')
                    ->label('Товар')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => "Арт: " . ($record->sku->product->vendor_code ?? '-')),

                TextColumn::make('sku.barcode')
                    ->label('Баркод')
                    ->searchable(),

                TextColumn::make('sku.tech_size')
                    ->label('Размер')
                    ->alignCenter(),

                TextColumn::make('quantity')
                    ->label('Всего на складах')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('in_way_to_client')
                    ->label('В пути к клиенту')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('in_way_from_client')
                    ->label('В пути от клиента')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('quantity', 'desc') 
            ->filters([])
            ->actions([
                // Попап окно с разбивкой по конкретным городам/складам
                Action::make('view_warehouses')
                    ->label('Склады')
                    ->icon('heroicon-m-building-office-2')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Разбивка по складам: " . ($record->sku->product->title ?? 'Товар'))
                    ->modalDescription(fn ($record) => "Размер: " . ($record->sku->tech_size ?? '-') . " | Баркод: " . ($record->sku->barcode ?? '-'))
                    ->modalSubmitAction(false) // Кнопка "Сохранить" не нужна, окно только для просмотра
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(fn ($record) => new HtmlString(
                        Blade::render('
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500">
                                            <th class="py-3 font-semibold text-left">Название склада</th>
                                            <th class="py-3 font-semibold text-center w-32">Количество</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @php
                                            $details = \App\Models\SkuWarehouseDetail::where("sku_id", $skuId)
                                                ->orderBy("quantity", "desc")
                                                ->get();
                                        @endphp
                                        
                                        @forelse($details as $detail)
                                            <tr>
                                                <td class="py-3 text-left font-medium">{{ $detail->warehouse_name }}</td>
                                                <td class="py-3 text-center font-bold text-primary-600">{{ $detail->quantity }} шт.</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="py-4 text-center text-gray-400">Нет данных об остатках на физических складах</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        ', ['skuId' => $record->sku_id])
                    ))
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouseStocks::route('/'),
        ];
    }
}