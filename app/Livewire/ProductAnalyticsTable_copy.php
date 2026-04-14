<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductAnalytic;
use Carbon\Carbon;
use Livewire\Component;

class ProductAnalyticsTable extends Component
{
    public Product $record;
    
    // Фильтры даты
    public $dateFrom;
    public $dateTo;

    // Настройки метрик (чекбоксы)
    public $showOpenCard = true;
    public $showAddToCart = true;
    public $showOrders = true;
    public $showBuyouts = true;
    public $showCrCart = true;
    public $showCrOrder = true;

    public function mount(Product $record)
    {
        $this->record = $record;
        // По умолчанию 14 дней
        $this->dateFrom = Carbon::now()->subDays(13)->format('Y-m-d');
        $this->dateTo = Carbon::now()->format('Y-m-d');
    }

    public function render()
    {
        // 1. Загружаем данные за период (сортируем по дате)
        $analytics = ProductAnalytic::where('nm_id', $this->record->nm_id)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->orderBy('date')
            ->get()
            ->keyBy('date'); // Ключ массива = дата (строка)

        // 2. Генерируем массив дат для колонок
        $dates = [];
        $current = Carbon::parse($this->dateFrom);
        $end = Carbon::parse($this->dateTo);

        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        // 3. Подготовка данных для строк (с расчетом дельты)
        // Нам нужно знать данные за "вчера" относительно старта, чтобы посчитать разницу для первого дня
        // Но для упрощения будем считать разницу внутри текущего набора.
        
        $data = [];
        
        // Вспомогательная функция для расчета
        $processMetric = function($key, $calcValueFn) use ($dates, $analytics) {
            $row = [];
            $total = 0;
            $prevValue = null;

            foreach ($dates as $dateStr) {
                // Преобразуем дату из БД (она может быть Carbon object) в строку Y-m-d
                // В keyBy('date') ключи могут быть строками Y-m-d H:i:s, приведем к Y-m-d
                // Проще искать в коллекции
                
                $dayRecord = $analytics->first(fn($item) => $item->date->format('Y-m-d') === $dateStr);
                
                $value = $dayRecord ? $calcValueFn($dayRecord) : 0;
                $total += $value;

                // Считаем разницу с предыдущим днем
                $diff = ($prevValue !== null) ? $value - $prevValue : 0;
                
                $row[$dateStr] = [
                    'value' => $value,
                    'diff' => $diff,
                ];
                
                $prevValue = $value;
            }
            return ['total' => $total, 'days' => $row];
        };

        // --- СТРОКИ ТАБЛИЦЫ ---

        if ($this->showOpenCard) {
            $data['Переходы'] = $processMetric('open_card', fn($r) => $r->open_card_count);
        }
        if ($this->showAddToCart) {
            $data['В корзину'] = $processMetric('add_to_cart', fn($r) => $r->add_to_cart_count);
        }
        if ($this->showOrders) {
            $data['Заказы, шт'] = $processMetric('orders', fn($r) => $r->orders_count);
        }
        if ($this->showBuyouts) {
            $data['Выкупы, шт'] = $processMetric('buyouts', fn($r) => $r->buyouts_count);
        }
        
        // Для % нельзя просто суммировать Total, считаем среднее или пересчитываем
        if ($this->showCrCart) {
            $data['Конверсия в корзину, %'] = $processMetric('cr_cart', function($r) {
                return $r->open_card_count > 0 ? round(($r->add_to_cart_count / $r->open_card_count) * 100, 2) : 0;
            });
            // Total для конверсии - это среднее
            $data['Конверсия в корзину, %']['total'] = count($dates) > 0 ? round($data['Конверсия в корзину, %']['total'] / count($dates), 2) : 0;
        }

        if ($this->showCrOrder) {
            $data['Конверсия в заказ, %'] = $processMetric('cr_order', function($r) {
                return $r->add_to_cart_count > 0 ? round(($r->orders_count / $r->add_to_cart_count) * 100, 2) : 0;
            });
            $data['Конверсия в заказ, %']['total'] = count($dates) > 0 ? round($data['Конверсия в заказ, %']['total'] / count($dates), 2) : 0;
        }

        return view('livewire.product-analytics-table', [
            'dates' => $dates,
            'tableData' => $data
        ]);
    }
}