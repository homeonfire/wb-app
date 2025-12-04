<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sku extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'barcode', 'tech_size', 'price', 'discount'];

    // Связь: Один размер может лежать на разных складах
    public function warehouseStocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SkuWarehouseStock::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ... внутри класса Sku
public function stock(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    // withDefault() создаст пустую запись (все по нулям), 
    // если мы обратимся к логистике, а её еще нет в базе. Это очень удобно для таблицы!
    return $this->hasOne(SkuStock::class)->withDefault();
}

// Связь с продажами по штрихкоду
    public function sales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SaleRaw::class, 'barcode', 'barcode');
    }
}