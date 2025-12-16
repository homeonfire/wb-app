<?php

namespace App\Filament\Pages;

use App\Models\Store;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Support\Str;

class RegisterStore extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Создать магазин';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Название магазина')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, \Filament\Forms\Set $set) {
                        // Автоматически генерируем slug из названия
                        $set('slug', Str::slug($state));
                    }),

                TextInput::make('slug')
                    ->label('URL (Slug)')
                    ->required()
                    ->unique(Store::class, 'slug')
                    ->readOnly(), // Можно сделать доступным для редактирования, если нужно
            ]);
    }

    protected function handleRegistration(array $data): Store
    {
        // Создаем магазин
        $store = Store::create($data);

        // Привязываем текущего пользователя к магазину
        // (Filament делает это сам через интерфейс HasTenants, но явно укажем для надежности)
        $store->users()->attach(auth()->user());

        return $store;
    }
}