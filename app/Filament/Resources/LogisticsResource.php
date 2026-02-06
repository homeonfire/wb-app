<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogisticsResource\Pages;
use App\Models\Product;
use App\Models\Sku;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
// Подключаем виджет статистики
use App\Filament\Resources\LogisticsResource\Widgets\LogisticsStatsOverview;

class LogisticsResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationLabel = 'Логистика (Сводка)';
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $slug = 'logistics-summary';
    protected static ?int $navigationSort = 20;

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

                // 1. ЗАКАЗ ЗАВОД
                Tables\Columns\TextColumn::make('total_at_factory')
                    ->label('Завод')
                    ->getStateUsing(fn (Product $record) => $record->skus->sum(fn($s) => $s->stock?->at_factory ?? 0))
                    ->alignCenter()
                    ->color('primary')
                    ->weight('bold'),

                // 2. В ПУТИ С ЗАВОДА
                Tables\Columns\TextColumn::make('total_in_transit_general')
                    ->label('Карго')
                    ->getStateUsing(fn (Product $record) => $record->skus->sum(fn($s) => $s->stock?->in_transit_general ?? 0))
                    ->alignCenter()
                    ->color('info')
                    ->weight('bold'),

                // 3. НАШ СКЛАД
                Tables\Columns\TextColumn::make('total_stock_own')
                    ->label('Склад')
                    ->getStateUsing(fn (Product $record) => $record->skus->sum(fn($s) => $s->stock?->stock_own ?? 0))
                    ->alignCenter()
                    ->color('success') 
                    ->weight('bold'),

                // 4. В ПУТИ НА WB
                Tables\Columns\TextColumn::make('total_in_transit_wb')
                    ->label('В пути WB')
                    ->getStateUsing(fn (Product $record) => $record->skus->sum(fn($s) => $s->stock?->in_transit_to_wb ?? 0))
                    ->alignCenter()
                    ->color('warning')
                    ->weight('bold'),

                // 5. ОСТАТОК WB (FBO)
                Tables\Columns\TextColumn::make('total_wb_stock')
                    ->label('На WB')
                    ->getStateUsing(fn (Product $record) => $record->skus->flatMap->warehouseStocks->sum('quantity'))
                    ->alignCenter()
                    ->color('gray'),

                // 6. К КЛИЕНТУ
                Tables\Columns\TextColumn::make('total_to_client')
                    ->label('К клиенту')
                    ->getStateUsing(fn (Product $record) => $record->skus->flatMap->warehouseStocks->sum('in_way_to_client'))
                    ->alignCenter()
                    ->color('info'),

                // 7. ПРОДАЖ/ДЕНЬ
                Tables\Columns\TextColumn::make('total_sales_speed')
                    ->label('Ср.прод')
                    ->getStateUsing(function (Product $record) {
                        $totalSales30 = $record->skus->sum(function ($sku) {
                            return $sku->sales()
                                ->where('sale_date', '>=', now()->subDays(30))
                                ->count();
                        });
                        return number_format($totalSales30 / 30, 1);
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('turnover_avg')
                    ->label('Обор.')
                    ->getStateUsing(function (Product $record) {
                        $stock = $record->skus->flatMap->warehouseStocks->sum('quantity');
                        $sales30 = $record->skus->sum(fn($s) => $s->sales()->where('sale_date', '>=', now()->subDays(30))->count());
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
            ->filters([
                // === ФИЛЬТРЫ ЛОГИСТИКИ (Мин/Макс) ===
                
                // 1. Завод
                Tables\Filters\Filter::make('factory_filter')
                    ->form([
                        // ИСПОЛЬЗУЕМ Forms\Components вместо несуществующих Tables\Filters\Components
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('min')->numeric()->label('Завод: от'),
                            \Filament\Forms\Components\TextInput::make('max')->numeric()->label('до'),
                        ]),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        return $query
                            ->when($data['min'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(at_factory) FROM sku_stocks WHERE sku_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) >= ?', [$val]))
                            ->when($data['max'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(at_factory) FROM sku_stocks WHERE sku_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) <= ?', [$val]));
                    }),

                // 2. Карго
                Tables\Filters\Filter::make('cargo_filter')
                    ->form([
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('min')->numeric()->label('Карго: от'),
                            \Filament\Forms\Components\TextInput::make('max')->numeric()->label('до'),
                        ]),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['min'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(in_transit_general) FROM sku_stocks WHERE sku_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) >= ?', [$val]))
                            ->when($data['max'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(in_transit_general) FROM sku_stocks WHERE sku_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) <= ?', [$val]));
                    }),

                // 3. Склад
                Tables\Filters\Filter::make('stock_filter')
                    ->form([
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('min')->numeric()->label('Склад: от'),
                            \Filament\Forms\Components\TextInput::make('max')->numeric()->label('до'),
                        ]),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['min'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(stock_own) FROM sku_stocks WHERE sku_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) >= ?', [$val]))
                            ->when($data['max'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(stock_own) FROM sku_stocks WHERE sku_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) <= ?', [$val]));
                    }),

                // 4. Путь WB
                Tables\Filters\Filter::make('transit_wb_filter')
                    ->form([
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('min')->numeric()->label('Путь WB: от'),
                            \Filament\Forms\Components\TextInput::make('max')->numeric()->label('до'),
                        ]),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['min'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(in_transit_to_wb) FROM sku_stocks WHERE sku_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) >= ?', [$val]))
                            ->when($data['max'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(in_transit_to_wb) FROM sku_stocks WHERE sku_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) <= ?', [$val]));
                    }),

                // 5. Остаток FBO (WB)
                Tables\Filters\Filter::make('fbo_filter')
                    ->form([
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('min')->numeric()->label('На WB: от'),
                            \Filament\Forms\Components\TextInput::make('max')->numeric()->label('до'),
                        ]),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['min'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(quantity) FROM sku_warehouse_stocks WHERE sku_warehouse_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) >= ?', [$val]))
                            ->when($data['max'], fn ($q, $val) => $q->whereRaw('(SELECT SUM(quantity) FROM sku_warehouse_stocks WHERE sku_warehouse_stocks.sku_id IN (SELECT id FROM skus WHERE skus.product_id = products.id)) <= ?', [$val]));
                    }),

                // 6. Скорость продаж (шт/день)
                Tables\Filters\Filter::make('speed_filter')
                    ->label('Скорость продаж')
                    ->form([
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('min')->numeric()->label('Ср.прод: от'),
                            \Filament\Forms\Components\TextInput::make('max')->numeric()->label('до'),
                        ]),
                    ])
                    ->query(function ($query, array $data) {
                        $date30 = now()->subDays(30);
                        return $query
                            ->when($data['min'], fn ($q, $val) => $q->whereRaw('(SELECT COUNT(*) FROM sale_raws JOIN skus ON sale_raws.barcode = skus.barcode WHERE skus.product_id = products.id AND sale_raws.sale_date >= ?) >= ?', [$date30, $val * 30]))
                            ->when($data['max'], fn ($q, $val) => $q->whereRaw('(SELECT COUNT(*) FROM sale_raws JOIN skus ON sale_raws.barcode = skus.barcode WHERE skus.product_id = products.id AND sale_raws.sale_date >= ?) <= ?', [$date30, $val * 30]));
                    }),
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
            ->headerActions([
                // Импорт: Заказ Завод (B, D)
                Action::make('import_factory_order')
                    ->label('Импорт "Завод"')
                    ->icon('heroicon-o-building-office-2')
                    ->color('primary')
                    ->form([
                        FileUpload::make('attachment')->label('XLSX (B=Баркод, D=Кол-во)')->disk('local')->directory('temp-imports')->required(),
                    ])
                    ->action(fn (array $data) => self::processImport($data, 1, 3, 'at_factory')),

                // Импорт: Карго (E, F)
                Action::make('import_factory_transit')
                    ->label('Импорт "Карго"')
                    ->icon('heroicon-o-globe-alt')
                    ->color('info')
                    ->form([
                        FileUpload::make('attachment')->label('XLSX (E=Баркод, F=Кол-во)')->disk('local')->directory('temp-imports')->required(),
                    ])
                    ->action(fn (array $data) => self::processImport($data, 4, 5, 'in_transit_general')),

                // Импорт: Склад (L, I)
                Action::make('import_stock_own')
                    ->label('Импорт "Склад"')
                    ->icon('heroicon-o-home-modern')
                    ->color('success')
                    ->form([
                        FileUpload::make('attachment')->label('XLSX (L=Баркод, I=Кол-во)')->disk('local')->directory('temp-imports')->required(),
                    ])
                    ->action(fn (array $data) => self::processImport($data, 11, 8, 'stock_own')),

                // Импорт: Путь WB (E, F)
                Action::make('import_wb_transit')
                    ->label('Импорт "Путь WB"')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->form([
                        FileUpload::make('attachment')->label('XLSX (E=Баркод, F=Кол-во)')->disk('local')->directory('temp-imports')->required(),
                    ])
                    ->action(fn (array $data) => self::processImport($data, 4, 5, 'in_transit_to_wb')),
            ])
            ->paginated([10, 25, 50]);
    }

    // Вынес логику импорта в отдельный метод, чтобы не дублировать код
    protected static function processImport(array $data, int $barcodeIdx, int $qtyIdx, string $field)
    {
        $path = Storage::disk('local')->path($data['attachment']);
        $rows = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
            public function array(array $array) { return $array; }
        }, $path);

        if (empty($rows) || empty($rows[0])) return;

        $updatedCount = 0;
        foreach ($rows[0] as $index => $row) {
            if (!isset($row[$barcodeIdx]) || !isset($row[$qtyIdx])) continue;
            if ($index === 0 && !is_numeric($row[$qtyIdx])) continue;

            $barcode = (string) $row[$barcodeIdx];
            $quantity = (int) $row[$qtyIdx];

            if (empty($barcode)) continue;

            $sku = Sku::where('barcode', $barcode)->first();
            if ($sku) {
                $sku->stock()->updateOrCreate([], [$field => $quantity]);
                $updatedCount++;
            }
        }
        Storage::disk('local')->delete($data['attachment']);
        Notification::make()->title("Обновлено записей: {$updatedCount}")->success()->send();
    }

    public static function getHeaderWidgets(): array
    {
        return [
            LogisticsStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLogistics::route('/'),
        ];
    }
}