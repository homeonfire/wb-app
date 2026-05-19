<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ВАЖНО: Если в таблице уже есть дубликаты, Postgres не даст создать уникальный индекс.
        // Так как мы все равно полностью синхронизируем остатки, безопаснее всего очистить таблицу перед созданием индекса.
        DB::table('sku_warehouse_stocks')->truncate();

        Schema::table('sku_warehouse_stocks', function (Blueprint $table) {
            // Создаем составной уникальный индекс с коротким понятным именем
            $table->unique(['sku_id', 'warehouse_name'], 'sku_wh_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sku_warehouse_stocks', function (Blueprint $table) {
            $table->dropUnique('sku_wh_unique');
        });
    }
};