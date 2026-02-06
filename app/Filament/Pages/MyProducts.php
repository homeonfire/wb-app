<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Auth;
use App\Models\OrderRaw; 
use App\Models\SaleRaw;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use App\Filament\Widgets\MyPersonalStatsWidget;
use App\Filament\Widgets\MyProductStocksTable;
use App\Filament\Resources\ProductResource\Widgets\MyProductAdvertsTable;

class MyProducts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Мои товары';
    protected static ?string $title = 'Моя статистика';
    protected static ?string $slug = 'my-products';
    protected static string $view = 'filament.pages.my-products';

    protected function getHeaderWidgets(): array
    {
        return [
            MyPersonalStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()->whereHas('users', function (Builder $query) {
                    $query->where('users.id', Auth::id());
                })
            )
            ->columns([
                ImageColumn::make('main_image_url')
                    ->circular()
                    ->label('')
                    ->width(50),
                
                TextColumn::make('title')
                    ->label('Товар')
                    ->searchable()
                    ->limit(30)
                    ->description(fn (Product $record) => $record->vendor_code . ' / ' . $record->brand)
                    ->tooltip(fn (Product $record) => $record->title)
                    ->weight('bold'),

                // ЗАКАЗЫ (Факт / План)
                TextColumn::make('orders_stats')
                    ->label('Заказы (Факт / План)')
                    ->state(function (Product $record) {
                        $filters = $this->tableFilters['date_filter'] ?? [];
                        $dateFrom = $filters['from'] ?? now()->startOfMonth();
                        $dateTo = $filters['to'] ?? now()->endOfMonth();

                        $fact = OrderRaw::where('nm_id', $record->nm_id)
                            ->whereBetween('order_date', [$dateFrom, $dateTo])
                            ->count();

                        $carbonDate = \Carbon\Carbon::parse($dateFrom);
                        $planRecord = $record->plans()
                            ->where('year', $carbonDate->year)
                            ->where('month', $carbonDate->month)
                            ->first();
                        
                        $plan = $planRecord ? $planRecord->orders_plan : 0;

                        return "{$fact} / {$plan}";
                    })
                    ->badge()
                    ->color(fn ($state) => 
                        (int)explode(' / ', $state)[0] >= (int)explode(' / ', $state)[1] && (int)explode(' / ', $state)[1] > 0 
                        ? 'success' : 'warning'
                    ),

                // ВЫКУПЫ (Факт / План)
                TextColumn::make('sales_stats')
                    ->label('Выкупы (Факт / План)')
                    ->state(function (Product $record) {
                        $filters = $this->tableFilters['date_filter'] ?? [];
                        $dateFrom = $filters['from'] ?? now()->startOfMonth();
                        $dateTo = $filters['to'] ?? now()->endOfMonth();

                        $fact = SaleRaw::where('nm_id', $record->nm_id)
                            ->whereBetween('sale_date', [$dateFrom, $dateTo])
                            ->count();

                        $carbonDate = \Carbon\Carbon::parse($dateFrom);
                        $planRecord = $record->plans()
                            ->where('year', $carbonDate->year)
                            ->where('month', $carbonDate->month)
                            ->first();
                        
                        $plan = $planRecord ? $planRecord->sales_plan : 0;

                        return "{$fact} / {$plan}";
                    })
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                Filter::make('date_filter')
                    ->form([
                        DatePicker::make('from')->label('С даты')->default(now()->startOfMonth()),
                        DatePicker::make('to')->label('По дату')->default(now()->endOfMonth()),
                    ])
                    ->query(fn (Builder $query) => $query), 
            ])
            ->paginated([10, 25, 50]);
    }

    protected function getFooterWidgets(): array
    {
        return [
            MyProductStocksTable::class,  // 1. Таблица остатков
            MyProductAdvertsTable::class, // 2. Таблица рекламы (Будет ниже)
        ];
    }
}