<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\ProductAnalytic;
use Filament\Widgets\Widget;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;

class UnitEconomicsCalculator extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.resources.product-resource.widgets.unit-economics-calculator';
    protected int | string | array $columnSpan = 'full';

    // Получаем текущий товар из Infolist
    public ?Model $record = null;

    public ?array $data = [];

    public function mount(): void
    {
        // 1. Пытаемся получить реальный процент выкупа из воронки за последние 30 дней
        $buyoutPercent = 30; // Дефолт
        if ($this->record) {
            $stats = ProductAnalytic::where('nm_id', $this->record->nm_id)
                ->where('date', '>=', now()->subDays(30))
                ->selectRaw('SUM(orders_count) as orders, SUM(buyouts_count) as buyouts')
                ->first();

            if ($stats && $stats->orders > 0) {
                $buyoutPercent = round(($stats->buyouts / $stats->orders) * 100, 1);
            }
        }

        // 2. Предзаполняем форму
        $this->form->fill([
            'sell_price' => 3500, // Сюда можно подтягивать текущую цену по API, пока ставим заглушку
            'cost_price' => $this->record->cost_price ?? 0,
            'buyout_percent' => $buyoutPercent,
            'commission_percent' => 25, // Стандартная комиссия, можно менять
            'logistics_cost' => 65,     // Стандартная логистика WB
            'return_logistics_cost' => 33, // Стандартная обратная логистика
            'tax_percent' => 7,         // Налог (УСН 6% + 1% свыше 300к)
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Интерактивный калькулятор Юнит-Экономики')
                    ->description('Изменяйте значения ниже, чтобы моментально увидеть, как изменится маржа и ROI. Данные по умолчанию берутся из статистики товара.')
                    ->icon('heroicon-m-calculator')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('sell_price')
                                ->label('Цена на сайте (₽)')
                                ->numeric()
                                ->live(debounce: '500ms')
                                ->extraInputAttributes(['class' => 'font-bold text-primary-600']),
                                
                            TextInput::make('cost_price')
                                ->label('Себестоимость (₽)')
                                ->numeric()
                                ->live(debounce: '500ms'),

                            TextInput::make('buyout_percent')
                                ->label('Процент выкупа (%)')
                                ->numeric()
                                ->live(debounce: '500ms'),

                            TextInput::make('tax_percent')
                                ->label('Налог (%)')
                                ->numeric()
                                ->live(debounce: '500ms'),

                            TextInput::make('commission_percent')
                                ->label('Комиссия WB (%)')
                                ->numeric()
                                ->live(debounce: '500ms'),

                            TextInput::make('logistics_cost')
                                ->label('Логистика к клиенту (₽)')
                                ->numeric()
                                ->live(debounce: '500ms'),

                            TextInput::make('return_logistics_cost')
                                ->label('Обратная логистика (₽)')
                                ->numeric()
                                ->live(debounce: '500ms'),
                        ]),

                        // --- БЛОК РЕЗУЛЬТАТОВ ---
                        Grid::make(3)->schema([
                            Placeholder::make('profit')
                                ->label('Чистая прибыль с 1 шт.')
                                ->content(function (Get $get) {
                                    $profit = $this->calculateProfit($get);
                                    $color = $profit > 0 ? 'text-success-600' : 'text-danger-600';
                                    return new \Illuminate\Support\HtmlString("<span class='text-2xl font-bold {$color}'>" . number_format($profit, 2, '.', ' ') . " ₽</span>");
                                }),

                            Placeholder::make('margin')
                                ->label('Маржинальность')
                                ->content(function (Get $get) {
                                    $price = (float) $get('sell_price');
                                    $profit = $this->calculateProfit($get);
                                    $margin = $price > 0 ? round(($profit / $price) * 100, 1) : 0;
                                    $color = $margin >= 15 ? 'text-success-600' : ($margin > 0 ? 'text-warning-600' : 'text-danger-600');
                                    return new \Illuminate\Support\HtmlString("<span class='text-2xl font-bold {$color}'>{$margin} %</span>");
                                }),

                            Placeholder::make('roi')
                                ->label('ROI (Окупаемость)')
                                ->content(function (Get $get) {
                                    $cost = (float) $get('cost_price');
                                    $profit = $this->calculateProfit($get);
                                    $roi = $cost > 0 ? round(($profit / $cost) * 100, 1) : 0;
                                    $color = $roi >= 100 ? 'text-success-600' : ($roi > 0 ? 'text-warning-600' : 'text-danger-600');
                                    return new \Illuminate\Support\HtmlString("<span class='text-2xl font-bold {$color}'>{$roi} %</span>");
                                }),
                        ])
                        ->extraAttributes(['class' => 'bg-gray-50 dark:bg-white/5 p-6 rounded-xl border border-gray-200 dark:border-white/10 mt-2 shadow-sm']),
                    ])
            ])
            ->statePath('data');
    }

    // Математика WB (расчет на 1 ВЫКУПЛЕННЫЙ товар с учетом покатушек отказников)
    private function calculateProfit(Get $get): float
    {
        $price = (float) $get('sell_price');
        $cost = (float) $get('cost_price');
        $buyout = (float) $get('buyout_percent') / 100;
        
        $commissionPercent = (float) $get('commission_percent') / 100;
        $taxPercent = (float) $get('tax_percent') / 100;
        
        $logistics = (float) $get('logistics_cost');
        $returnLogistics = (float) $get('return_logistics_cost');

        if ($buyout <= 0) return 0;

        $wbCommission = $price * $commissionPercent;
        $taxes = $price * $taxPercent;
        
        // Стоимость логистики на 1 выкупленный товар = (Логистика туда) + (Логистика обратно * вероятность отказа) / вероятность выкупа
        // Формула: (Логистика + Возврат * (1 - %Выкупа)) / %Выкупа
        $avgLogisticsPerItem = ($logistics + ($returnLogistics * (1 - $buyout))) / $buyout; 

        $profit = $price - $cost - $wbCommission - $taxes - $avgLogisticsPerItem;

        return $profit;
    }
}