<?php

namespace App\Console\Commands;

use App\Models\AdvertCampaign;
use App\Models\AdvertStatistic;
use App\Models\Store;
use App\Services\WbService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WbSyncAdvertStats extends Command
{
    protected $signature = 'wb:sync-advert-stats {--days=3 : За сколько дней грузить}';
    protected $description = 'Загрузка статистики по рекламе (только активные)';

    public function handle()
    {
        $stores = Store::all();
        $days = (int) $this->option('days');
        
        $dateFrom = Carbon::now()->subDays($days)->format('Y-m-d');
        $dateTo = Carbon::now()->format('Y-m-d');

        foreach ($stores as $store) {
            $this->line("----------------------------------------------------------------");
            $this->info("📺 Магазин: {$store->name} (ID: {$store->id})");

            if (empty($store->api_key_advert)) {
                $this->warn("   ⚠️ Нет ключа рекламы.");
                continue;
            }

            // 👇 ИЗМЕНЕНИЕ: Берем только АКТИВНЫЕ кампании (статус 9)
            // 9 - Идут показы
            // 11 - Пауза (исключили)
            // 7 - Архив (исключили)
            $campaigns = AdvertCampaign::where('store_id', $store->id)
                ->where('status', 9) 
                ->get();

            if ($campaigns->isEmpty()) {
                $this->warn("   Нет активных кампаний для обновления.");
                continue;
            }

            $campaignMap = $campaigns->keyBy('advert_id');
            $wbIds = $campaignMap->keys()->toArray();

            // Разбиваем на пачки по 50 (лимит API WB комфортный)
            $chunks = array_chunk($wbIds, 50);

            foreach ($chunks as $chunkIndex => $chunkIds) {
                $this->info("   ⏳ Запрос статистики для пачки #" . ($chunkIndex + 1) . " (кол-во: " . count($chunkIds) . ")...");

                try {
                    // Формируем payload для v2/fullstats
                    $payload = [];
                    foreach ($chunkIds as $id) {
                        $payload[] = [
                            'id' => (int) $id,
                            'interval' => [
                                'begin' => $dateFrom,
                                'end'   => $dateTo,
                            ]
                        ];
                    }

                    $url = 'https://advert-api.wildberries.ru/adv/v2/fullstats';

                    $response = Http::withHeaders([
                        'Authorization' => $store->api_key_advert,
                        'Content-Type'  => 'application/json',
                    ])->post($url, $payload);

                    if ($response->failed()) {
                        if ($response->status() === 429) {
                            $this->error("   🔥 Ошибка 429 (Too Many Requests).");
                        } else {
                            $this->error("   ❌ Ошибка запроса: " . $response->body());
                        }
                    } else {
                        $data = $response->json();

                        if (empty($data)) {
                            $this->warn("   Пустой ответ от API.");
                        } else {
                            $this->saveStats($data, $campaignMap);
                            $this->info("   ✅ Данные сохранены.");
                        }
                    }

                } catch (\Throwable $e) {
                    $this->error("   ❌ Исключение: " . $e->getMessage());
                }

                // Лимит: 1 запрос в минуту (для advert API)
                if ($chunkIndex < count($chunks) - 1 || $store->id !== $stores->last()->id) {
                    $this->warn("   ⏸ Ждем 65 секунд из-за лимитов WB (1 запрос/мин)...");
                    $this->output->progressStart(65);
                    for ($i = 0; $i < 65; $i++) {
                        sleep(1);
                        $this->output->progressAdvance();
                    }
                    $this->output->progressFinish();
                    $this->newLine();
                }
            }
        }
    }

    private function saveStats(array $data, $campaignMap)
    {
        // 1. Собираем все данные в один массив, чтобы исключить дубликаты от API
        $preparedData = [];

        foreach ($data as $campData) {
            $wbAdvertId = $campData['advertId'] ?? null;
            if (!$wbAdvertId || !isset($campaignMap[$wbAdvertId])) continue;

            $localCampaign = $campaignMap[$wbAdvertId];
            $days = $campData['days'] ?? [];

            foreach ($days as $dayStat) {
                // Используем объект Carbon для даты, чтобы Laravel сам привел формат к нужному виду
                $dateObj = Carbon::parse($dayStat['date'])->startOfDay();
                // Ключ для уникальности: ID_кампании + Дата
                $uniqueKey = $localCampaign->id . '_' . $dateObj->format('Y-m-d');
                
                $clicks = $dayStat['clicks'] ?? 0;
                $cpc = $dayStat['cpc'] ?? 0;
                $apiSpend = $dayStat['spend'] ?? 0;

                // Логика пересчета расхода
                if ($apiSpend == 0 && $clicks > 0 && $cpc > 0) {
                    $finalSpend = $clicks * $cpc;
                } else {
                    $finalSpend = $apiSpend;
                }

                // Записываем в массив (если API пришлет дубль даты, мы просто перезапишем данные последними)
                $preparedData[$uniqueKey] = [
                    'advert_campaign_id' => $localCampaign->id,
                    'date'               => $dateObj, // Передаем объект!
                    'views'              => $dayStat['views'] ?? 0,
                    'clicks'             => $clicks,
                    'ctr'                => $dayStat['ctr'] ?? 0,
                    'cpc'                => $cpc,
                    'spend'              => $finalSpend,
                    'atbs'               => $dayStat['atbs'] ?? 0,
                    'orders'             => $dayStat['orders'] ?? 0,
                    'cr'                 => $dayStat['cr'] ?? 0,
                    'shks'               => $dayStat['shks'] ?? 0,
                    'sum_price'          => $dayStat['sum_price'] ?? 0,
                ];
            }
        }

        // 2. Сохраняем только уникальные записи
        DB::transaction(function () use ($preparedData) {
            foreach ($preparedData as $row) {
                AdvertStatistic::updateOrCreate(
                    [
                        // Ищем строго по ID и Дате
                        'advert_campaign_id' => $row['advert_campaign_id'],
                        'date'               => $row['date']
                    ],
                    $row // Обновляем остальные поля
                );
            }
        });
    }
}