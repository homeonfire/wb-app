<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use App\Filament\Resources\ProductResource\Widgets\ProductPnLOverview;
use App\Filament\Resources\ProductResource\Widgets\ProductSalesChart;
use App\Filament\Resources\ProductResource\Widgets\ProductFunnelWidget; // <--- Импорт
use Filament\Tables\Enums\FiltersLayout;
use App\Livewire\ProductAnalyticsTable;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Товары';
    protected static ?string $modelLabel = 'Товар';
    protected static ?string $pluralModelLabel = 'Товары';

    // ЭТО ВАЖНО: Привязка ресурса к текущему магазину (Tenant)
    protected static ?string $tenantOwnershipRelationshipName = 'store';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Основная информация')
                            ->schema([
                                Forms\Components\TextInput::make('nm_id')
                                    ->label('Артикул WB')
                                    ->numeric()
                                    ->required()
                                    ->unique(ignoreRecord: true),

                                Forms\Components\TextInput::make('vendor_code')
                                    ->label('Артикул продавца')
                                    ->required(),

                                Forms\Components\TextInput::make('brand')
                                    ->label('Бренд'),

                                Forms\Components\TextInput::make('title')
                                    ->label('Название')
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpan(2),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Финансы')
                            ->schema([
                                Forms\Components\TextInput::make('cost_price')
                                    ->label('Себестоимость (₽)')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->default(0),
                            ]),

                        Forms\Components\Section::make('Фото')
                            ->schema([
                                Forms\Components\FileUpload::make('main_image_url')
                                    ->label('Фото')
                                    ->image()
                                    ->avatar() // Круглое превью
                                    ->imageEditor(),
                            ]),
                    ])->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('main_image_url')
                    ->label('Фото')
                    ->circular(),

                // Добавляем searchable() для глобального поиска
                Tables\Columns\TextColumn::make('nm_id')
                    ->label('WB ID')
                    ->searchable() // <--- Ищет по ID
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('vendor_code')
                    ->label('Артикул')
                    ->searchable() // <--- Ищет по Артикулу
                    ->sortable(),

                Tables\Columns\TextColumn::make('brand')
                    ->label('Бренд')
                    ->searchable(), // <--- Ищет по Бренду

                Tables\Columns\TextColumn::make('title')
                    ->label('Название')
                    ->searchable() // <--- Ищет по Названию
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true), // Скрыто по умолчанию, можно включить

                Tables\Columns\TextInputColumn::make('cost_price')
                    ->label('Себестоимость')
                    ->type('number')
                    ->rules(['numeric', 'min:0']),
                
                // Добавим колонки для наглядности фильтров
                Tables\Columns\TextColumn::make('fbo_stock')
                    ->label('FBO')
                    ->getStateUsing(fn (Product $record) => $record->skus->flatMap->warehouseStocks->sum('quantity'))
                    ->sortable(query: function ($query, string $direction) {
                        // Сортировка по связанной сумме сложная, пока оставим стандартную
                        return $query;
                    }),
            ])
            ->filters([
                // 1. Фильтр по Бренду (Выпадающий список)
                Tables\Filters\SelectFilter::make('brand')
                    ->label('Бренд')
                    ->options(fn () => Product::query()
                        ->where('store_id', \Filament\Facades\Filament::getTenant()->id) // Только бренды этого магазина
                        ->distinct()
                        ->pluck('brand', 'brand') // key = value
                        ->toArray()
                    )
                    ->searchable() // Можно вводить название бренда
                    ->preload(), // Загружаем список сразу

                // 2. Фильтр "Нет себестоимости" (Важно для P&L!)
                Tables\Filters\Filter::make('no_cost_price')
                    ->label('❗️ Нет себестоимости')
                    ->query(fn ($query) => $query->where('cost_price', 0))
                    ->toggle(), // Вид переключателя (Toggle)

                // 3. Фильтр "Наличие на WB" (Есть / Нет)
                Tables\Filters\TernaryFilter::make('has_stock')
                    ->label('Остаток на WB')
                    ->placeholder('Все товары')
                    ->trueLabel('В наличии')
                    ->falseLabel('Закончились')
                    ->queries(
                        // Ищем товары, у которых есть SKU, у которых есть стоки > 0
                        true: fn ($query) => $query->whereHas('skus.warehouseStocks', fn ($q) => $q->where('quantity', '>', 0)),
                        false: fn ($query) => $query->whereDoesntHave('skus.warehouseStocks', fn ($q) => $q->where('quantity', '>', 0)),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    // Массовое изменение себестоимости (Бонус)
                    Tables\Actions\BulkAction::make('update_cost')
                        ->label('Задать себестоимость')
                        ->form([
                            Forms\Components\TextInput::make('cost_price')
                                ->label('Новая себестоимость')
                                ->numeric()
                                ->required(),
                        ])
                        ->action(function (array $data, \Illuminate\Database\Eloquent\Collection $records) {
                            $records->each->update(['cost_price' => $data['cost_price']]);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
{
    return $infolist
        ->schema([
            // 1. Блок основной информации
            Infolists\Components\Section::make('Карточка товара')
                ->columns(3)
                ->schema([
                    // Фото
                    Infolists\Components\ImageEntry::make('main_image_url')
                        ->label('')
                        ->height(200)
                        ->columnSpan(1),

                    // Данные
                    Infolists\Components\Group::make()
                        ->columnSpan(2)
                        ->schema([
                            Infolists\Components\TextEntry::make('title')
                                ->label('Название')
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                ->weight('bold'),

                            Infolists\Components\Grid::make(3)
                                ->schema([
                                    Infolists\Components\TextEntry::make('brand')->label('Бренд'),
                                    Infolists\Components\TextEntry::make('vendor_code')->label('Артикул продавца'),
                                    Infolists\Components\TextEntry::make('nm_id')->label('WB ID')->copyable(),
                                ]),

                            // Кнопка-ссылка на WB
                            Infolists\Components\Actions::make([
                                Infolists\Components\Actions\Action::make('open_wb')
                                    ->label('Открыть на Wildberries')
                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                    ->url(fn (Product $record) => "https://www.wildberries.ru/catalog/{$record->nm_id}/detail.aspx")
                                    ->openUrlInNewTab()
                                    ->button(),
                            ]),
                        ]),
                ]),

            // 2. Блок аналитики (Встраиваем виджеты сюда, чтобы они были ПЕРЕД размерами)
            // 2. Блок аналитики
                Infolists\Components\Section::make('P&L Аналитика')
                    ->schema([
                        // Карточки с цифрами
                        Infolists\Components\Livewire::make(ProductPnLOverview::class)
                            // ПРАВИЛЬНО: Функция возвращает массив
                            ->data(fn (Product $record) => ['record' => $record]) 
                            ->columnSpanFull(),
                        
                        // График
                        Infolists\Components\Livewire::make(ProductSalesChart::class)
                            // ПРАВИЛЬНО: Функция возвращает массив
                            ->data(fn (Product $record) => ['record' => $record])
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Воронка продаж (30 дней)')
                    ->schema([
                        Infolists\Components\Livewire::make(ProductFunnelWidget::class)
                            ->data(fn (Product $record) => ['record' => $record])
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Детальная аналитика (Воронка)')
                    ->schema([
                        // Filament позволяет встраивать Livewire компоненты напрямую
                        Infolists\Components\Livewire::make(ProductAnalyticsTable::class)
                            ->data(fn (Product $record) => ['record' => $record])
                            ->columnSpanFull(),
                    ]),
        ]);
}

    public static function getRelations(): array
    {
        return [
            // Здесь мы подключим управление размерами (SKU)
            //RelationManagers\SkusRelationManager::class,
            RelationManagers\SkuLogisticsRelationManager::class,
        ];
    }

    public static function getPages(): array
{
    return [
        'index' => Pages\ListProducts::route('/'),
        'create' => Pages\CreateProduct::route('/create'),
        'edit' => Pages\EditProduct::route('/{record}/edit'),
        'view' => Pages\ViewProduct::route('/{record}'), // <--- ДОБАВИЛИ ЭТУ СТРОКУ
    ];
}
}