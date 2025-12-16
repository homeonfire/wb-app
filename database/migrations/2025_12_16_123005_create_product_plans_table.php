<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_plans', function (Blueprint $table) {
            $table->id();
            
            // Привязка к товару (не к размеру!)
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            $table->unsignedSmallInteger('year'); // Год
            $table->unsignedTinyInteger('month'); // Месяц (1-12)
            
            $table->unsignedInteger('orders_plan')->default(0); // План заказов (шт)
            $table->unsignedInteger('sales_plan')->default(0);  // План выкупов (шт)
            
            $table->timestamps();

            // Защита от дублей: для одного товара нельзя создать два плана на один и тот же месяц
            $table->unique(['product_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_plans');
    }
};
