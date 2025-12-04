<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('sku_stocks', function (Blueprint $table) {
        $table->id();

        // Привязка к конкретному размеру (SKU)
        // unique() означает, что у одного SKU может быть только одна запись о логистике
        $table->foreignId('sku_id')->constrained()->cascadeOnDelete()->unique();

        // Наши внутренние остатки (по умолчанию 0)
        $table->integer('stock_own')->default(0);        // Наш склад
        $table->integer('in_transit_to_wb')->default(0); // В пути на WB
        $table->integer('in_transit_general')->default(0); // Карго / В пути общее
        $table->integer('at_factory')->default(0);       // Остаток на фабрике

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_stocks');
    }
};
