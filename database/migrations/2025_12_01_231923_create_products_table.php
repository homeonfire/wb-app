<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id(); // Внутренний ID Laravel

        // Привязка к магазину (ОБЯЗАТЕЛЬНО для мульти-магазинов)
        $table->foreignId('store_id')->constrained()->cascadeOnDelete();

        // Данные WB
        $table->unsignedBigInteger('nm_id')->unique(); // Артикул WB (число)
        $table->string('vendor_code')->index(); // Артикул продавца
        $table->string('title')->nullable(); // Название
        $table->string('brand')->nullable(); // Бренд
        $table->text('main_image_url')->nullable(); // Ссылка на фото с WB

        // Наша экономика
        $table->decimal('cost_price', 10, 2)->default(0); // Себестоимость

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
