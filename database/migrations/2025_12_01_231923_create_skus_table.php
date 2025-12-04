<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('skus', function (Blueprint $table) {
        $table->id();

        // Связь с товаром
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();

        $table->string('barcode')->unique(); // Штрихкод (уникальный)
        $table->string('tech_size')->nullable(); // Размер (S, M, 42, 44...)

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skus');
    }
};
