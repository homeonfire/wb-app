<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkuWarehouseDetail extends Model
{
    // Разрешаем массовую запись для этих колонок
    protected $fillable = [
        'sku_id',
        'warehouse_name',
        'quantity',
    ];

    // Обратная связь: Детализация склада принадлежит конкретному SKU
    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }
}