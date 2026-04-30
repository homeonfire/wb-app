<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ViewColumn;
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
use App\Filament\Resources\ProductResource;
use Carbon\Carbon;

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
                Product::query()
                    ->whereHas('users', function (Builder $query) {
                        $query->where('users.id', Auth::id());
                    })
                    // Подгружаем связи, чтобы таблица летала
                    ->with(['skus.warehouseStocks'])
            )
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('view', ['record' => $record]))
            ->columns([
                ImageColumn::make('main_image_url')
                    ->circular()
                    ->label('')
                    ->width(50),
                
                // 1. Арт. Вб, наш, предмет (Сгруппировано)
                TextColumn::make('title')
                    ->label('Товар')
                    ->formatStateUsing(fn (Product $record) => "<b>{$record->title}</b><br><span class='text-xs text-gray-500'>WB: {$record->nm_id} | Арт: {$record->vendor_code}</span>")
                    ->html()
                    ->searchable(['title', 'nm_id', 'vendor_code']),

                // 2. Цены (Текущая / СПП)
                TextColumn::make('prices')
                    ->label('Цена / с СПП')
                    ->getStateUsing(function (Product $record) {
                        $price = $record->skus->first()->price ?? 0;
                        $priceSpp = $price * 0.75; // Пример: скидка 25%. Замени на свое поле, если есть
                        return number_format($price, 0, '.', ' ') . ' ₽ / ' . number_format($priceSpp, 0, '.', ' ') . ' ₽';
                    })
                    ->color('gray'),

                // 3. % Выкупа
                TextColumn::make('buyout_percent')
                    ->label('% Выкупа')
                    ->getStateUsing(fn (Product $record) => $record->buyout_percent ?? rand(30, 85)) // Заглушка, подставь реальное поле
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => $state >= 50 ? 'success' : 'danger'),

                // 4. Остатки
                TextColumn::make('fbo_stock')
                    ->label('Остатки')
                    ->getStateUsing(fn (Product $record) => $record->skus->flatMap->warehouseStocks->sum('quantity'))
                    ->badge()
                    ->color(fn ($state) => $state > 50 ? 'success' : ($state > 0 ? 'warning' : 'danger')),

                // 5. ЗАКАЗЫ (Факт / План)
                ViewColumn::make('orders_stats')
                    ->label('Заказы (Ф/П)')
                    ->view('filament.tables.columns.plan-fact')
                    ->getStateUsing(function (Product $record) {
                        $filters = $this->tableFilters['date_filter'] ?? [];
                        $dateFrom = $filters['from'] ?? now()->startOfMonth();
                        $dateTo = $filters['to'] ?? now()->endOfMonth();

                        $fact = OrderRaw::where('nm_id', $record->nm_id)
                            ->whereBetween('order_date', [$dateFrom, $dateTo])
                            ->count();

                        $carbonDate = Carbon::parse($dateFrom);
                        $planRecord = $record->plans()
                            ->where('year', $carbonDate->year)
                            ->where('month', $carbonDate->month)
                            ->first();
                        
                        $plan = $planRecord ? $planRecord->orders_plan : 0;
                        $percent = $plan > 0 ? round(($fact / $plan) * 100) : ($fact > 0 ? 100 : 0);

                        return ['fact' => $fact, 'plan' => $plan, 'percent' => $percent, 'unit' => 'шт.'];
                    }),

                // 6. ПРОДАЖИ (Факт / План)
                ViewColumn::make('sales_stats')
                    ->label('Продажи (Ф/П)')
                    ->view('filament.tables.columns.plan-fact')
                    ->getStateUsing(function (Product $record) {
                        $filters = $this->tableFilters['date_filter'] ?? [];
                        $dateFrom = $filters['from'] ?? now()->startOfMonth();
                        $dateTo = $filters['to'] ?? now()->endOfMonth();

                        $fact = SaleRaw::where('nm_id', $record->nm_id)
                            ->whereBetween('sale_date', [$dateFrom, $dateTo])
                            ->count();

                        $carbonDate = Carbon::parse($dateFrom);
                        $planRecord = $record->plans()
                            ->where('year', $carbonDate->year)
                            ->where('month', $carbonDate->month)
                            ->first();
                        
                        $plan = $planRecord ? $planRecord->sales_plan : 0;
                        $percent = $plan > 0 ? round(($fact / $plan) * 100) : ($fact > 0 ? 100 : 0);

                        return ['fact' => $fact, 'plan' => $plan, 'percent' => $percent, 'unit' => 'шт.'];
                    }),

                // 7. МАРЖА (Факт / План)
                ViewColumn::make('margin_stats')
                    ->label('Маржа (Ф/П)')
                    ->view('filament.tables.columns.plan-fact')
                    ->getStateUsing(function (Product $record) {
                        $filters = $this->tableFilters['date_filter'] ?? [];
                        $dateFrom = $filters['from'] ?? now()->startOfMonth();
                        $dateTo = $filters['to'] ?? now()->endOfMonth();

                        // Считаем факт маржи: (Сумма продаж со скидкой) - (Кол-во продаж * Себестоимость)
                        $salesQuery = SaleRaw::where('nm_id', $record->nm_id)->whereBetween('sale_date', [$dateFrom, $dateTo]);
                        $revenue = $salesQuery->sum('price_with_disc');
                        $salesCount = $salesQuery->count();
                        
                        $fact = $revenue - ($salesCount * $record->cost_price);

                        $carbonDate = Carbon::parse($dateFrom);
                        $planRecord = $record->plans()
                            ->where('year', $carbonDate->year)
                            ->where('month', $carbonDate->month)
                            ->first();
                        
                        $plan = $planRecord ? $planRecord->margin_plan : 0;
                        $percent = $plan > 0 ? round(($fact / $plan) * 100) : ($fact > 0 ? 100 : 0);

                        return ['fact' => $fact, 'plan' => $plan, 'percent' => $percent, 'unit' => '₽'];
                    }),

                // 8. ОБЩИЙ % ВЫПОЛНЕНИЯ (Считаем по продажам как основному показателю)
                TextColumn::make('total_completion')
                    ->label('% Выполнения')
                    ->getStateUsing(function (Product $record) {
                        $filters = $this->tableFilters['date_filter'] ?? [];
                        $dateFrom = $filters['from'] ?? now()->startOfMonth();
                        
                        $fact = SaleRaw::where('nm_id', $record->nm_id)
                            ->whereBetween('sale_date', [$dateFrom, $filters['to'] ?? now()->endOfMonth()])
                            ->count();

                        $planRecord = $record->plans()->where('year', Carbon::parse($dateFrom)->year)->where('month', Carbon::parse($dateFrom)->month)->first();
                        $plan = $planRecord ? $planRecord->sales_plan : 0;

                        if ($plan <= 0) return '0%';
                        return round(($fact / $plan) * 100) . '%';
                    })
                    ->color(fn ($state) => (int)$state >= 100 ? 'success' : ((int)$state >= 70 ? 'warning' : 'danger'))
                    ->weight('bold'),
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
            MyProductStocksTable::class,
            MyProductAdvertsTable::class,
        ];
    }
}