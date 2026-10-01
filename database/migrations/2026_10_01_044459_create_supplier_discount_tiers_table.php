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
        Schema::create('supplier_discount_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->integer('min_qty')->comment('Minimal kuantitas untuk mendapat diskon ini');
            $table->decimal('discount_pct', 5, 2)->comment('Persentase diskon (misal 10.50)');
            $table->timestamps();

            // Mencegah duplikasi: 1 supplier tidak boleh punya aturan min_qty yang sama berulang kali
            $table->unique(['supplier_id', 'min_qty']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_discount_tiers');
    }
};
