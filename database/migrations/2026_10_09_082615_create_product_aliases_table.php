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
        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('normalized_product_id')->constrained('normalized_products')->cascadeOnDelete();
            $table->string('alias_raw')->index();
            $table->string('alias_normalized')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_aliases');
    }
};
