<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PlansRelationManager extends RelationManager
{
    protected static string $relationship = 'plans';
    protected static ?string $title = 'План продаж'; // Заголовок вкладки
    protected static ?string $modelLabel = 'План';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    // Выбор года (текущий + следующий)
                    Forms\Components\Select::make('year')
                        ->label('Год')
                        ->options([
                            now()->year => now()->year,
                            now()->addYear()->year => now()->addYear()->year,
                        ])
                        ->default(now()->year)
                        ->required(),

                    // Выбор месяца
                    Forms\Components\Select::make('month')
                        ->label('Месяц')
                        ->options([
                            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
                            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
                            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
                        ])
                        ->default(now()->month)
                        ->required(),
                ])->columns(2),

                Forms\Components\Group::make()->schema([
                    Forms\Components\TextInput::make('orders_plan')
                        ->label('План Заказов (шт)')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    Forms\Components\TextInput::make('sales_plan')
                        ->label('План Выкупов (шт)')
                        ->numeric()
                        ->default(0)
                        ->required(),
                ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('year')
            ->columns([
                Tables\Columns\TextColumn::make('year')
                    ->label('Год')
                    ->sortable(),

                Tables\Columns\TextColumn::make('month')
                    ->label('Месяц')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        '1' => 'Январь', '2' => 'Февраль', '3' => 'Март', '4' => 'Апрель',
                        '5' => 'Май', '6' => 'Июнь', '7' => 'Июль', '8' => 'Август',
                        '9' => 'Сентябрь', '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('orders_plan')
                    ->label('План Заказов')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('sales_plan')
                    ->label('План Выкупов')
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('month', 'desc') // Свежие месяцы сверху
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добавить план'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}