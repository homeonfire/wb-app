<?php

namespace App\Console\Commands;

use App\Models\AdvertCampaign;
use App\Models\Store;
use App\Services\WbService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WbSyncAdverts extends Command
{
    protected $signature = 'wb:sync-adverts';
    protected $description = 'Загрузка рекламных кампаний и привязка к товарам (nm_id)';

    public function handle()
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            $this->line("----------------------------------------------------------------");
            $this->info("📺 Магазин: {$store->name} (ID: {$store->id})");

            if (empty($store->api_key_advert)) {
                $this->warn("   ⚠️ Нет API ключа 'Реклама'. Пропускаем.");
                continue;
            }

            try {
                $wb = new WbService($store);

                $this->line("   📡 1. Получаем список ID кампаний...");
                
                $groups = $wb->api->Adv()->advertsList(); 
                $allIds = [];
                
                if (is_iterable($groups)) {
                    foreach ($groups as $group) {
                        $list = is_object($group) ? ($group->advert_list ?? []) : ($group['advert_list'] ?? []);
                        foreach ($list as $item) {
                            $id = is_object($item) ? ($item->advertId ?? $item->id ?? null) : ($item['advertId'] ?? $item['id'] ?? null);
                            if ($id) $allIds[] = $id;
                        }
                    }
                }
                
                $allIds = array_unique($allIds);
                $totalCount = count($allIds);

                if ($totalCount === 0) {
                    $this->warn("   📭 Кампаний не найдено.");
                    continue;
                }

                $this->info("   🔍 Найдено ID: {$totalCount}. Загружаем детали...");

                $chunks = array_chunk($allIds, 50);
                $processed = 0;

                foreach ($chunks as $chunk) {
                    try {
                        $details = $wb->api->Adv()->advertsInfoByIds($chunk);

                        if (!empty($details)) {
                            DB::transaction(function () use ($store, $details) {
                                foreach ($details as $adv) {
                                    $adv = (object) $adv;
                                    $advId = $adv->advertId ?? $adv->id ?? null;
                                    
                                    if (!$advId) continue;

                                    // 👇 Обновленная логика извлечения
                                    $nmId = $this->extractNmId($adv);

                                    AdvertCampaign::updateOrCreate(
                                        [
                                            'store_id' => $store->id,
                                            'advert_id' => $advId
                                        ],
                                        [
                                            'name' => $adv->name ?? 'Без названия',
                                            'type' => $adv->type ?? 0,
                                            'status' => $adv->status ?? 0,
                                            'daily_budget' => $adv->dailyBudget ?? 0,
                                            'create_time' => isset($adv->createTime) ? Carbon::parse($adv->createTime) : null,
                                            'change_time' => isset($adv->changeTime) ? Carbon::parse($adv->changeTime) : null,
                                            'raw_data' => $adv,
                                            'nm_id' => $nmId, // ✅ Теперь должно найтись корректно
                                        ]
                                    );
                                }
                            });
                        }

                        $processed += count($chunk);
                        $this->line("   ✅ Обработано {$processed} из {$totalCount}...");
                        
                        usleep(200000); 

                    } catch (\Throwable $e) {
                        $this->error("   ❌ Ошибка пачки: " . $e->getMessage());
                    }
                }

                $this->info("   🏁 Готово.");

            } catch (\Throwable $e) {
                $this->error("   💥 Ошибка API: " . $e->getMessage());
            }
        }
    }

    /**
     * Универсальный метод поиска nm_id
     */
    private function extractNmId(object $adv): ?int
    {
        // 1. unitedParams (Авто, Поиск+Каталог)
        if (!empty($adv->unitedParams) && is_array($adv->unitedParams)) {
            foreach ($adv->unitedParams as $param) {
                $param = (object) $param;
                
                // Вариант А: nms лежит сразу в параметре (как в твоем примере)
                if (!empty($param->nms) && is_array($param->nms)) {
                    return (int) $param->nms[0];
                }

                // Вариант Б: nms лежит внутри menus (бывает в других типах)
                if (!empty($param->menus) && is_array($param->menus)) {
                    foreach ($param->menus as $menu) {
                        $menu = (object) $menu;
                        if (!empty($menu->nms) && is_array($menu->nms)) {
                            return (int) $menu->nms[0]; 
                        }
                    }
                }
            }
        }

        // 2. auction_multibids (часто есть в ответе)
        if (!empty($adv->auction_multibids) && is_array($adv->auction_multibids)) {
            $firstBid = (object) $adv->auction_multibids[0];
            if (!empty($firstBid->nm)) {
                return (int) $firstBid->nm;
            }
        }

        // 3. params (старый формат)
        if (!empty($adv->params) && is_array($adv->params)) {
            foreach ($adv->params as $param) {
                $param = (object) $param;
                if (!empty($param->nms) && is_array($param->nms)) {
                    return (int) $param->nms[0];
                }
                if (isset($param->nmId)) {
                    return (int) $param->nmId;
                }
            }
        }

        return null;
    }
}