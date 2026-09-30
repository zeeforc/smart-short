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
        Schema::create('normalized_products', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_name')->unique();
            $table->decimal('lowest_price', 15, 2)->default(0);
            $table->foreignId('best_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->json('price_history_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('normalized_products');
    }
};
