<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class ProductPlansWidget extends BaseWidget
{
    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'План продаж';

    // 👇 1. Создаем вспомогательный метод с полями формы
    protected function getPlanFormSchema(): array
    {
        return [
            Forms\Components\Group::make()->schema([
                Forms\Components\Select::make('year')
                    ->label('Год')
                    ->options([
                        now()->year => now()->year,
                        now()->addYear()->year => now()->addYear()->year,
                    ])
                    ->default(now()->year)
                    ->required(),

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
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn () => $this->record->plans()->getQuery()
            )
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
            ->defaultSort('month', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добавить план')
                    // 👇 2. Явно передаем форму сюда
                    ->form($this->getPlanFormSchema())
                    ->using(function (array $data, string $model): Model {
                        return $this->record->plans()->create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    // 👇 3. И сюда тоже
                    ->form($this->getPlanFormSchema()),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}