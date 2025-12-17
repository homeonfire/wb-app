<?php

namespace App\Filament\Resources\AdvertCampaignResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StatisticsRelationManager extends RelationManager
{
    protected static string $relationship = 'statistics'; // Имя метода связи в модели AdvertCampaign
    protected static ?string $title = 'Статистика по дням';
    protected static ?string $icon = 'heroicon-o-chart-bar';

    public function form(Form $form): Form
    {
        return $form->schema([
            // Форма не нужна, так как мы только смотрим данные
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('views')
                    ->label('Просмотры')
                    ->numeric()
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Всего')),

                Tables\Columns\TextColumn::make('clicks')
                    ->label('Клики')
                    ->numeric()
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Всего')),

                Tables\Columns\TextColumn::make('ctr')
                    ->label('CTR')
                    ->suffix('%')
                    ->numeric(2)
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Average::make()->label('Сред.')),

                Tables\Columns\TextColumn::make('cpc')
                    ->label('CPC (Цена клика)')
                    ->money('RUB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('spend')
                    ->label('Расход')
                    ->money('RUB')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('RUB')->label('Всего')),

                Tables\Columns\TextColumn::make('atbs')
                    ->label('В корзину')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('orders')
                    ->label('Заказы')
                    ->numeric()
                    ->sortable()
                    ->color('success')
                    ->weight('bold')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Всего')),

                Tables\Columns\TextColumn::make('sum_price')
                    ->label('Выручка')
                    ->money('RUB')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('RUB')->label('Всего')),
            ])
            ->defaultSort('date', 'desc') // Свежие даты сверху
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}