<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\WbService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class WbTestApi extends Command
{
    // Название команды для запуска из консоли
    protected $signature = 'wb:test-api';
    
    protected $description = 'Тестовая выгрузка аналитики (nmReportDetail) за 1 день для первого магазина в JSON';

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

        try {
            $wb = new WbService($store);

            // Берем вчерашний день (чтобы данные за день были уже полностью сформированы)
            $date = Carbon::now()->subDay();
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();

            $this->warn("📅 Запрашиваем данные за: {$start->format('Y-m-d')}");

            // Делаем запрос к API
            $response = $wb->api->Analytics()->nmReportDetail($start, $end, [
                'limit' => 5, // 👈 Ограничиваем 5 карточками, чтобы консоль не "улетела"
                'page' => 1
            ]);

            // Выводим результат в виде отформатированного JSON с поддержкой русского языка
            $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $this->newLine();
            $this->info("✅ Тестовый запрос успешно выполнен!");

        } catch (\Exception $e) {
            $this->error("💥 Ошибка API: " . $e->getMessage());
        }
    }
}