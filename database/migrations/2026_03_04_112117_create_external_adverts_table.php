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
    Schema::create('external_adverts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('store_id')->constrained()->cascadeOnDelete(); // <--- ДОБАВИЛИ ЭТО
        $table->foreignId('product_id')->constrained()->cascadeOnDelete(); 
        $table->string('blogger_link');
        $table->decimal('ad_cost', 10, 2)->default(0); 
        $table->decimal('ad_spent', 10, 2)->default(0); 
        $table->string('platform'); 
        $table->json('formats'); 
        $table->date('release_date');
        $table->string('status')->default('not_published');
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_adverts');
    }
};
