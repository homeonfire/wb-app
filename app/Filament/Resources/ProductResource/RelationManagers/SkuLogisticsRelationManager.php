<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Sku;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SkuLogisticsRelationManager extends RelationManager
{
    protected static string $relationship = 'skus';

    protected static ?string $title = 'Остатки и логистика по размерам (SKU)';
    protected static ?string $icon = 'heroicon-o-table-cells';

    // ⚠️ Метод isReadOnly() удален, чтобы Filament разрешил отображение кнопки "Склады"

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tech_size')
            ->paginated(false) // Показываем все размеры сразу без страниц
            ->striped() // Полосатые строки
            ->columns([
                // 1. РАЗМЕР / БАРКОД
                Tables\Columns\TextColumn::make('tech_size')
                    ->label('Размер / Баркод')
                    ->description(fn (Sku $record) => $record->barcode)
                    ->weight('bold'),

                // 2. ПРОДАЖ/ДЕНЬ (Среднее за 30 дней)
                Tables\Columns\TextColumn::make('sales_per_day')
                    ->label('Продаж/день')
                    ->getStateUsing(function (Sku $record) {
                        $sales30 = $record->sales()
                            ->where('sale_date', '>=', now()->subDays(30))
                            ->count();
                        
                        return $sales30 > 0 ? number_format($sales30 / 30, 2) : '0.00';
                    })
                    ->alignCenter(),

                // 3. ОСТАТОК WB (FBO)
                Tables\Columns\TextColumn::make('wb_stock')
                    ->label('Остаток WB')
                    ->getStateUsing(fn (Sku $record) => $record->warehouseStocks->sum('quantity'))
                    ->alignCenter()
                    ->color('gray'),

                // 4. К КЛИЕНТУ
                Tables\Columns\TextColumn::make('to_client')
                    ->label('К клиенту')
                    ->getStateUsing(fn (Sku $record) => $record->warehouseStocks->sum('in_way_to_client'))
                    ->alignCenter()
                    ->color('info'),

                // 5. ОТ КЛИЕНТА
                Tables\Columns\TextColumn::make('from_client')
                    ->label('От клиента')
                    ->getStateUsing(fn (Sku $record) => $record->warehouseStocks->sum('in_way_from_client'))
                    ->alignCenter()
                    ->color('warning'),

                // 6. СВОЙ СКЛАД
                Tables\Columns\TextColumn::make('stock.stock_own')
                    ->label('Свой склад')
                    ->default(0)
                    ->alignCenter(),

                // 7. В ПУТИ НА WB
                Tables\Columns\TextColumn::make('stock.in_transit_to_wb')
                    ->label('В пути на WB')
                    ->default(0)
                    ->alignCenter(),

                // 8. В ПУТИ (КАРГО)
                Tables\Columns\TextColumn::make('stock.in_transit_general')
                    ->label('В пути (Склад)')
                    ->default(0)
                    ->alignCenter(),

                // 9. НА ФАБРИКЕ
                Tables\Columns\TextColumn::make('stock.at_factory')
                    ->label('На фабрике')
                    ->default(0)
                    ->alignCenter(),

                // 10. ОБОРАЧИВАЕМОСТЬ (Дней)
                Tables\Columns\TextColumn::make('turnover')
                    ->label('Оборачиваемость')
                    ->getStateUsing(function (Sku $record) {
                        $wbStock = $record->warehouseStocks->sum('quantity');
                        $ownStock = $record->stock->stock_own ?? 0;
                        $totalStock = $wbStock + $ownStock;

                        $sales30 = $record->sales()
                            ->where('sale_date', '>=', now()->subDays(30))
                            ->count();
                        $speed = $sales30 / 30;

                        if ($speed <= 0) return '∞';

                        $days = $totalStock / $speed;
                        return round($days);
                    })
                    ->color(fn ($state) => match(true) {
                         $state === '∞' => 'gray',
                         $state < 60 => 'success',
                         $state < 100 => 'warning',
                         default => 'danger',
                    })
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->actions([
                // 👇 Кнопка "Склады" теперь отобразится корректно
                Tables\Actions\Action::make('warehouses')
                    ->label('Склады')
                    ->icon('heroicon-o-building-storefront')
                    ->modalHeading(fn ($record) => "Остатки по складам: {$record->tech_size} ({$record->barcode})")
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(fn ($action) => $action->label('Закрыть'))
                    ->modalContent(fn ($record) => view('filament.resources.product-resource.sku-warehouses-modal', ['sku' => $record])),
            ]);
    }
}