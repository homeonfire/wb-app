<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExternalAdvertResource\Pages;
use App\Models\ExternalAdvert;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExternalAdvertResource extends Resource
{
    protected static ?string $model = ExternalAdvert::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone'; // Иконка рупора
    protected static ?string $navigationLabel = 'Внешняя реклама';
    protected static ?string $modelLabel = 'Закупку рекламы';
    protected static ?string $pluralModelLabel = 'Внешняя реклама';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Артикул WB (nm_id)') // Поменяли название
                        ->relationship('product', 'nm_id') // Ищем по артикулу WB
                        ->getOptionLabelFromRecordUsing(fn (Product $record) => "{$record->nm_id} ({$record->vendor_code})") // Показываем: 12345678 (Мой Артикул)
                        ->searchable(['nm_id', 'vendor_code']) // Ищем и по WB, и по внутреннему
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (!$state) {
                                $set('product_price', null);
                                return;
                            }
                            $product = Product::with('skus')->find($state);
                            $price = $product?->skus->first()?->price ?? 0;
                            $set('product_price', $price);
                        }),

                    Forms\Components\TextInput::make('product_price')
                        ->label('Стоимость артикула из БД')
                        ->disabled() // Запрещаем редактировать руками
                        ->dehydrated(false) // Важно: не пытаемся сохранить это поле в БД
                        ->numeric()
                        ->suffix('₽'),

                    Forms\Components\TextInput::make('blogger_link')
                        ->label('Ссылка на блоггера')
                        ->url()
                        ->required()
                        ->columnSpanFull(),
                ])->columns(2),

                Forms\Components\Section::make('Финансы и Формат')->schema([
                    Forms\Components\TextInput::make('ad_cost')
                        ->label('Стоимость рекламы')
                        ->numeric()
                        ->suffix('₽')
                        ->required(),

                    Forms\Components\TextInput::make('ad_spent')
                        ->label('Потрачено на рекламу')
                        ->numeric()
                        ->suffix('₽')
                        ->required(),

                    Forms\Components\Select::make('platform')
                        ->label('Площадка')
                        ->options([
                            'telegram' => 'Telegram',
                            'instagram' => 'Instagram',
                            'vk' => 'VK',
                            'youtube' => 'YouTube',
                            'tiktok' => 'TikTok',
                            'dzen' => 'Яндекс Дзен',
                            'pinterest' => 'Pinterest',
                        ])
                        ->required(),

                    Forms\Components\Select::make('formats')
                        ->label('Форматы')
                        ->multiple() // Позволяет выбрать несколько вариантов
                        ->options([
                            'stories' => 'Сторис',
                            'reels' => 'Рилс',
                            'post' => 'Пост',
                            'video' => 'Ролик',
                        ])
                        ->required(),

                    Forms\Components\DatePicker::make('release_date')
                        ->label('Дата выхода')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Статус')
                        ->options([
                            'not_published' => 'Не вышла',
                            'published' => 'Вышла',
                        ])
                        ->default('not_published')
                        ->required(),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.nm_id')
                    ->label('Артикул WB')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable() // Можно скопировать по клику
                    ->copyMessage('Артикул скопирован!'), // Сообщение об успешном копировании

                Tables\Columns\TextColumn::make('blogger_link')
                    ->label('Блоггер')
                    ->limit(20)
                    ->url(fn ($record) => $record->blogger_link, true) // Делает кликабельной ссылкой
                    ->color('primary'),

                Tables\Columns\TextColumn::make('platform')
                    ->label('Площадка')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'telegram' => 'info',          // Голубой
                        'vk' => 'primary',             // Синий
                        'instagram', 'youtube' => 'danger', // Красный
                        'dzen' => 'warning',           // Желтый
                        'tiktok' => 'gray',            // Серый
                        'pinterest' => 'success',      // Зеленый (или любой другой)
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'telegram' => 'Telegram',
                        'instagram' => 'Instagram',
                        'vk' => 'VK',
                        'youtube' => 'YouTube',
                        'tiktok' => 'TikTok',
                        'dzen' => 'Дзен',
                        'pinterest' => 'Pinterest',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('formats')
                    ->label('Форматы')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        // 1. Если пришла строка (JSON), декодируем её в массив
                        if (is_string($state)) {
                            $state = json_decode($state, true) ?? [$state];
                        }
                        
                        // 2. Если почему-то это всё ещё не массив, оборачиваем в массив
                        if (!is_array($state)) {
                            $state = (array) $state;
                        }

                        // 3. Переводим значения
                        return collect($state)->map(fn($item) => match ($item) {
                            'stories' => 'Сторис',
                            'reels' => 'Рилс',
                            'post' => 'Пост',
                            'video' => 'Ролик',
                            default => $item,
                        })->filter()->join(', ');
                    }),

                Tables\Columns\TextColumn::make('ad_cost')
                    ->label('Стоимость')
                    ->money('RUB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('release_date')
                    ->label('Дата выхода')
                    ->date('d.m.Y')
                    ->sortable(),

                // SelectColumn позволяет менять статус ПРЯМО из таблицы в 1 клик, не заходя в редактирование
                Tables\Columns\SelectColumn::make('status')
                    ->label('Статус')
                    ->options([
                        'not_published' => 'Не вышла',
                        'published' => 'Вышла',
                    ]),
            ])
            ->filters([
                // Здесь можно будет добавить фильтры по статусу или площадке
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExternalAdverts::route('/'),
            'create' => Pages\CreateExternalAdvert::route('/create'),
            'edit' => Pages\EditExternalAdvert::route('/{record}/edit'),
        ];
    }
}