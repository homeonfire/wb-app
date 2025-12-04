<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('stores', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // Название магазина (например "ИП Иванов")
        $table->string('slug')->unique(); // Короткое имя для URL
        // API Ключи для этого магазина
        $table->text('api_key_standard')->nullable();
        $table->text('api_key_stat')->nullable();
        $table->text('api_key_advert')->nullable();
        $table->timestamps();
    });

    // Таблица связи: Какой юзер имеет доступ к какому магазину
    Schema::create('store_user', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('store_id')->constrained()->cascadeOnDelete();
        $table->timestamps();
    });
}
};
