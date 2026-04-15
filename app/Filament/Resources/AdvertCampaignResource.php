<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdvertCampaignResource\Pages;
use App\Filament\Resources\AdvertCampaignResource\RelationManagers\StatisticsRelationManager;
use App\Models\AdvertCampaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Carbon\Carbon;
use App\Filament\Resources\ProductResource;

class AdvertCampaignResource extends Resource
{
    protected static ?string $model = AdvertCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Реклама';
    protected static ?string $modelLabel = 'Рекламная кампания';
    protected static ?string $pluralModelLabel = 'Рекламные кампании';

    protected static ?string $tenantOwnershipRelationshipName = 'store';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация о кампании')
                    ->schema([
                        Forms\Components\TextInput::make('advert_id')
                            ->label('ID кампании')
                            ->disabled(),
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required(),
                        Forms\Components\TextInput::make('daily_budget')
                            ->label('Бюджет (₽)')
                            ->numeric(),
                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options([
                                9 => 'Активна',
                                11 => 'Пауза',
                                7 => 'Завершена',
                            ]),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // 1. БАЗОВЫЙ ЗАПРОС (Общие суммы)
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['product'])
                ->withSum('statistics', 'views')
                ->withSum('statistics', 'clicks')
                ->withSum('statistics', 'atbs')
                ->withSum('statistics', 'orders')
                ->withSum('statistics', 'sum_price')
                ->withSum('statistics', 'spend')
            )
            ->columns([
                ImageColumn::make('product.main_image_url')
                    ->label('Товар')
                    ->square()
                    ->size(50),

                TextColumn::make('name')
                    ->label('Кампания')
                    ->description(fn (AdvertCampaign $record) => "ID: {$record->advert_id} • {$record->type_name}")
                    ->weight('bold')
                    ->searchable(['name', 'advert_id'])
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->name),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (AdvertCampaign $record) => $record->status_label)
                    ->color(fn (AdvertCampaign $record) => $record->status_color)
                    ->sortable(),

                // --- МЕТРИКИ ---

