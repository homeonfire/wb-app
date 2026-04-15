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
    Schema::table('products', function (Blueprint $table) {
        $table->decimal('margin_30d', 8, 2)->default(0)->after('cost_price'); // Маржа в %
        $table->decimal('revenue_30d', 12, 2)->default(0)->after('margin_30d'); // Выручка
        $table->char('abc_class', 1)->nullable()->after('revenue_30d'); // Класс A, B или C
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
