<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkuStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku_id',
        'stock_own',
        'in_transit_to_wb',
        'in_transit_general',
        'at_factory',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}