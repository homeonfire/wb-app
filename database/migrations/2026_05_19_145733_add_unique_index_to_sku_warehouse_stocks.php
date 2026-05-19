<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('sku_warehouse_stocks')->truncate();

        Schema::table('sku_warehouse_stocks', function (Blueprint $table) {
            // Удаляем старый составной индекс
            $table->dropUnique('sku_wh_unique'); 
            
            // Создаем новый — строго по sku_id
            $table->unique('sku_id'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sku_warehouse_stocks', function (Blueprint $table) {
            //
        });
    }
};
