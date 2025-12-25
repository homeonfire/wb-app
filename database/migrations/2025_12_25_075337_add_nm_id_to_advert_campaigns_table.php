<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advert_campaigns', function (Blueprint $table) {
            // Добавляем поле nm_id (артикул WB)
            $table->unsignedBigInteger('nm_id')->nullable()->index()->after('advert_id');
        });
    }

    public function down(): void
    {
        Schema::table('advert_campaigns', function (Blueprint $table) {
            $table->dropColumn('nm_id');
        });
    }
};