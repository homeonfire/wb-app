<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UserAssignedProductsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Мои товары (План / Факт)';
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                auth()->user()->managedProducts()->getQuery()
                    ->where('products.store_id', filament()->getTenant()->id)
                    ->select('products.*')
                    ->with(['orders', 'sales', 'plans']) // Связи теперь ведут на OrderRaw/SaleRaw
            )
            ->columns([
                Tables\Columns\ImageColumn::make('main_image_url')
                    ->label('')
                    ->circular()
                    ->width(40),

                Tables\Columns\TextColumn::make('title')
                    ->label('Товар')
                    ->description(fn (Product $record) => $record->vendor_code)
                    ->limit(25)
                    ->tooltip(fn (Product $record) => $record->title),

                // 1. ЗАКАЗЫ (Штуки)
                Tables\Columns\TextColumn::make('orders_fact')
                    ->label('Заказы (Шт)')
                    ->state(function (Product $record) {
                        // ФАКТ: Считаем количество (count)
                        $fact = $record->orders
                            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
                            ->count();

                        $plan = $record->plans->first()?->orders_plan ?? 0;

                        if ($plan <= 0) return $fact;

                        return round(($fact / $plan) * 100) . '%';
                    })
                    ->badge()
                    ->color(fn (string $state) => (int)$state >= 100 ? 'success' : ((int)$state >= 80 ? 'info' : 'warning'))
                    ->description(function (Product $record) {
                        $fact = $record->orders
                            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
                            ->count();
                        $plan = $record->plans->first()?->orders_plan ?? 0;
                        
                        return number_format($fact, 0, '', ' ') . ' / ' . number_format($plan, 0, '', ' ') . ' шт.';
                    }),

                // 2. ВЫКУПЫ (Рубли)
                Tables\Columns\TextColumn::make('sales_fact')
                    ->label('Выкупы (Руб)')
                    ->state(function (Product $record) {
                        // ФАКТ: Сумма finished_price
                        $fact = $record->sales
                            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
                            ->sum('finished_price');

                        $plan = $record->plans->first()?->sales_plan ?? 0;

                        if ($plan <= 0) return number_format($fact, 0, '.', ' ') . ' ₽';

                        return round(($fact / $plan) * 100) . '%';
                    })
                    ->badge()
                    ->color(fn (string $state) => (int)$state >= 100 ? 'success' : 'danger')
                    ->description(function (Product $record) {
                        $fact = $record->sales
                            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
                            ->sum('finished_price');
                        $plan = $record->plans->first()?->sales_plan ?? 0;

                        return number_format($fact, 0, '.', ' ') . ' ₽ / ' . number_format($plan, 0, '.', ' ') . ' ₽';
                    }),
            ])
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }

    public static function canView(): bool
    {
        return auth()->user()->managedProducts()
            ->where('products.store_id', filament()->getTenant()->id)
            ->exists();
    }
}