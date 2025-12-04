<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('sale_raws', function (Blueprint $table) {
        $table->id();

        $table->foreignId('store_id')->constrained()->cascadeOnDelete();

        $table->string('sale_id')->unique(); // Уникальный ID продажи (S123...)
        $table->dateTime('sale_date'); // Было date // Дата продажи
        $table->dateTime('last_change_date')->nullable();

        $table->unsignedBigInteger('nm_id')->index();
        $table->string('barcode')->index();

        $table->decimal('total_price', 10, 2)->default(0); // Цена до скидки
        $table->integer('discount_percent')->default(0);

        $table->decimal('price_with_disc', 10, 2)->default(0); // Цена со скидкой
        $table->decimal('for_pay', 10, 2)->default(0); // К перечислению продавцу
        $table->decimal('finished_price', 10, 2)->default(0); // Фактическая цена

        $table->string('warehouse_name')->nullable();
        $table->string('region_name')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_raws');
    }
};
