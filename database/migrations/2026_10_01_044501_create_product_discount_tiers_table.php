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
        Schema::create('product_discount_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('product_name')->comment('Nama/Kode obat untuk pencarian (Buku Defecta)');
            $table->integer('min_qty')->comment('Minimal kuantitas untuk mendapat diskon ini');
            $table->decimal('discount_pct', 5, 2)->comment('Persentase diskon (misal 10.50)');
            $table->timestamps();

            // Mencegah duplikasi: 1 obat dari 1 supplier tidak boleh punya aturan min_qty yang sama berulang kali
            $table->unique(['supplier_id', 'product_name', 'min_qty'], 'prod_discount_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_discount_tiers');
    }
};
