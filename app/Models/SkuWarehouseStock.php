<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkuWarehouseStock extends Model
{
    // Твои текущие настройки ($fillable и т.д.)
    protected $fillable = [
        'sku_id',
        'warehouse_name',
        'quantity',
        'in_way_to_client',
        'in_way_from_client',
    ];

    // ДОБАВЛЯЕМ ЭТУ СВЯЗЬ:
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}