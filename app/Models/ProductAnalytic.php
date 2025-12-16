<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'nm_id',
        'date',
        
        // Инфо
        'vendor_code',
        'brand_name',
        'object_id',
        'object_name',

        // Воронка
        'open_card_count',
        'add_to_cart_count',
        'orders_count',
        'buyouts_count',
        'cancel_count',

        // Деньги
        'orders_sum_rub',
        'buyouts_sum_rub',
        'cancel_sum_rub',
        'avg_price_rub',

        // Средние
        'avg_orders_count_per_day',

        // Конверсии
        'conversion_open_to_cart_percent',
        'conversion_cart_to_order_percent',
        'conversion_buyouts_percent',

        // Стоки
        'stocks_mp',
        'stocks_wb',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}