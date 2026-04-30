<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UserAssignedProductsWidget extends BaseWidget
{
    protected static ?string $heading = 'Выполнение плана по моим товарам';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // 1. Берем товары только текущего менеджера
                Product::query()
                    ->whereHas('users', fn (Builder $q) => $q->where('users.id', auth()->id()))
                    // Жадная загрузка, чтобы не было N+1 запросов при расчетах
                    ->with(['skus.warehouseStocks', 'plans' => function($q) {
                        // Берем план на текущий месяц
                        $q->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                    }])
            )
            ->columns([
                // --- 1. Арт. ВБ, Наш, Предмет ---
                Tables\Columns\TextColumn::make('nm_id')
                    ->label('Товар')
                    ->formatStateUsing(fn (Product $record) => "<b>{$record->title}</b><br><span class='text-xs text-gray-500'>WB: {$record->nm_id} | Арт: {$record->vendor_code}</span>")
                    ->html() // Разрешаем HTML, чтобы скомпоновать три поля в одну красивую ячейку
                    ->searchable(['nm_id', 'vendor_code', 'title'])
                    ->copyable(),

                // --- 2. Цены (Текущая / СПП) ---
                Tables\Columns\TextColumn::make('prices')
                    ->label('Цена / с СПП')
                    ->getStateUsing(function (Product $record) {
                        // Берем цену первого размера (SKU)
                        $price = $record->skus->first()->price ?? 0; 
                        $priceSpp = $price * 0.75; // <-- ЗАМЕНИ на реальную логику или поле с СПП
                        
                        return number_format($price, 0, '.', ' ') . ' ₽ / ' . number_format($priceSpp, 0, '.', ' ') . ' ₽';
                    })
                    ->color('gray'),

                // --- 3. % Выкупа ---
                Tables\Columns\TextColumn::make('buyout_percent')
                    ->label('% Выкупа')
                    // Если процент не хранится в Product, здесь будет твоя логика его получения
                    ->getStateUsing(fn (Product $record) => $record->buyout_percent ?? rand(35, 85)) 
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => $state >= 60 ? 'success' : ($state >= 40 ? 'warning' : 'danger')),

                // --- 4. Остатки ---
                Tables\Columns\TextColumn::make('fbo_stock')
                    ->label('Остатки')
                    ->getStateUsing(fn (Product $record) => $record->skus->flatMap->warehouseStocks->sum('quantity'))
                    ->badge()
                    ->color(fn ($state) => $state > 50 ? 'success' : ($state > 0 ? 'warning' : 'danger')),

                // --- 5. Заказы (Факт/План) + Прогресс-бар ---
                Tables\Columns\ViewColumn::make('orders_plan_fact')
                    ->label('Заказы за месяц')
                    ->view('filament.tables.columns.plan-fact')
                    ->getStateUsing(function (Product $record) {
                        $plan = $record->plans->first()->orders_plan ?? 0;
                        $fact = $record->orders_count_30d ?? 0; // <-- Укажи свое поле факта
                        $percent = $plan > 0 ? round(($fact / $plan) * 100) : ($fact > 0 ? 100 : 0);
                        
                        return ['fact' => $fact, 'plan' => $plan, 'percent' => $percent, 'unit' => 'шт.'];
                    }),

                // --- 6. Продажи (Факт/План) + Прогресс-бар ---
                Tables\Columns\ViewColumn::make('sales_plan_fact')
                    ->label('Продажи за месяц')
                    ->view('filament.tables.columns.plan-fact')
                    ->getStateUsing(function (Product $record) {
                        $plan = $record->plans->first()->sales_plan ?? 0;
                        $fact = $record->sales_count_30d ?? 0; // <-- Укажи свое поле факта
                        $percent = $plan > 0 ? round(($fact / $plan) * 100) : ($fact > 0 ? 100 : 0);
                        
                        return ['fact' => $fact, 'plan' => $plan, 'percent' => $percent, 'unit' => 'шт.'];
                    }),

                // --- 7. Маржа (Факт/План) + Прогресс-бар ---
                Tables\Columns\ViewColumn::make('margin_plan_fact')
                    ->label('Маржа за месяц')
                    ->view('filament.tables.columns.plan-fact')
                    ->getStateUsing(function (Product $record) {
                        $plan = $record->plans->first()->margin_plan ?? 0;
                        $fact = $record->margin_30d ?? 0; // <-- Укажи свое поле факта
                        $percent = $plan > 0 ? round(($fact / $plan) * 100) : ($fact > 0 ? 100 : 0);
                        
                        // Передаем 'unit' => '₽', чтобы в прогресс-баре были рубли
                        return ['fact' => $fact, 'plan' => $plan, 'percent' => $percent, 'unit' => '₽'];
                    }),
            ])
            ->paginated(false) 
            ->striped(); // Добавит легкую зебру для читаемости
    }
}