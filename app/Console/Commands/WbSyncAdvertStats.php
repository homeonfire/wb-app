<?php

namespace App\Console\Commands;

use App\Models\AdvertCampaign;
use App\Models\AdvertStatistic;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WbSyncAdvertStats extends Command
{
    protected $signature = 'wb:sync-advert-stats {--days=3 : За сколько дней грузить}';
    protected $description = 'Загрузка статистики по рекламе (только активные) через API v3';

    public function handle()
    {
        $stores = Store::all();
        $days = (int) $this->option('days');
        
        // Форматируем даты для API v3 (YYYY-MM-DD)
        $dateFrom = Carbon::now()->subDays($days)->format('Y-m-d');
        $dateTo = Carbon::now()->format('Y-m-d');

        foreach ($stores as $store) {
            $this->line("----------------------------------------------------------------");
            $this->info("📺 Магазин: {$store->name} (ID: {$store->id})");

            if (empty($store->api_key_advert)) {
                $this->warn("   ⚠️ Нет ключа рекламы.");
                continue;
            }

            // Берем только АКТИВНЫЕ кампании (статус 9)
            $campaigns = AdvertCampaign::where('store_id', $store->id)
                ->where('status', 9) 
                ->get();

            if ($campaigns->isEmpty()) {
                $this->warn("   Нет активных кампаний для обновления.");
                continue;
            }

            $campaignMap = $campaigns->keyBy('advert_id');
            $wbIds = $campaignMap->keys()->toArray();

            // Разбиваем на пачки по 50 (лимит API WB)
            $chunks = array_chunk($wbIds, 50);

            foreach ($chunks as $chunkIndex => $chunkIds) {
                $this->info("   ⏳ Запрос статистики для пачки #" . ($chunkIndex + 1) . " (кол-во: " . count($chunkIds) . ")...");

                try {
                    // Формируем строку ID через запятую для GET запроса
                    $idsString = implode(',', $chunkIds);
                    $url = 'https://advert-api.wildberries.ru/adv/v3/fullstats';

                    // Делаем GET запрос к API v3
                    $response = Http::withHeaders([
                        'Authorization' => $store->api_key_advert,
                        'Accept'        => 'application/json',
                    ])->get($url, [
                        'ids'       => $idsString,
                        'beginDate' => $dateFrom,
                        'endDate'   => $dateTo,
                    ]);

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

                // Лимит: 3 запроса в минуту (1 запрос в 20 секунд)
                if ($chunkIndex < count($chunks) - 1 || $store->id !== $stores->last()->id) {
                    $this->warn("   ⏸ Ждем 21 секунду из-за лимитов WB (3 запроса/мин)...");
                    $this->output->progressStart(21);
                    for ($i = 0; $i < 21; $i++) {
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
        $preparedData = [];

        foreach ($data as $campData) {
            $wbAdvertId = $campData['advertId'] ?? null;
            if (!$wbAdvertId || !isset($campaignMap[$wbAdvertId])) continue;

            $localCampaign = $campaignMap[$wbAdvertId];
            $days = $campData['days'] ?? [];

            foreach ($days as $dayStat) {
                // Дата приходит в формате "2025-09-07T00:00:00Z"
                $dateObj = Carbon::parse($dayStat['date'])->startOfDay();
                $uniqueKey = $localCampaign->id . '_' . $dateObj->format('Y-m-d');
                
                $clicks = $dayStat['clicks'] ?? 0;
                $cpc = $dayStat['cpc'] ?? 0;
                
                // 👇 ИЗМЕНЕНИЕ: В API v3 расходы лежат в поле 'sum'
                $apiSpend = $dayStat['sum'] ?? 0;

                // Логика пересчета расхода (страховка от багов WB)
                if ($apiSpend == 0 && $clicks > 0 && $cpc > 0) {
                    $finalSpend = $clicks * $cpc;
                } else {
                    $finalSpend = $apiSpend;
                }

                $preparedData[$uniqueKey] = [
                    'advert_campaign_id' => $localCampaign->id,
                    'date'               => $dateObj,
                    'views'              => $dayStat['views'] ?? 0,
                    'clicks'             => $clicks,
                    'ctr'                => $dayStat['ctr'] ?? 0,
                    'cpc'                => $cpc,
                    'spend'              => $finalSpend, // Сохраняем в нашу колонку spend
                    'atbs'               => $dayStat['atbs'] ?? 0,
                    'orders'             => $dayStat['orders'] ?? 0,
                    'cr'                 => $dayStat['cr'] ?? 0,
                    'shks'               => $dayStat['shks'] ?? 0,
                    'sum_price'          => $dayStat['sum_price'] ?? 0,
                ];
            }
        }

        DB::transaction(function () use ($preparedData) {
            foreach ($preparedData as $row) {
                AdvertStatistic::updateOrCreate(
                    [
                        'advert_campaign_id' => $row['advert_campaign_id'],
                        'date'               => $row['date']
                    ],
                    $row
                );
            }
        });
    }
}