<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleRaw extends Model
{
    protected $fillable = [
        'store_id', 'sale_id', 'sale_date', 'last_change_date', 'nm_id', 'barcode',
        'total_price', 'discount_percent', 'price_with_disc', 'for_pay',
        'finished_price', 'warehouse_name', 'region_name'
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'last_change_date' => 'datetime',
    ];
}
