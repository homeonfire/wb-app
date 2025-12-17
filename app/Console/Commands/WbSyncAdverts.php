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
    protected $description = 'Загрузка списка рекламных кампаний (advertsList -> advertsInfoByIds)';

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

                $this->line("   📡 1. Получаем список ID кампаний (advertsList)...");
                
                // Метод возвращает структуру, сгруппированную по типам и статусам
                $groups = $wb->api->Adv()->advertsList(); 

                $allIds = [];
                
                // Разбираем ответ WB
                if (is_iterable($groups)) {
                    foreach ($groups as $group) {
                        // $group - это объект с полями type, status, count и advert_list
                        // Безопасно получаем список, проверяя и объект, и массив
                        $list = is_object($group) ? ($group->advert_list ?? []) : ($group['advert_list'] ?? []);
                        
                        foreach ($list as $item) {
                            // 👇 ИСПРАВЛЕНИЕ: WB возвращает advertId, но на всякий случай проверяем и id
                            if (is_object($item)) {
                                $id = $item->advertId ?? $item->id ?? null;
                            } else {
                                $id = $item['advertId'] ?? $item['id'] ?? null;
                            }

                            if ($id) {
                                $allIds[] = $id;
                            }
                        }
                    }
                }
                
                // Убираем дубли
                $allIds = array_unique($allIds);
                $totalCount = count($allIds);

                if ($totalCount === 0) {
                    $this->warn("   📭 Кампаний не найдено (список пуст).");
                    continue;
                }

                $this->info("   🔍 Найдено ID: {$totalCount}. Начинаем загрузку деталей...");

                // 2. Запрашиваем детали пачками по 50 штук
                $chunks = array_chunk($allIds, 50);
                $processed = 0;

                foreach ($chunks as $chunk) {
                    try {
                        // Запрос деталей (advertsInfoByIds)
                        $details = $wb->api->Adv()->advertsInfoByIds($chunk);

                        if (!empty($details)) {
                            DB::transaction(function () use ($store, $details) {
                                foreach ($details as $adv) {
                                    $adv = (object) $adv; // Приводим к объекту для удобства

                                    // Еще одна проверка на ID (в деталях это advertId)
                                    $advId = $adv->advertId ?? $adv->id ?? null;
                                    if (!$advId) continue;

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
                                            
                                            // 👇 СОХРАНЯЕМ ВЕСЬ ОБЪЕКТ ЦЕЛИКОМ
                                            'raw_data' => $adv, 
                                        ]
                                    );
                                }
                            });
                        }

                        $processed += count($chunk);
                        $this->line("   ✅ Загружено {$processed} из {$totalCount}...");

                        // Небольшая пауза (0.2 сек)
                        usleep(200000);

                    } catch (\Throwable $e) {
                        $this->error("   ❌ Ошибка при обработке пачки ID: " . $e->getMessage());
                    }
                }

                $this->info("   🏁 Готово.");

            } catch (\Throwable $e) {
                $this->error("   💥 Ошибка API: " . $e->getMessage());
            }
        }
    }
}