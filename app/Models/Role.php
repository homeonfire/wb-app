<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends SpatieRole
{
    // Разрешаем массовое заполнение store_id
    protected $fillable = ['name', 'guard_name', 'store_id', 'updated_at', 'created_at'];

    // Та самая связь, которую искал Filament
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}