                TextColumn::make('views')
                    ->label('Показы')
                    ->state(fn (AdvertCampaign $record) => $record->filtered_views ?? $record->statistics_sum_views)
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('statistics_sum_views', $direction))
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('clicks')
                    ->label('Клики')
                    ->state(fn (AdvertCampaign $record) => $record->filtered_clicks ?? $record->statistics_sum_clicks)
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('statistics_sum_clicks', $direction))
                    ->placeholder('-'),

                TextColumn::make('ctr')
                    ->label('CTR')
                    ->state(function (AdvertCampaign $record) {
                        $clicks = $record->filtered_clicks ?? $record->statistics_sum_clicks ?? 0;
                        $views = $record->filtered_views ?? $record->statistics_sum_views ?? 0;
                        return $views > 0 ? round(($clicks / $views) * 100, 2) . '%' : '-';
                    }),

                TextColumn::make('atbs')
                    ->label('В корзину')
                    ->state(fn (AdvertCampaign $record) => $record->filtered_atbs ?? $record->statistics_sum_atbs)
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('statistics_sum_atbs', $direction))
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('orders')
                    ->label('Заказы')
                    ->state(fn (AdvertCampaign $record) => $record->filtered_orders ?? $record->statistics_sum_orders)
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('statistics_sum_orders', $direction))
                    ->color('success')
                    ->weight('bold')
                    ->placeholder('-'),

                // 👇 НОВЫЙ СТОЛБЕЦ: CPO (Cost Per Order)
                TextColumn::make('cpo')
                    ->label('CPO')
                    ->state(function (AdvertCampaign $record) {
                        $spend = $record->filtered_spend ?? $record->statistics_sum_spend ?? 0;
                        $orders = $record->filtered_orders ?? $record->statistics_sum_orders ?? 0;
                        
                        if ($orders <= 0) return '-';
                        
                        // Расход / Заказы
                        return $spend / $orders;
                    })
                    ->money('RUB')
                    ->placeholder('-'),

                TextColumn::make('sum_price')
                    ->label('Заказы (руб)')
                    ->state(fn (AdvertCampaign $record) => $record->filtered_sum_price ?? $record->statistics_sum_sum_price)
                    ->money('RUB')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('statistics_sum_sum_price', $direction))
                    ->color('success')
                    ->placeholder('-'),

                TextColumn::make('spend')
                    ->label('Расход')
                    ->state(fn (AdvertCampaign $record) => $record->filtered_spend ?? $record->statistics_sum_spend)
                    ->money('RUB')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('statistics_sum_spend', $direction))
                    ->color('danger')
                    ->placeholder('-'),

                TextColumn::make('drr')
                    ->label('ДРР')
                    ->state(function (AdvertCampaign $record) {
                        $spend = $record->filtered_spend ?? $record->statistics_sum_spend ?? 0;
                        $revenue = $record->filtered_sum_price ?? $record->statistics_sum_sum_price ?? 0;
                        
                        if ($revenue <= 0) return '-';
                        return round(($spend / $revenue) * 100, 1) . '%';
                    })
                    ->color(function ($state) {
                        if ($state === '-') return 'gray';
                        return ((float)$state > 20) ? 'danger' : 'success';
                    })
                    ->placeholder('-'),
            ])
            ->defaultSort('status', 'desc')
            ->filters([
                Filter::make('period')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('С даты')
                            ->default(now()->subDays(7)),
                        Forms\Components\DatePicker::make('until')
                            ->label('По дату')
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        if (!$from && !$until) {
                            return $query;
                        }

                        $dateFilter = fn($q) => $q
                            ->when($from, fn($subQ) => $subQ->where('date', '>=', $from))
                            ->when($until, fn($subQ) => $subQ->where('date', '<=', $until));

                        // 2. ФИЛЬТРОВАННЫЙ ЗАПРОС (Алиасы filtered_...)
                        return $query
                            ->withAggregate(['statistics as filtered_views' => $dateFilter], 'views', 'sum')
                            ->withAggregate(['statistics as filtered_clicks' => $dateFilter], 'clicks', 'sum')
                            ->withAggregate(['statistics as filtered_atbs' => $dateFilter], 'atbs', 'sum')
                            ->withAggregate(['statistics as filtered_orders' => $dateFilter], 'orders', 'sum')
                            ->withAggregate(['statistics as filtered_sum_price' => $dateFilter], 'sum_price', 'sum')
                            ->withAggregate(['statistics as filtered_spend' => $dateFilter], 'spend', 'sum');
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['from']) && empty($data['until'])) {
                            return null;
                        }
                        return 'Период: ' . 
                            ($data['from'] ? Carbon::parse($data['from'])->format('d.m.Y') : '...') . 
                            ' — ' . 
                            ($data['until'] ? Carbon::parse($data['until'])->format('d.m.Y') : '...');
                    }),

                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        9 => '🟢 Активна',
                        11 => '⏸ Пауза',
                        7 => '🏁 Завершена',
                        4 => '🆕 Готова к запуску',
                        -1 => '❌ Удалена',
                    ])
                    ->default(9)
                    ->native(false),

                // 👇 НОВЫЙ ФИЛЬТР "МОИ РК" 👇
                Filter::make('my_campaigns')
                    ->label('👤 Мои РК')
                    ->query(function (Builder $query) {
                        // 1. Получаем список nm_id товаров, привязанных к авторизованному менеджеру
                        $myProductNmIds = \App\Models\Product::whereHas('users', function ($q) {
                            $q->where('users.id', auth()->id());
                        })->pluck('nm_id');

                        // 2. Фильтруем кампании по этим nm_id
                        return $query->whereIn('nm_id', $myProductNmIds);
                    })
                    ->toggle(),
                // 👆 КОНЕЦ НОВОГО ФИЛЬТРА 👆
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('go_to_product')
                    ->label('К товару')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info') // Синий цвет, чтобы выделялось
                    ->url(fn (AdvertCampaign $record) => $record->product ? ProductResource::getUrl('view', ['record' => $record->product]) : null)
                    ->visible(fn (AdvertCampaign $record) => $record->product !== null),
                Tables\Actions\Action::make('wb_link')
                    ->label('WB')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (AdvertCampaign $record) => $record->nm_id ? "https://www.wildberries.ru/catalog/{$record->nm_id}/detail.aspx" : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => (bool) $record->nm_id)
                    ->color('gray')
                    ->iconButton(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StatisticsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdvertCampaigns::route('/'),
            'create' => Pages\CreateAdvertCampaign::route('/create'),
            'view' => Pages\ViewAdvertCampaign::route('/{record}'),
            'edit' => Pages\EditAdvertCampaign::route('/{record}/edit'),
        ];
    }
}