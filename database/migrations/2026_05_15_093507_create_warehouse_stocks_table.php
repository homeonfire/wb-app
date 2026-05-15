<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            
            $table->unsignedBigInteger('nm_id');
            $table->unsignedBigInteger('chrt_id'); // ID размера от WB
            $table->unsignedBigInteger('warehouse_id');
            $table->string('warehouse_name');
            $table->string('region_name')->nullable();
            
            $table->integer('quantity')->default(0);
            $table->integer('in_way_to_client')->default(0);
            $table->integer('in_way_from_client')->default(0);
            
            $table->timestamps();

            // Составной уникальный индекс для быстрого Upsert
            $table->unique(['nm_id', 'chrt_id', 'warehouse_id'], 'wh_stock_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};