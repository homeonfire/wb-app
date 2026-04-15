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
use App\Filament\Resources\ProductResource\Widgets\ProductFunnelWidget;
use Filament\Tables\Enums\FiltersLayout;
use App\Livewire\ProductAnalyticsTable;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
// 👇 Псевдонимы, чтобы не путать компоненты Формы и Просмотра
use Filament\Infolists\Components\Grid as InfolistGrid;       
use Filament\Infolists\Components\Section as InfolistSection;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Товары';
    protected static ?string $modelLabel = 'Товар';
    protected static ?string $pluralModelLabel = 'Товары';

    // Привязка ресурса к текущему магазину (Tenant)
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
                                Forms\Components\Select::make('users')
                                    ->label('Ответственные менеджеры')
                                    ->relationship('users', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    // 👇 Добавь это, если список упорно пустой при открытии
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),
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

                // Здесь используем Section и Grid для ФОРМ (без префикса Infolist)
                Section::make('Сезонность / Актуальность')
                ->schema([
                    Repeater::make('seasonality')
                        ->label('Периоды актуальности')
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('start_month')
                                    ->label('С месяца')
                                    ->options([
                                        1 => 'Январь', 2 => 'Февраль', 3 => 'Март',
                                        4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
                                        7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь',
                                        10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
                                    ])
                                    ->required(),

                                Select::make('end_month')
                                    ->label('По месяц')
                                    ->options([
                                        1 => 'Январь', 2 => 'Февраль', 3 => 'Март',
                                        4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
                                        7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь',
                                        10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
                                    ])
                                    ->required(),
                            ]),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->createItemButtonLabel('Добавить период'),
                ])
                ->collapsible(),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('orders'))
            ->columns([
                Tables\Columns\ImageColumn::make('main_image_url')
                    ->label('Фото')
                    ->circular(),

                Tables\Columns\TextColumn::make('nm_id')
                    ->label('WB ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('vendor_code')
                    ->label('Артикул')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Заказы')
                    ->sortable() // Теперь сортировка работает, так как поле есть в запросе
                    ->badge()    // (Опционально) делает цифру красивым бейджиком
                    ->color(fn (string $state): string => $state > 0 ? 'success' : 'gray'), 

                Tables\Columns\TextColumn::make('brand')
                    ->label('Бренд')
                    ->searchable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                // 👇 НОВЫЕ КОЛОНКИ ABC И МАРЖИ 👇
                Tables\Columns\TextColumn::make('abc_class')
                    ->label('ABC')
                    ->badge()
                    ->sortable()
                    ->color(fn (?string $state): string => match ($state) {
                        'A' => 'success',
                        'B' => 'warning',
                        'C' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip('АВС-анализ по выручке за 30 дней'),

                Tables\Columns\TextColumn::make('margin_30d')
                    ->label('Маржа (30 дн)')
                    ->suffix('%')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state >= 30 ? 'success' : ($state > 0 ? 'warning' : 'danger')),

                Tables\Columns\TextColumn::make('revenue_30d')
                    ->label('Выручка (30 дн)')
                    ->money('RUB')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // 👆 КОНЕЦ НОВЫХ КОЛОНОК 👆

                Tables\Columns\TextInputColumn::make('cost_price')
                    ->label('Себестоимость')
                    ->type('number')
                    ->rules(['numeric', 'min:0']),
                
                Tables\Columns\TextColumn::make('fbo_stock')
                    ->label('FBO')
                    ->getStateUsing(fn (Product $record) => $record->skus->flatMap->warehouseStocks->sum('quantity'))
                    ->sortable(query: function ($query, string $direction) {
                        return $query;
                    }),
                    
                Tables\Columns\TextColumn::make('id')
                    ->label('Внутренний ID')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand')
                    ->label('Бренд')
                    ->options(fn () => Product::query()
                        ->where('store_id', \Filament\Facades\Filament::getTenant()->id)
                        ->distinct()
                        ->pluck('brand', 'brand')
                        ->toArray()
                    )
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('no_cost_price')
                    ->label('❗️ Нет себестоимости')
                    ->query(fn ($query) => $query->where('cost_price', 0))
                    ->toggle(),

                Tables\Filters\TernaryFilter::make('has_stock')
                    ->label('Остаток на WB')
                    ->placeholder('Все товары')
                    ->trueLabel('В наличии')
                    ->falseLabel('Закончились')
                    ->queries(
                        true: fn ($query) => $query->whereHas('skus.warehouseStocks', fn ($q) => $q->where('quantity', '>', 0)),
                        false: fn ($query) => $query->whereDoesntHave('skus.warehouseStocks', fn ($q) => $q->where('quantity', '>', 0)),
                    ),
                Tables\Filters\Filter::make('my_products')
                    ->label('👤 Показать мои товары')
                    ->query(function (Builder $query) {
                        // Ищем товары, у которых в связях (users) есть текущий ID
                        return $query->whereHas('users', function ($q) {
                            $q->where('users.id', auth()->id());
                        });
                    })
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
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
        $months = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
        ];

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
                                Infolists\Components\TextEntry::make('users.name')
                                    ->label('Менеджеры')
                                    ->badge()
                                    ->color('info')
                                    ->listWithLineBreaks() // Или ->separator(',')
                                    ->placeholder('Нет привязанных менеджеров'),
                            ]),
                    ]),

                // 2. Блок аналитики
                Infolists\Components\Section::make('P&L Аналитика')
                    ->schema([
                        Infolists\Components\Livewire::make(ProductPnLOverview::class)
                            ->data(fn (Product $record) => ['record' => $record]) 
                            ->columnSpanFull(),
                        
                        Infolists\Components\Livewire::make(ProductSalesChart::class)
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
                        Infolists\Components\Livewire::make(ProductAnalyticsTable::class)
                            ->data(fn (Product $record) => ['record' => $record])
                            ->columnSpanFull(),
                    ]),

                // 3. Сезонность
                Infolists\Components\Section::make('Сезонность / Актуальность')
                    ->schema([
                        RepeatableEntry::make('seasonality')
                            ->label('') 
                            ->schema([
                                // 👇 ИСПОЛЬЗУЕМ InfolistGrid ВМЕСТО Grid 👇
                                InfolistGrid::make(2)->schema([
                                    TextEntry::make('start_month')
                                        ->label('С месяца')
                                        ->formatStateUsing(fn ($state) => $months[$state] ?? $state),
                                    
                                    TextEntry::make('end_month')
                                        ->label('По месяц')
                                        ->formatStateUsing(fn ($state) => $months[$state] ?? $state),
                                ]),
                            ])
                            ->grid(2)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),       
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SkuLogisticsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'view' => Pages\ViewProduct::route('/{record}'),
        ];
    }
}