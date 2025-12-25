<?php

namespace App\Filament\Resources\AdvertCampaignResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class StatisticsRelationManager extends RelationManager
{
    // Имя связи в модели AdvertCampaign (hasMany)
    protected static string $relationship = 'statistics';

    protected static ?string $title = 'Статистика по дням';
    
    // Иконка для вкладки (опционально)
    protected static ?string $icon = 'heroicon-o-chart-bar';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->required(),
                // Остальные поля можно добавить, если планируешь редактировать статистику вручную,
                // но обычно это только для чтения.
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->columns([
                // 1. Дата
                TextColumn::make('date')
                    ->label('Дата')
                    ->date('d.m.Y') // Формат ДД.ММ.ГГГГ
                    ->sortable()
                    ->weight('bold'),

                // 2. Показы
                TextColumn::make('views')
                    ->label('Показы')
                    ->numeric()
                    ->sortable(),

                // 3. Клики
                TextColumn::make('clicks')
                    ->label('Клики')
                    ->numeric()
                    ->sortable(),

                // 4. CTR (Обычно приходит от WB, но можно и пересчитать)
                TextColumn::make('ctr')
                    ->label('CTR')
                    ->suffix('%')
                    ->numeric(2)
                    ->sortable(),

                // 5. CPC (Цена клика)
                TextColumn::make('cpc')
                    ->label('CPC')
                    ->money('RUB')
                    ->sortable()
                    ->color('gray'),

                // 6. В корзину
                TextColumn::make('atbs')
                    ->label('В корзину')
                    ->numeric()
                    ->sortable(),

                // 7. Заказы
                TextColumn::make('orders')
                    ->label('Заказы')
                    ->numeric()
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                // 8. CPO (Стоимость заказа) - Считаем на лету
                TextColumn::make('cpo')
                    ->label('CPO')
                    ->state(function (Model $record) {
                        // Защита от деления на 0
                        if ($record->orders <= 0) return '-';
                        return $record->spend / $record->orders;
                    })
                    ->money('RUB')
                    ->color('warning'),

                // 9. Выручка
                TextColumn::make('sum_price')
                    ->label('Выручка')
                    ->money('RUB')
                    ->sortable()
                    ->color('success'),

                // 10. Расход
                TextColumn::make('spend')
                    ->label('Расход')
                    ->money('RUB')
                    ->sortable()
                    ->color('danger'),

                // 11. ДРР (Доля рекламных расходов) - Считаем на лету
                TextColumn::make('drr')
                    ->label('ДРР')
                    ->state(function (Model $record) {
                        // Защита от деления на 0
                        if ($record->sum_price <= 0) return '-';
                        
                        $drr = ($record->spend / $record->sum_price) * 100;
                        return round($drr, 1) . '%';
                    })
                    ->color(function ($state) {
                        if ($state === '-') return 'gray';
                        // Красный если ДРР > 20%, иначе зеленый
                        return ((float)$state > 20) ? 'danger' : 'success';
                    }),
            ])
            ->defaultSort('date', 'desc') // Свежие даты сверху
            ->filters([
                // Можно добавить фильтр по датам, если статистика очень большая
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('С'),
                        Forms\Components\DatePicker::make('until')->label('По'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q) => $q->where('date', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->where('date', '<=', $data['until']));
                    }),
            ])
            ->headerActions([
                // Кнопок создания не нужно, так как статистика грузится скриптом
            ])
            ->actions([
                // Редактировать тоже обычно не нужно
            ])
            ->bulkActions([
                // Удаление можно оставить на всякий случай
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}