<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('order_raws', function (Blueprint $table) {
        $table->id();

        $table->foreignId('store_id')->constrained()->cascadeOnDelete(); // Чей заказ

        // Данные WB
        $table->string('srid')->unique(); // Уникальный ID заказа WB
        $table->dateTime('order_date'); // Было date // Дата заказа
        $table->dateTime('last_change_date')->nullable(); // Дата изменения

        $table->unsignedBigInteger('nm_id')->index(); // Артикул WB
        $table->string('barcode')->index(); // Штрихкод

        $table->decimal('total_price', 10, 2)->default(0); // Цена до скидки
        $table->integer('discount_percent')->default(0); // Скидка
        $table->string('warehouse_name')->nullable(); // Склад отгрузки
        $table->string('oblast_okrug_name')->nullable(); // Регион

        $table->decimal('finished_price', 10, 2)->default(0); // Фактическая цена

        $table->boolean('is_cancel')->default(false); // Отмена
        $table->dateTime('cancel_dt')->nullable(); // Дата отмены

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_raws');
    }
};
