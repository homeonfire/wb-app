<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\AdvertCampaign;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;

class MyProductAdvertsTable extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'Мои Рекламные кампании';
    
    // Сортировка 3, чтобы этот блок был в самом низу (после остатков)
    protected static ?int $sort = 3;

    // Скрываем с главной панели (Дашборда)
    public static function canView(): bool
    {
        return request()->routeIs('filament.admin.pages.my-products');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AdvertCampaign::query()
                    // Фильтруем кампании, которые привязаны к товарам текущего менеджера
                    ->whereIn('nm_id', function($query) {
                        $query->select('nm_id')
                              ->from('products')
                              ->join('product_user', 'products.id', '=', 'product_user.product_id')
                              ->where('product_user.user_id', Auth::id());
                    })
                    // Сортируем: сначала активные (статус 9), потом остальные
                    ->orderByRaw('CASE WHEN status = 9 THEN 1 ELSE 2 END')
                    ->orderByDesc('id')
            )
            ->columns([
                TextColumn::make('advert_id')
                    ->label('ID Кампании')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->weight('bold')
                    ->limit(40),

                TextColumn::make('nm_id')
                    ->label('Артикул товара')
                    ->searchable()
                    ->copyable()
                    ->url(fn ($record) => "https://www.wildberries.ru/catalog/{$record->nm_id}/detail.aspx", true)
                    ->color('primary'),

                TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn ($state) => match($state) {
                        4 => 'Каталог',
                        5 => 'Карточка',
                        6 => 'Поиск',
                        8 => 'Авто',
                        9 => 'Поиск + Каталог',
                        default => 'Тип '.$state
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        8 => 'primary', // Авто - фиолетовый
                        6 => 'info',    // Поиск - синий
                        9 => 'success', // Поиск+Каталог - зеленый
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn ($state) => match($state) {
                        9 => 'Идут показы',
                        11 => 'Пауза',
                        7 => 'Архив/Завершен',
                        default => $state
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        9 => 'success',  // Активна
                        11 => 'warning', // Пауза
                        7 => 'danger',   // Архив
                        default => 'gray',
                    }),

                TextColumn::make('daily_budget')
                    ->label('Бюджет (день)')
                    ->money('rub')
                    ->alignRight(),
                
                TextColumn::make('create_time')
                    ->label('Создана')
                    ->dateTime('d.m.Y')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('open_wb_cabinet')
                    ->label('В кабинет')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn ($record) => "https://cmp.wildberries.ru/campaigns/list/all/edit/campaign/{$record->advert_id}")
                    ->openUrlInNewTab()
                    ->color('gray'),
            ])
            ->paginated([5, 10, 25]);
    }
}