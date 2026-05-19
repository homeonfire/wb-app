<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WbRequestWarehouseRemains extends Command
{
    protected $signature = 'wb:request-remains {store_id : ID магазина}';
    protected $description = 'Запрос на генерацию детального отчета по остаткам (с разбивкой по размерам и баркодам)';

    public function handle()
    {
        $storeId = $this->argument('store_id');
        $store = Store::findOrFail($storeId);

        $this->info("🚀 Отправляем запрос на генерацию ДЕТАЛЬНОГО отчета для: {$store->name}...");

        if (empty($store->api_key_standard)) {
            $this->error("❌ У магазина нет API ключа.");
            return;
        }

        try {
            // Формируем query-параметры для разбивки отчета
            $queryParams = [
                'groupBySize'    => 'true',
                'groupByBarcode' => 'true',
                'groupByNm'      => 'true',
                'groupBySa'      => 'true',
                'locale'         => 'ru',
            ];

            $response = Http::withHeaders([
                'Authorization' => $store->api_key_standard,
                'Content-Type'  => 'application/json',
            ])->get('https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains', $queryParams);

            if ($response->successful()) {
                $data = $response->json();
                $taskId = $data['data']['taskId'] ?? $data['taskId'] ?? null;

                if ($taskId) {
                    $this->info("✅ Задача успешно поставлена в очередь WB!");
                    $this->warn("======================================");
                    $this->warn("🔑 НОВЫЙ TASK ID: " . $taskId);
                    $this->warn("======================================");
                    $this->newLine();
                    $this->line("Подожди 30-60 секунд и запусти команду скачивания с этим ID:");
                    $this->info("./vendor/bin/sail artisan wb:download-remains {$storeId} \"{$taskId}\"");
                } else {
                    $this->error("Ответ успешен, но taskId не найден: " . $response->body());
                }
            } elseif ($response->status() === 429) {
                $this->error("🔥 Ошибка 429: Лимит запросов. Подожди минуту.");
            } else {
                $this->error("❌ Ошибка API: " . $response->status() . " " . $response->body());
            }

        } catch (\Exception $e) {
            $this->error("🚨 Критическая ошибка: " . $e->getMessage());
        }
    }
}