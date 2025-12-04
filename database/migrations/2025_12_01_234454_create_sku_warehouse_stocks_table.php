<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_warehouse_stocks', function (Blueprint $table) {
            $table->id();

            // Связь с размером (SKU)
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();

            $table->string('warehouse_name'); // Название склада (Коледино)
            $table->integer('quantity'); // Доступное количество
            $table->integer('in_way_to_client')->default(0); // В пути к клиенту
            $table->integer('in_way_from_client')->default(0); // В пути от клиента

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_warehouse_stocks');
    }
};
