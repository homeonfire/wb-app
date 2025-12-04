<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Store extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'api_key_standard', 'api_key_stat', 'api_key_advert'];

    // Связь: Магазин принадлежит многим юзерам
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class);
    }
}