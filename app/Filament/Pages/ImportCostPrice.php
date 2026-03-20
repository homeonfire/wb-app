<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsCostPriceImport;
use Filament\Notifications\Notification;

class ImportCostPrice extends Page implements HasForms
{
    use InteractsWithForms;

    // Иконка и названия в меню
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationLabel = 'Импорт себестоимости';
    protected static ?string $title = 'Импорт себестоимости товаров';
    
    // Опционально: можно поместить в какую-то группу в боковом меню
    protected static ?string $navigationGroup = 'Склад и Финансы'; 

    protected static string $view = 'filament.pages.import-cost-price';

    // Сюда будут сохраняться данные формы
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Загрузка файла')
                    ->description('Загрузите Excel файл. Столбец A - артикул WB, Столбец D - себестоимость. Первая строка (заголовки) игнорируется.')
                    ->schema([
                        FileUpload::make('excel_file')
                            ->label('Excel файл (.xlsx, .xls, .csv)')
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

    // Функция, которая сработает при отправке формы
    public function importData(): void
    {
        $data = $this->form->getState();

        if (empty($data['excel_file'])) {
            return;
        }

        // В зависимости от настроек FileUpload, иногда может вернуть массив
        $fileName = is_array($data['excel_file']) ? current($data['excel_file']) : $data['excel_file'];
        
        $filePath = Storage::disk('local')->path($fileName);

        try {
            // Запускаем импорт
            Excel::import(new ProductsCostPriceImport, $filePath);

            Notification::make()
                ->title('Импорт успешно завершен!')
                ->body('Себестоимости товаров обновлены в базе данных.')
                ->success()
                ->send();

            // Очищаем форму после успешной загрузки
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