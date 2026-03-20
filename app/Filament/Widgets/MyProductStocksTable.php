<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\Action;
use App\Filament\Resources\ProductResource;

class MyProductStocksTable extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Остатки по складам (Логистика)';
    protected static ?int $sort = 2;

    // 👇 СКРЫВАЕМ С ДАШБОРДА
    public static function canView(): bool
    {
        // Показывать ТОЛЬКО на странице "Мои товары"
        return request()->routeIs('filament.admin.pages.my-products');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->whereHas('users', function (Builder $query) {
                        $query->where('users.id', Auth::id());
                    })
                    ->with(['skus.stock', 'skus.warehouseStocks'])
            )
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('view', ['record' => $record]))
            ->columns([
                ImageColumn::make('main_image_url')->circular()->label('')->width(40),
                
                TextColumn::make('title')
                    ->label('Товар')
                    ->searchable()
                    ->limit(30)
                    ->description(fn (Product $record) => $record->vendor_code)
                    ->weight('bold'),

                // Логистика
                TextColumn::make('stock_factory')->label('Завод')->state(fn (Product $record) => $record->skus->sum(fn($s) => $s->stock?->at_factory ?? 0))->color('primary')->alignCenter(),
                TextColumn::make('stock_cargo')->label('Карго')->state(fn (Product $record) => $record->skus->sum(fn($s) => $s->stock?->in_transit_general ?? 0))->color('info')->alignCenter(),
                TextColumn::make('stock_own')->label('Склад')->state(fn (Product $record) => $record->skus->sum(fn($s) => $s->stock?->stock_own ?? 0))->color('success')->alignCenter(),
                TextColumn::make('stock_to_wb')->label('Путь WB')->state(fn (Product $record) => $record->skus->sum(fn($s) => $s->stock?->in_transit_to_wb ?? 0))->color('warning')->alignCenter(),
                TextColumn::make('stock_wb')->label('На WB (FBO)')->state(fn (Product $record) => $record->skus->flatMap->warehouseStocks->sum('quantity'))->weight('bold')->alignCenter(),
            ])
            ->actions([
                Action::make('view_sizes')
                    ->label('Размеры')
                    ->icon('heroicon-o-arrows-pointing-out')
                    ->modalHeading(fn (Product $record) => "Остатки: {$record->vendor_code}")
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(fn ($action) => $action->label('Закрыть'))
                    ->modalContent(fn (Product $record) => view('filament.resources.logistics.sizes-modal', ['record' => $record])),
            ])
            ->paginated([5, 10, 25]);
    }
}