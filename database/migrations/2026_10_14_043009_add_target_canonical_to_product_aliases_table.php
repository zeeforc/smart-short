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
        Schema::table('product_aliases', function (Blueprint $table) {
            $table->string('target_canonical')->nullable()->after('normalized_product_id')->index();

            // Ubah foreign key constraint dari cascadeOnDelete ke nullOnDelete agar riwayat alias tidak terhapus
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['normalized_product_id']);
                $table->foreign('normalized_product_id')
                    ->references('id')
                    ->on('normalized_products')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_aliases', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['normalized_product_id']);
                $table->foreign('normalized_product_id')
                    ->references('id')
                    ->on('normalized_products')
                    ->cascadeOnDelete();
            }

            $table->dropColumn('target_canonical');
        });
    }
};
