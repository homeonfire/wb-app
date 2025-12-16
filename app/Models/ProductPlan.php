<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPlan extends Model
{
    protected $fillable = [
        'product_id',
        'year',
        'month',
        'orders_plan',
        'sales_plan',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}