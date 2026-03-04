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
    Schema::create('external_adverts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // Связь с товаром (артикулом)
        $table->string('blogger_link');
        $table->decimal('ad_cost', 10, 2)->default(0); // Стоимость рекламы
        $table->decimal('ad_spent', 10, 2)->default(0); // Потрачено по факту
        $table->string('platform'); // Telegram, Inst, VK
        $table->json('formats'); // Массив для Сторис, Рилс и т.д.
        $table->date('release_date');
        $table->string('status')->default('not_published'); // Статус по умолчанию
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_adverts');
    }
};
