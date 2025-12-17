<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdvertCampaignResource\Pages;
use App\Models\AdvertCampaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action; // 👈 Не забудьте добавить этот импорт
use App\Filament\Resources\AdvertCampaignResource\RelationManagers\StatisticsRelationManager;

class AdvertCampaignResource extends Resource
{
    protected static ?string $model = AdvertCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Реклама';
    protected static ?string $pluralModelLabel = 'Рекламные кампании';
    
    protected static ?string $tenantOwnershipRelationshipName = 'store';

    // 👇 Добавляем этот метод (если его нет) или обновляем его
    public static function getRelations(): array
    {
        return [
            StatisticsRelationManager::class, // 👈 Подключаем таблицу статистики
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название')
                    ->required(),
                Forms\Components\TextInput::make('advert_id')
                    ->label('ID кампании')
                    ->disabled(),
                Forms\Components\TextInput::make('daily_budget')
                    ->label('Бюджет')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('advert_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->limit(40)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn (AdvertCampaign $record) => $record->type_name)
                    ->badge()
                    ->color(fn (string $state, AdvertCampaign $record) => match ($record->type) {
                        8 => 'success', // Авто
                        6 => 'info',    // Поиск
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (AdvertCampaign $record) => $record->status_name)
                    ->badge()
                    ->color(fn (string $state, AdvertCampaign $record) => match ($record->status) {
                        9 => 'success',  // Идут показы
                        11 => 'warning', // Пауза
                        7 => 'gray',     // Архив
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('daily_budget')
                    ->label('Бюджет')
                    ->money('RUB')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        9 => 'Активна',
                        11 => 'Пауза',
                        7 => 'Архив',
                    ]),
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        8 => 'Автоматическая',
                        6 => 'Поиск',
                        5 => 'Карточка',
                        4 => 'Каталог',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // 👇 ДОБАВЛЕННОЕ ДЕЙСТВИЕ ДЛЯ ПРОСМОТРА JSON
                Action::make('json_view')
                    ->label('JSON')
                    ->icon('heroicon-o-code-bracket') // Иконка кода
                    ->color('gray')
                    ->modalHeading(fn (AdvertCampaign $record) => "Сырые данные: {$record->name} (ID: {$record->advert_id})")
                    ->form([
                        Forms\Components\Textarea::make('raw_content')
                            ->label('')
                            ->rows(20) // Высота окна
                            ->default(fn (AdvertCampaign $record) => 
                                // Форматируем JSON красиво и сохраняем кириллицу
                                json_encode($record->raw_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                            )
                            ->extraAttributes(['class' => 'font-mono text-xs']) // Моноширинный шрифт
                            ->readOnly(), // Только для чтения
                    ])
                    ->modalSubmitAction(false) // Убираем кнопку "Сохранить"
                    ->modalCancelAction(fn ($action) => $action->label('Закрыть')),

            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdvertCampaigns::route('/'),
            // 👇 Добавляем маршрут просмотра
            'view' => Pages\ViewAdvertCampaign::route('/{record}'), 
        ];
    }
}