<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WbDownloadWarehouseRemains extends Command
{
    protected $signature = 'wb:download-remains {store_id} {task_id}';
    protected $description = 'Тест: Проверка статуса и скачивание отчета по остаткам';

    public function handle()
    {
        $store = Store::findOrFail($this->argument('store_id'));
        $taskId = $this->argument('task_id');

        $this->info("🔍 Проверяем статус задачи: {$taskId}");

        try {
            $statusUrl = "https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains/tasks/{$taskId}/status";
            
            $statusResponse = Http::withHeaders([
                'Authorization' => $store->api_key_standard,
            ])->get($statusUrl);

            if (!$statusResponse->successful()) {
                $this->error("❌ Ошибка проверки статуса: " . $statusResponse->status() . " " . $statusResponse->body());
                return;
            }

            $statusData = $statusResponse->json();
            $status = $statusData['data']['status'] ?? $statusData['status'] ?? 'unknown';

            $this->info("📊 Статус на сервере WB: [" . strtoupper($status) . "]");

            if ($status === 'done') {
                $this->info("🚀 Отчет готов! Начинаем скачивание...");

                $downloadUrl = "https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains/tasks/{$taskId}/download";

                $downloadResponse = Http::withHeaders([
                    'Authorization' => $store->api_key_standard,
                ])->timeout(120)->get($downloadUrl);

                if ($downloadResponse->successful()) {
                    $body = $downloadResponse->body();
                    $size = strlen($body);
                    
                    $this->warn("📦 Размер ответа: {$size} байт");
                    $this->line("👀 Превью данных (первые 150 симв):");
                    $this->line(mb_substr($body, 0, 150));
                    $this->newLine();

                    if ($size === 0) {
                        $this->error("⚠️ WB вернул абсолютно пустой ответ (0 байт)!");
                        return;
                    }

                    $directory = 'wb_reports';
                    if (!Storage::disk('local')->exists($directory)) {
                        Storage::disk('local')->makeDirectory($directory);
                    }

                    // Определяем расширение по содержимому (вдруг там ZIP)
                    $ext = str_starts_with($body, 'PK') ? 'zip' : 'json';
                    $fileName = "{$directory}/remains_{$taskId}.{$ext}"; 
                    
                    Storage::disk('local')->put($fileName, $body);
                    
                    $this->info("🎉 Файл успешно сохранен!");
                    // Выводим абсолютный путь, чтобы ты точно знал, где его искать в проекте
                    $this->info("📁 Точный путь: " . storage_path('app/' . $fileName));
                } else {
                    $this->error("❌ Ошибка при скачивании: " . $downloadResponse->status() . " " . $downloadResponse->body());
                }
            } elseif (in_array($status, ['new', 'processing'])) {
                $this->warn("⏳ Отчет еще формируется на стороне WB. Повтори команду чуть позже.");
            } else {
                $this->error("⚠️ Неизвестный статус: {$status}");
                $this->line("Тело ответа: " . $statusResponse->body());
            }

        } catch (\Exception $e) {
            $this->error("🚨 Критическая ошибка: " . $e->getMessage());
        }
    }
}