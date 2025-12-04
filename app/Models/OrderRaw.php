<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRaw extends Model
{
    protected $fillable = [
            'store_id', 'srid', 'order_date', 'last_change_date', 'nm_id', 'barcode',
            'total_price', 'discount_percent', 'warehouse_name', 'oblast_okrug_name',
            'finished_price', 'is_cancel', 'cancel_dt'
        ];

        protected $casts = [
            'order_date' => 'datetime',
            'last_change_date' => 'datetime',
            'cancel_dt' => 'datetime',
            'is_cancel' => 'boolean',
        ];
}
