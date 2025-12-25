<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvertCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'advert_id',
        'name',
        'type',
        'status',
        'daily_budget',
        'create_time',
        'change_time',
        'start_time',
        'end_time',
        'nm_id',       // ✅ Добавили в fillable
        'subject_id',
        'raw_data',
    ];

    protected $casts = [
        'advert_id' => 'integer',
        'store_id' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        'daily_budget' => 'integer',
        'create_time' => 'datetime',
        'change_time' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'nm_id' => 'integer', // ✅ Добавили cast
        'raw_data' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    // ✅ Связь с товаром по nm_id
    public function product(): BelongsTo
    {
        // Связываем products.nm_id с advert_campaigns.nm_id
        return $this->belongsTo(Product::class, 'nm_id', 'nm_id');
    }

    public function statistics(): HasMany
    {
        return $this->hasMany(AdvertStatistic::class, 'advert_campaign_id', 'id');
    }

    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            8 => 'Автоматическая',
            9 => 'Поиск + Каталог',
            4 => 'Каталог',
            5 => 'Карточка',
            6 => 'Поиск',
            default => 'Тип ' . $this->type,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            9 => 'success', // Активна
            11 => 'warning', // Пауза
            7 => 'gray', // Завершена
            4 => 'info', // Готова
            -1 => 'danger', // Удалена
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            9 => 'Активна',
            11 => 'Пауза',
            7 => 'Архив',
            4 => 'Готова',
            -1 => 'Удалена',
            default => (string) $this->status,
        };
    }
}