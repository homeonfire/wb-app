<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogisticsResource\Pages;
use App\Models\Product;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LogisticsResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationLabel = 'Логистика (Сводка)';
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $slug = 'logistics-summary';
    protected static ?int $navigationSort = 20;

    // ОТКЛЮЧАЕМ РЕДАКТИРОВАНИЕ
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('main_image_url')
                    ->label('')
                    ->circular(),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('Товар')
                    ->description(fn (Product $record) => $record->brand . ' / ' . $record->vendor_code)
                    ->searchable(['title', 'brand', 'vendor_code'])
                    ->limit(30)
                    ->weight('bold'),

                // 1. ПРОДАЖ В ДЕНЬ
                Tables\Columns\TextColumn::make('total_sales_speed')
                    ->label('Продаж/день')
                    ->getStateUsing(function (Product $record) {
                        $totalSales30 = $record->skus->sum(function ($sku) {
                            return $sku->sales()
                                ->where('sale_date', '>=', now()->subDays(30))
                                ->count();
                        });
                        return number_format($totalSales30 / 30, 2);
                    })
                    ->alignCenter(),

                // 2. ОСТАТОК WB
                Tables\Columns\TextColumn::make('total_wb_stock')
                    ->label('Остаток WB')
                    ->getStateUsing(fn (Product $record) => $record->skus->flatMap->warehouseStocks->sum('quantity'))
                    ->alignCenter()
                    ->color('gray'),

                // 3. К КЛИЕНТУ
                Tables\Columns\TextColumn::make('total_to_client')
                    ->label('К клиенту')
                    ->getStateUsing(fn (Product $record) => $record->skus->flatMap->warehouseStocks->sum('in_way_to_client'))
                    ->alignCenter()
                    ->color('info'),

                // 4. ОБОРАЧИВАЕМОСТЬ
                Tables\Columns\TextColumn::make('turnover_avg')
                    ->label('Оборачиваемость')
                    ->getStateUsing(function (Product $record) {
                        $stock = $record->skus->flatMap->warehouseStocks->sum('quantity');
                        
                        $sales30 = $record->skus->sum(function ($sku) {
                            return $sku->sales()->where('sale_date', '>=', now()->subDays(30))->count();
                        });
                        $speed = $sales30 / 30;

                        if ($speed <= 0) return '∞';
                        return round($stock / $speed);
                    })
                    ->color(fn ($state) => match(true) {
                         $state === '∞' => 'gray',
                         $state < 60 => 'success',
                         $state < 100 => 'warning',
                         default => 'danger',
                    })
                    ->alignCenter()
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_sizes')
                    ->label('Размеры')
                    ->icon('heroicon-o-arrows-pointing-out')
                    ->modalHeading(fn (Product $record) => "Логистика по размерам: {$record->vendor_code}")
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(fn ($action) => $action->label('Закрыть'))
                    ->modalContent(fn (Product $record) => view('filament.resources.logistics.sizes-modal', ['record' => $record])),
            ])
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            // 👇 ВОТ ЗДЕСЬ БЫЛА ОШИБКА. ДЛЯ SIMPLE РЕСУРСА ЭТО ManageLogistics
            'index' => Pages\ManageLogistics::route('/'),
        ];
    }
}