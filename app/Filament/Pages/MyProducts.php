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
use App\Models\SaleRaw; // ✅ Было SalesRaw, стало SaleRaw
use App\Models\Product;
use App\Filament\Widgets\MyStatsWidget; 


class MyProducts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Мои товары';
    protected static ?string $title = 'Моя статистика';
    protected static string $view = 'filament.pages.my-products';

    public $dateFrom;
    public $dateTo;

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth();
        $this->dateTo = now()->endOfMonth();
    }

    protected function getHeaderWidgets(): array
    {
        // Получаем nm_id товаров
        $nmIds = Auth::user()->products()->pluck('nm_id');

        // 1. ЗАКАЗЫ (OrderRaw -> order_date)
        $ordersCount = OrderRaw::whereIn('nm_id', $nmIds)
            ->whereBetween('order_date', [$this->dateFrom, $this->dateTo])
            ->count();

        // 2. ПРОДАЖИ (SaleRaw -> sale_date) ✅ ИСПРАВЛЕНО
        $salesCount = SaleRaw::whereIn('nm_id', $nmIds)
            ->whereBetween('sale_date', [$this->dateFrom, $this->dateTo]) 
            ->count();

        return [
            // Убедись, что виджет MyStatsWidget существует по этому пути
            // Если он в папке Pages/Widgets, поправь use сверху
            MyStatsWidget::make([
                'orders' => $ordersCount,
                'sales' => $salesCount,
            ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // 🛑 Временно выводим ВСЕ товары (а не только юзера), чтобы исключить падение из-за связи
                // Если сработает, значит проблема в Auth::user()->products()
                \App\Models\Product::query()->limit(5)
            )
            ->columns([
                ImageColumn::make('main_image_url')
                    ->circular()
                    ->label('')
                    ->width(40),
                
                TextColumn::make('title')
                    ->label('Товар')
                    ->searchable()
                    ->limit(30)
                    ->description(fn (Product $record) => $record->vendor_code),

                TextColumn::make('test_debug')
                    ->label('ДЕБАГ')
                    ->state(function (Product $record) {
                        
                        // 1. Проверяем, существует ли Product ID (Должен сработать)
                        return "Товар ID: " . $record->id;

                        /*
                        // 2. Если (1) сработало, то проверяем запросы к сырым данным
                        $fact = \App\Models\OrderRaw::where('nm_id', $record->nm_id)->count();
                        return "Заказов: " . $fact;

                        // 3. Если (2) сработало, проверяем ПЛАН
                        $plan_count = $record->plans()->count();
                        return "Планов: " . $plan_count;
                        */
                    }),
            ]);
    }
}