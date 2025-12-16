<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->date('date')->index();
            $table->bigInteger('nm_id')->index(); // Артикул WB
            
            // Основная инфо о товаре (из json)
            $table->string('vendor_code')->nullable(); // vendorCode
            $table->string('brand_name')->nullable();  // brandName
            $table->bigInteger('object_id')->nullable(); // object.id (ID предмета/категории)
            $table->string('object_name')->nullable();   // object.name (Название предмета)

            // Воронка (selectedPeriod) - Количества
            $table->integer('open_card_count')->default(0);  // openCardCount
            $table->integer('add_to_cart_count')->default(0); // addToCartCount
            $table->integer('orders_count')->default(0);      // ordersCount
            $table->integer('buyouts_count')->default(0);     // buyoutsCount
            $table->integer('cancel_count')->default(0);      // cancelCount

            // Финансы (selectedPeriod) - Суммы
            $table->decimal('orders_sum_rub', 15, 2)->default(0);  // ordersSumRub
            $table->decimal('buyouts_sum_rub', 15, 2)->default(0); // buyoutsSumRub
            $table->decimal('cancel_sum_rub', 15, 2)->default(0);  // cancelSumRub
            $table->decimal('avg_price_rub', 15, 2)->default(0);   // avgPriceRub

            // Средние показатели
            $table->decimal('avg_orders_count_per_day', 10, 2)->default(0); // avgOrdersCountPerDay

            // Конверсии (selectedPeriod -> conversions)
            $table->integer('conversion_open_to_cart_percent')->default(0); // addToCartPercent
            $table->integer('conversion_cart_to_order_percent')->default(0); // cartToOrderPercent
            $table->integer('conversion_buyouts_percent')->default(0);      // buyoutsPercent

            // Остатки (stocks)
            $table->integer('stocks_mp')->default(0); // stocksMp (Склад продавца)
            $table->integer('stocks_wb')->default(0); // stocksWb (Склад WB)

            $table->timestamps();

            // Уникальный индекс, чтобы не дублировать записи за один день
            $table->unique(['store_id', 'nm_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_analytics');
    }
};