<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advert_statistics', function (Blueprint $table) {
            $table->id();
            
            // Привязка к нашей таблице кампаний
            // ВАЖНО: ссылаемся на advert_campaigns.id (внутренний), а не на advert_id (внешний)
            $table->foreignId('advert_campaign_id')->constrained()->cascadeOnDelete();
            
            $table->date('date')->index(); // Дата статистики

            // Основные метрики
            $table->integer('views')->default(0);    // Просмотры
            $table->integer('clicks')->default(0);   // Клики
            $table->float('ctr')->default(0);        // CTR
            $table->float('cpc')->default(0);        // Цена клика
            $table->decimal('spend', 10, 2)->default(0); // Затраты (руб)
            
            // Конверсии (если WB отдает)
            $table->integer('atbs')->default(0);     // Добавления в корзину
            $table->integer('orders')->default(0);   // Заказы
            $table->integer('cr')->default(0);       // CR (Conversion Rate)
            $table->integer('shks')->default(0);     // Штуки (sales count)
            $table->decimal('sum_price', 12, 2)->default(0); // Сумма заказов (если есть)

            $table->timestamps();

            // Уникальный индекс: одна запись на одну кампанию в день
            $table->unique(['advert_campaign_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advert_statistics');
    }
};