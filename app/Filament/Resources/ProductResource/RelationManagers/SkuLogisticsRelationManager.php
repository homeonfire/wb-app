<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Sku;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;

class SkuLogisticsRelationManager extends RelationManager
{
    protected static string $relationship = 'skus';

    protected static ?string $title = 'Остатки и логистика по размерам (SKU)';
    protected static ?string $icon = 'heroicon-o-table-cells';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tech_size')
            ->paginated(false) 
            ->striped() 
            ->columns([
                Tables\Columns\TextColumn::make('tech_size')
                    ->label('Размер / Баркод')
                    ->description(fn (Sku $record) => $record->barcode)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('sales_per_day')
                    ->label('Продаж/день')
                    ->getStateUsing(function (Sku $record) {
                        $sales30 = $record->sales()
                            ->where('sale_date', '>=', now()->subDays(30))
                            ->count();
                        
                        return $sales30 > 0 ? number_format($sales30 / 30, 2) : '0.00';
                    })
                    ->alignCenter(),

                // 👇 Эта колонка берет сумму из warehouseStocks
                Tables\Columns\TextColumn::make('wb_stock')
                    ->label('Остаток WB')
                    ->getStateUsing(fn (Sku $record) => $record->warehouseStocks->sum('quantity'))
                    ->alignCenter()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('to_client')
                    ->label('К клиенту')
                    ->getStateUsing(fn (Sku $record) => $record->warehouseStocks->sum('in_way_to_client'))
                    ->alignCenter()
                    ->color('info'),

                Tables\Columns\TextColumn::make('from_client')
                    ->label('От клиента')
                    ->getStateUsing(fn (Sku $record) => $record->warehouseStocks->sum('in_way_from_client'))
                    ->alignCenter()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('stock.stock_own')
                    ->label('Свой склад')
                    ->default(0)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('stock.in_transit_to_wb')
                    ->label('В пути на WB')
                    ->default(0)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('stock.in_transit_general')
                    ->label('В пути (Склад)')
                    ->default(0)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('stock.at_factory')
                    ->label('На фабрике')
                    ->default(0)
                    ->alignCenter(),

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
                Tables\Actions\Action::make('view_warehouses')
                    ->label('Склады')
                    ->icon('heroicon-m-building-office-2')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Разбивка по складам: " . ($record->product->title ?? 'Товар'))
                    ->modalDescription(fn ($record) => "Размер: " . ($record->tech_size ?? '-') . " | Баркод: " . ($record->barcode ?? '-'))
                    ->modalSubmitAction(false) 
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(fn ($record) => new HtmlString(
                        Blade::render('
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500">
                                            <th class="py-3 font-semibold text-left">Название склада</th>
                                            <th class="py-3 font-semibold text-center w-32">На WB (FBO)</th>
                                            <th class="py-3 font-semibold text-center w-32">К клиенту</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @php
                                            // Группируем по ID склада, чтобы пустые имена не слипались в одну строку
                                            $grouped = $sku->warehouseStocks
                                                ->groupBy(function($item) {
                                                    return $item->warehouse_id ?? ($item->warehouse_name ?: "unknown");
                                                })
                                                ->map(function ($group) {
                                                    $first = $group->first();
                                                    // Если имя пустое, выводим ID склада
                                                    $name = !empty($first->warehouse_name) 
                                                        ? $first->warehouse_name 
                                                        : "Склад WB (ID: " . ($first->warehouse_id ?? "Неизвестно") . ")";
                                                    
                                                    return (object) [
                                                        "name" => $name,
                                                        "qty" => $group->sum("quantity"),
                                                        "in_way" => $group->sum("in_way_to_client")
                                                    ];
                                                })
                                                ->sortByDesc("qty");
                                        @endphp
                                        
                                        @forelse($grouped as $detail)
                                            <tr class="hover:bg-gray-50/50">
                                                <td class="py-3 text-left font-medium text-gray-900 dark:text-white">{{ $detail->name }}</td>
                                                <td class="py-3 text-center font-bold text-primary-600 dark:text-primary-400">{{ $detail->qty }} шт.</td>
                                                <td class="py-3 text-center font-semibold text-blue-600 dark:text-blue-400">{{ $detail->in_way }} шт.</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="py-4 text-center text-gray-400">Нет данных об остатках</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        ', ['sku' => $record])
                    ))
            ]);
    }
}