<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SkusRelationManager extends RelationManager
{
    protected static string $relationship = 'skus';

    public function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('barcode')
                ->label('Штрихкод')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('tech_size')
                ->label('Размер'),
        ]);
}

public function table(Table $table): Table
{
    return $table
        ->recordTitleAttribute('barcode')
        ->columns([
            Tables\Columns\TextColumn::make('barcode')->label('Штрихкод'),
            Tables\Columns\TextColumn::make('tech_size')->label('Размер'),
        ])
        ->headerActions([
            Tables\Actions\CreateAction::make()->label('Добавить размер'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
}
}
