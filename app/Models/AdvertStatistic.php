<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvertStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'advert_campaign_id',
        'date',
        'views',
        'clicks',
        'ctr',
        'cpc',
        'spend',
        'atbs',
        'orders',
        'cr',
        'shks',
        'sum_price',
    ];

    protected $casts = [
        'date' => 'date',
        'ctr' => 'float',
        'cpc' => 'float',
        'spend' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdvertCampaign::class, 'advert_campaign_id');
    }
}