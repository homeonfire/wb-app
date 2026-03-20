<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsAssignManagerImport;
use Filament\Notifications\Notification;
use App\Models\User;

class ImportManagerProducts extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'Привязка товаров';
    protected static ?string $title = 'Массовая привязка товаров к менеджеру';
    protected static ?string $navigationGroup = 'Команда'; // Помести в нужную группу меню

    protected static string $view = 'filament.pages.import-manager-products';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Параметры импорта')
                    ->description('Выберите менеджера и загрузите Excel-файл. Артикул WB должен находиться в столбце B. Первая строка с заголовками будет пропущена.')
                    ->schema([
                        Select::make('user_id')
                            ->label('Выберите менеджера')
                            ->options(
                                // Собираем массив вида [id => "Имя (Почта)"]
                                User::all()->mapWithKeys(function ($user) {
                                    return [$user->id => "{$user->name} ({$user->email})"];
                                })
                            )
                            ->searchable() // Можно будет искать по имени или почте
                            ->preload()
                            ->required(),

                        FileUpload::make('excel_file')
                            ->label('Excel файл (.xlsx, .xls)')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes([
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv',
                            ])
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function importData(): void
    {
        $data = $this->form->getState();

        if (empty($data['excel_file']) || empty($data['user_id'])) {
            return;
        }

        $fileName = is_array($data['excel_file']) ? current($data['excel_file']) : $data['excel_file'];
        $filePath = Storage::disk('local')->path($fileName);

        try {
            // Запускаем импорт и ПЕРЕДАЕМ ID выбранного менеджера в класс импорта
            Excel::import(new ProductsAssignManagerImport($data['user_id']), $filePath);

            Notification::make()
                ->title('Привязка успешно завершена!')
                ->body('Товары из файла были привязаны к выбранному менеджеру.')
                ->success()
                ->send();

            // Очищаем форму
            $this->form->fill();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Произошла ошибка')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}