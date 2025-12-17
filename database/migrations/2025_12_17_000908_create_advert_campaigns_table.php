<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advert_campaigns', function (Blueprint $table) {
            $table->id();
            
            // Привязка к магазину
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            
            // Основные данные WB
            $table->unsignedBigInteger('advert_id')->unique(); // ID кампании
            $table->string('name')->nullable();   // Название
            $table->integer('type')->default(0);  // Тип (4-каталог, 5-карточка, 6-поиск, 7-рекомендации, 8-авто, 9-поиск+каталог)
            $table->integer('status')->default(0); // Статус (7-завершена, 9-идут показы, 11-пауза)
            
            $table->decimal('daily_budget', 10, 2)->default(0); // Дневной бюджет
            
            $table->dateTime('create_time')->nullable(); // Дата создания
            $table->dateTime('change_time')->nullable(); // Дата изменения

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advert_campaigns');
    }
};