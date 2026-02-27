<?php

namespace App\Console\Commands;

use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http; // 👈 Будем использовать встроенный HTTP клиент Laravel

class WbTestApi extends Command
{
    protected $signature = 'wb:test-api';
    
    protected $description = 'Тестовая выгрузка воронки продаж (v3) через прямой HTTP-запрос';

    public function handle()
    {
        // Берем самый первый магазин из базы
        $store = Store::first();

        if (!$store) {
            $this->error('❌ Магазины не найдены в БД.');
            return 1;
        }

        $this->info("🏪 Используем магазин: {$store->name} (ID: {$store->id})");

        // Для API Аналитики нужен стандартный ключ
        if (empty($store->api_key_standard)) {
            $this->error('❌ У магазина нет стандартного API ключа (api_key_standard).');
            return 1;
        }

        // Для v3 требуются даты в формате YYYY-MM-DD
        // Возьмем вчерашний день для текущего периода (selectedPeriod)
        $date = Carbon::now()->subDay();
        $startDate = $date->format('Y-m-d');
        $endDate = $date->format('Y-m-d');

        // Для прошлого периода (pastPeriod) API WB обычно просит аналогичный промежуток.
        // Возьмем тот же день, но ровно год назад.
        $pastStartDate = $date->copy()->subYear()->format('Y-m-d');
        $pastEndDate = $date->copy()->subYear()->format('Y-m-d');

        $this->warn("📅 Запрашиваем данные за: {$startDate}");

        // Формируем тело запроса строго по документации v3
        $payload = [
            'selectedPeriod' => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
            'pastPeriod' => [
                'start' => $pastStartDate,
                'end'   => $pastEndDate,
            ],
            'limit'  => 5, // Ограничиваем 5 товарами для теста
            'offset' => 0,
        ];

        try {
            $this->info("🚀 Отправка POST запроса к API v3...");

            // Выполняем прямой запрос, минуя библиотеку Dakword
            $response = Http::withHeaders([
                'Authorization' => $store->api_key_standard,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->post('https://seller-analytics-api.wildberries.ru/api/analytics/v3/sales-funnel/products', $payload);

            // Проверяем успешность ответа (HTTP статус 200)
            if ($response->successful()) {
                // Выводим результат в консоль в красивом виде
                $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                $this->newLine();
                $this->info("✅ Тестовый запрос (v3) успешно выполнен!");
            } else {
                // Если WB вернул ошибку (400, 401, 429 и т.д.)
                $this->error("❌ Ошибка API. HTTP Статус: {$response->status()}");
                $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

        } catch (\Exception $e) {
            $this->error("💥 Критическая ошибка: " . $e->getMessage());
        }

        return 0;
    }
}