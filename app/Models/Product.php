<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// 👇 ПРАВИЛЬНЫЕ МОДЕЛИ (Singular + Raw)
use App\Models\OrderRaw;
use App\Models\SaleRaw;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'nm_id', 'vendor_code', 'title', 'brand',
        'main_image_url', 'cost_price', 'seasonality',
    ];

    protected $casts = [
        'seasonality' => 'array',
        'nm_id' => 'integer',
        'store_id' => 'integer',
        'cost_price' => 'decimal:2',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_user');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ProductPlan::class);
    }

    // 👇 Связь с заказами (OrderRaw)
    public function orders(): HasMany
    {
        return $this->hasMany(OrderRaw::class, 'nm_id', 'nm_id');
    }

    // 👇 Связь с продажами (SaleRaw)
    public function sales(): HasMany
    {
        return $this->hasMany(SaleRaw::class, 'nm_id', 'nm_id');
    }

    // 👇 ВОТ ЭТОГО МЕТОДА НЕ ХВАТАЕТ 👇
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    // 👆
}