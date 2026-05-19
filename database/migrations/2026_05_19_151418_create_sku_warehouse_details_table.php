<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_warehouse_details', function (Blueprint $table) {
            $table->id();
            // Связь с таблицей skus
            $table->foreignId('sku_id')->constrained('skus')->cascadeOnDelete();
            
            // Название физического склада (Коледино, Тула и т.д.)
            $table->string('warehouse_name');
            
            // Количество шмоток на этом складе
            $table->integer('quantity')->default(0);
            
            $table->timestamps();

            // Тот самый уникальный индекс для бесконфликтного Upsert-а!
            $table->unique(['sku_id', 'warehouse_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_warehouse_details');
    }
};