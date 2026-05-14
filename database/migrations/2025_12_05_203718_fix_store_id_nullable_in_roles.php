<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Снимаем ограничение Первичного ключа
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary();
        });

        // 2. Теперь можно сделать колонку nullable
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->change();
        });

        // 3. Возвращаем ограничение уникальности
        Schema::table('model_has_roles', function (Blueprint $table) {
            // ИСПРАВЛЕНО: permission_id заменен на role_id
            $table->unique(['role_id', 'model_id', 'model_type', 'store_id'], 'model_has_roles_unique_index');
        });
        
        // --- То же самое для прав (permissions) ---
        
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->change();
        });
        
        Schema::table('model_has_permissions', function (Blueprint $table) {
             // Здесь permission_id остается, так как это таблица прав
             $table->unique(['permission_id', 'model_id', 'model_type', 'store_id'], 'model_has_permissions_unique_index');
        });
    }

    public function down(): void
    {
        // Обратного пути нет, структура слишком сложная для авто-отката
    }
};