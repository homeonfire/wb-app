<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'nm_id',
        'vendor_code',
        'title',
        'brand',
        'main_image_url',
        'cost_price',
    ];

    // Товар принадлежит магазину
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    

    // У товара много размеров
    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }
}