<?php

namespace App\Filament\Widgets;

use App\Models\SaleRaw;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Filament\Resources\ProductResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Facades\Filament;

class TopProductsTable extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Топ-5 товаров по выручке (30 дней)';

    public function table(Table $table): Table
    {
        $store = Filament::getTenant();
        return $table
            ->query(
                SaleRaw::query()
                    ->where('store_id', $store->id)
                    ->select('nm_id', DB::raw('SUM(price_with_disc) as total_revenue'), DB::raw('COUNT(*) as total_sales'))
                    ->where('sale_date', '>=', now()->subDays(30))
                    ->groupBy('nm_id')
                    ->orderByDesc('total_revenue')
                    ->limit(5)
            )
            // ❌ УДАЛИЛИ ->recordKey('nm_id') ОТСЮДА
            ->columns([
                Tables\Columns\ImageColumn::make('product_image')
                    ->label('')
                    ->circular()
                    ->getStateUsing(function ($record) {
                        $product = Product::where('nm_id', $record->nm_id)->first();
                        return $product?->main_image_url;
                    }),

                Tables\Columns\TextColumn::make('nm_id')
                    ->label('Товар')
                    ->description(function ($record) {
                        $product = Product::where('nm_id', $record->nm_id)->first();
                        return $product ? Str::limit($product->title, 40) : 'Неизвестный товар';
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('total_sales')
                    ->label('Продаж (шт)')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Выручка')
                    ->money('rub')
                    ->sortable()
                    ->alignRight()
                    ->weight('bold')
                    ->color('success'),
            ])
            ->recordUrl(function ($record) {
                // Ищем наш товар в БД по артикулу WB
                $product = Product::where('nm_id', $record->nm_id)->first();
                
                // Если товар существует в нашей базе, возвращаем ссылку на страницу его просмотра
                if ($product) {
                    return ProductResource::getUrl('view', ['record' => $product]);
                }
                
                // Если товара почему-то нет (например, продажа есть, а карточку еще не спарсили), строка будет некликабельной
                return null; 
            })
            ->paginated(false);
    }

    // 👇 ДОБАВЛЯЕМ ЭТОТ МЕТОД
    // Он говорит Filament'у: "В качестве уникального ID строки используй nm_id"
    public function getTableRecordKey($record): string
    {
        return (string) $record->nm_id;
    }
}