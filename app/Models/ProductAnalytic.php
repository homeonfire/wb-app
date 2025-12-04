<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'nm_id', 'date',
        'open_card_count', 'add_to_cart_count', 'orders_count', 'buyouts_count'
    ];

    protected $casts = [
        'date' => 'date',
    ];
}