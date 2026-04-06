<?php

namespace App\Console\Commands;

use App\Models\AdvertCampaign;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WbSyncAdverts extends Command
{
    protected $signature = 'wb:sync-adverts';
    protected $description = 'Загрузка рекламных кампаний напрямую через WB API v2';

    public function handle()
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            $this->line("----------------------------------------------------------------");
            $this->info("📺 Магазин: {$store->name} (ID: {$store->id})");

            $token = $store->api_key_advert;

            if (empty($token)) {
                $this->warn("   ⚠️ Нет API ключа 'Реклама'. Пропускаем.");
                continue;
            }

            try {
                $this->line("   📡 1. Получаем список ID кампаний (/adv/v1/promotion/count)...");
                
                // Делаем ПРЯМОЙ HTTP-запрос к API
                $responseList = Http::withHeaders([
                    'Authorization' => $token,
                    'Accept' => 'application/json'
                ])->get('https://advert-api.wildberries.ru/adv/v1/promotion/count');

                if ($responseList->failed()) {
                    $this->error("   ❌ Ошибка API при получении списка: " . $responseList->status() . " " . $responseList->body());
                    continue;
                }

                $dataList = $responseList->object();
                $groups = $dataList->adverts ?? [];
                
                $allIds = [];
                $campaignTypes = []; // Карта для сохранения типа кампании (Поиск, Авто и т.д.)

                foreach ($groups as $group) {
                    $type = $group->type ?? 0;
                    $list = $group->advert_list ?? [];
                    
                    foreach ($list as $item) {
                        $id = $item->advertId ?? null;
                        if ($id) {
                            $allIds[] = $id;
                            $campaignTypes[$id] = $type; // Запоминаем тип для каждого ID
                        }
                    }
                }
                
                $allIds = array_unique($allIds);
                $totalCount = count($allIds);

                if ($totalCount === 0) {
                    $this->warn("   📭 Кампаний не найдено.");
                    continue;
                }

                $this->info("   🔍 Найдено ID: {$totalCount}. Загружаем детали (/api/advert/v2/adverts)...");

                // API v2 принимает максимум 50 ID за один запрос
                $chunks = array_chunk($allIds, 50);
                $processed = 0;

                foreach ($chunks as $chunk) {
                    try {
                        $idsString = implode(',', $chunk);

                        // Прямой запрос к новому API v2
                        $responseDetails = Http::withHeaders([
                            'Authorization' => $token,
                            'Accept' => 'application/json'
                        ])->get('https://advert-api.wildberries.ru/api/advert/v2/adverts', [
                            'ids' => $idsString
                        ]);

                        if ($responseDetails->failed()) {
                            $this->error("   ❌ Ошибка загрузки деталей пачки: " . $responseDetails->status() . " " . $responseDetails->body());
                            continue;
                        }

                        $details = $responseDetails->object()->adverts ?? [];

                        if (!empty($details)) {
                            DB::transaction(function () use ($store, $details, $campaignTypes) {
                                foreach ($details as $adv) {
                                    $advId = $adv->id ?? null;
                                    if (!$advId) continue;

                                    $nmId = $this->extractNmId($adv);

                                    // Собираем данные
                                    $name = $adv->settings->name ?? 'Без названия';
                                    $status = $adv->status ?? 0;
                                    $dailyBudget = $adv->dailyBudget ?? 0; // В v2 может отсутствовать, но страхуемся
                                    
                                    // Тип мы берем из карты, собранной в первом запросе, так как в v2 details его нет!
                                    $type = $campaignTypes[$advId] ?? 0;

                                    // Даты
                                    $createTime = $adv->timestamps->created ?? null;
                                    $changeTime = $adv->timestamps->updated ?? null;

                                    AdvertCampaign::updateOrCreate(
                                        [
                                            'store_id' => $store->id,
                                            'advert_id' => $advId
                                        ],
                                        [
                                            'name' => $name,
                                            'type' => $type,
                                            'status' => $status,
                                            'daily_budget' => $dailyBudget,
                                            'create_time' => $createTime ? Carbon::parse($createTime) : null,
                                            'change_time' => $changeTime ? Carbon::parse($changeTime) : null,
                                            'raw_data' => json_decode(json_encode($adv), true), // сохраняем весь ответ массивом для дебага
                                            'nm_id' => $nmId, 
                                        ]
                                    );
                                }
                            });
                        }

                        $processed += count($chunk);
                        $this->line("   ✅ Обработано {$processed} из {$totalCount}...");
                        
                        usleep(250000); // Пауза 250мс (Лимит WB: 5 запросов в сек = 1 запрос раз в 200мс)

                    } catch (\Throwable $e) {
                        $this->error("   ❌ Ошибка пачки: " . $e->getMessage());
                    }
                }

                $this->info("   🏁 Готово.");

            } catch (\Throwable $e) {
                $this->error("   💥 Критическая ошибка скрипта: " . $e->getMessage());
            }
        }
    }

    /**
     * Метод извлечения nm_id из новой структуры (с фоллбэком)
     */
    private function extractNmId(object $adv): ?int
    {
        // 1. НОВЫЙ ФОРМАТ v2
        if (!empty($adv->nm_settings) && is_array($adv->nm_settings)) {
            $firstSetting = (object) $adv->nm_settings[0];
            if (!empty($firstSetting->nm_id)) {
                return (int) $firstSetting->nm_id;
            }
        }

        // --- Оставил старые проверки на всякий случай, если WB будет миксовать форматы ---
        if (!empty($adv->unitedParams) && is_array($adv->unitedParams)) {
            foreach ($adv->unitedParams as $param) {
                $param = (object) $param;
                if (!empty($param->nms) && is_array($param->nms)) return (int) $param->nms[0];
                if (!empty($param->menus) && is_array($param->menus)) {
                    foreach ($param->menus as $menu) {
                        $menu = (object) $menu;
                        if (!empty($menu->nms) && is_array($menu->nms)) return (int) $menu->nms[0]; 
                    }
                }
            }
        }

        if (!empty($adv->auction_multibids) && is_array($adv->auction_multibids)) {
            $firstBid = (object) $adv->auction_multibids[0];
            if (!empty($firstBid->nm)) return (int) $firstBid->nm;
        }

        if (!empty($adv->params) && is_array($adv->params)) {
            foreach ($adv->params as $param) {
                $param = (object) $param;
                if (!empty($param->nms) && is_array($param->nms)) return (int) $param->nms[0];
                if (isset($param->nmId)) return (int) $param->nmId;
            }
        }

        return null;
    }
}