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
            $table->string('status')->default('matched')->after('normalized_product_id');
            $table->foreignId('normalized_product_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_aliases', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
