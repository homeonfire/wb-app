<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advert_campaigns', function (Blueprint $table) {
            // Добавляем JSON поле для хранения всех сырых данных
            $table->json('raw_data')->nullable()->after('daily_budget');
        });
    }

    public function down(): void
    {
        Schema::table('advert_campaigns', function (Blueprint $table) {
            $table->dropColumn('raw_data');
        });
    }
};