<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'raw_data', // 👈 Добавили
    ];

    protected $casts = [
        'advert_id' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        'daily_budget' => 'decimal:2',
        'create_time' => 'datetime',
        'change_time' => 'datetime',
        'raw_data' => 'array', // 👈 Добавили авто-кастинг в массив
    ];

    // Хелпер для получения названия типа (можно вынести в Enum)
    public function getTypeNameAttribute(): string
    {
        return match($this->type) {
            4 => 'Каталог',
            5 => 'Карточка',
            6 => 'Поиск',
            7 => 'Рекомендации',
            8 => 'Автоматическая',
            9 => 'Поиск + Каталог',
            default => "Тип {$this->type}",
        };
    }

    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            7 => 'Архив',
            9 => 'Активна (идут показы)',
            11 => 'На паузе',
            default => "Статус {$this->status}",
        };
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    // app/Models/AdvertCampaign.php
    public function statistics(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdvertStatistic::class);
    }
}