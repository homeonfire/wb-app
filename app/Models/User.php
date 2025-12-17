<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles; // <--- 1. Добавили импорт
use App\Models\Product;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use Notifiable;
    use HasRoles; // <--- 2. Подключили трейт ролей

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->stores;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->stores->contains($tenant);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function managedProducts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_user');
    }

    // 👇 Добавляем вот эту связь
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_user');
    }
}