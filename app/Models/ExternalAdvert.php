<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalAdvert extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', // <--- Обязательно добавляем сюда
        'product_id',
        'blogger_link',
        'ad_cost',
        'ad_spent',
        'platform',
        'formats',
        'release_date',
        'status',
    ];

    protected $casts = [
        'formats' => 'array',
        'release_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // НОВЫЙ МЕТОД: Связь с магазином
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}