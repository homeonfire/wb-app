<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Снимаем ограничение Первичного ключа
        // В Postgres PK обычно называется "имятаблицы_pkey"
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary();
        });

        // 2. Теперь можно сделать колонку nullable
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->change();
        });

        // 3. Возвращаем ограничение уникальности, но уже в виде Индекса (он допускает NULL)
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unique(['permission_id', 'model_id', 'model_type', 'store_id'], 'model_has_roles_unique_index');
        });
        
        // --- То же самое для прав (permissions), на всякий случай ---
        
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->change();
        });
        
        Schema::table('model_has_permissions', function (Blueprint $table) {
             $table->unique(['permission_id', 'model_id', 'model_type', 'store_id'], 'model_has_permissions_unique_index');
        });
    }

    public function down(): void
    {
        // Обратного пути нет, структура слишком сложная для авто-отката
    }
};