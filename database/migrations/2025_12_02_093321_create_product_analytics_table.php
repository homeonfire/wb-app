<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('product_analytics', function (Blueprint $table) {
        $table->id();

        $table->foreignId('store_id')->constrained()->cascadeOnDelete();

        // Связь по артикулу WB (так надежнее для аналитики)
        $table->unsignedBigInteger('nm_id')->index();
        $table->date('date'); // Дата статистики

        // Воронка
        $table->integer('open_card_count')->default(0); // Открытия карточки (Переходы)
        $table->integer('add_to_cart_count')->default(0); // Добавления в корзину
        $table->integer('orders_count')->default(0); // Заказы (из отчета аналитики)
        $table->integer('buyouts_count')->default(0); // Выкупы (из отчета аналитики)

        // Конверсии (можно хранить или считать на лету, лучше хранить "сырые" цифры)

        $table->timestamps();

        // Уникальный индекс, чтобы не дублировать данные за одну дату
        $table->unique(['nm_id', 'date']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_analytics');
    }
};
