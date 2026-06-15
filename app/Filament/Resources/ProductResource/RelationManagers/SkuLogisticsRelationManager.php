<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Sku;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString; // 👇 Добавлен импорт для вывода HTML
use Illuminate\Support\Facades\Blade; // 👇 Добавлен импорт для рендеринга шаблона

class SkuLogisticsRelationManager extends RelationManager
{
    protected static string $relationship = 'skus';

    protected static ?string $title = 'Остатки и логистика по размерам (SKU)';
    protected static ?string $icon = 'heroicon-o-table-cells';

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
                        // Считаем продажи за 30 дней
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
                    ->color('info'), // Синий цвет

                // 5. ОТ КЛИЕНТА
                Tables\Columns\TextColumn::make('from_client')
                    ->label('От клиента')
                    ->getStateUsing(fn (Sku $record) => $record->warehouseStocks->sum('in_way_from_client'))
                    ->alignCenter()
                    ->color('warning'), // Оранжевый

                // --- ВНУТРЕННЯЯ ЛОГИСТИКА (из таблицы sku_stocks) ---
                
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
                // Формула: Остаток / Скорость продаж
                Tables\Columns\TextColumn::make('turnover')
                    ->label('Оборачиваемость')
                    ->getStateUsing(function (Sku $record) {
                        // 1. Считаем общий остаток (WB + Свой)
                        $wbStock = $record->warehouseStocks->sum('quantity');
                        $ownStock = $record->stock->stock_own ?? 0;
                        $totalStock = $wbStock + $ownStock;

                        // 2. Считаем скорость
                        $sales30 = $record->sales()
                            ->where('sale_date', '>=', now()->subDays(30))
                            ->count();
                        $speed = $sales30 / 30;

                        if ($speed <= 0) return '∞'; // Если продаж нет

                        $days = $totalStock / $speed;
                        return round($days);
                    })
                    ->color(fn ($state) => match(true) {
                         $state === '∞' => 'gray',
                         $state < 60 => 'success',   // Зеленый (хорошая оборачиваемость)
                         $state < 100 => 'warning', // Желтый
                         default => 'danger',       // Красный (залежи)
                    })
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->actions([
                // 👇 Полностью скопированная и адаптированная логика из Аналитики по товарам
                Tables\Actions\Action::make('view_warehouses')
                    ->label('Склады')
                    ->icon('heroicon-m-building-office-2')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Разбивка по складам: " . ($record->product->title ?? 'Товар'))
                    ->modalDescription(fn ($record) => "Размер: " . ($record->tech_size ?? '-') . " | Баркод: " . ($record->barcode ?? '-'))
                    ->modalSubmitAction(false) // Окно только для просмотра
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
                                            <tr class="hover:bg-gray-50/50">
                                                <td class="py-3 text-left font-medium text-gray-900 dark:text-white">{{ $detail->warehouse_name }}</td>
                                                <td class="py-3 text-center font-bold text-primary-600 dark:text-primary-400">{{ $detail->quantity }} шт.</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="py-4 text-center text-gray-400">Нет данных об остатках на физических складах</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        ', ['skuId' => $record->id]) // Передаем ID текущего SKU
                    ))
            ]);
    }
}