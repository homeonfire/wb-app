<?php

namespace App\Services;

use App\Models\Store;
use Dakword\WBSeller\API;

class WbService
{
    public API $api;

    public function __construct(Store $store)
    {
        // Инициализируем библиотеку ключами из базы данных этого магазина
        $this->api = new API([
            'keys' => [
                'content'     => $store->api_key_standard,
                'prices'      => $store->api_key_standard,
                'marketplace' => $store->api_key_standard,
                'statistics'  => $store->api_key_stat,
                'adv'         => $store->api_key_advert,
                'analytics' => $store->api_key_standard,    
            ],
            // Если нужно прокси, можно добавить тут
        ]);
    }

    // Метод для доступа к API Контента
    public function content()
    {
        return $this->api->Content();
    }

    // Позже добавим сюда prices(), statistics() и т.д.
}