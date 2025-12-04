<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkuWarehouseStock extends Model
{
    protected $fillable = [
        'sku_id',
        'warehouse_name',
        'quantity',
        'in_way_to_client',
        'in_way_from_client'
    ];
}
