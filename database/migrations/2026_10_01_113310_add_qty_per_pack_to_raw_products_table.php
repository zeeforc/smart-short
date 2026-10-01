<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_products', function (Blueprint $table) {
            // Units per package for suppliers that price per box/pack, not per unit.
            // Defaults to 1 (per-unit pricing). Must be set correctly for per-box pricelist sources.
            $table->unsignedInteger('qty_per_pack')->default(1)->after('raw_unit');
        });
    }

    public function down(): void
    {
        Schema::table('raw_products', function (Blueprint $table) {
            $table->dropColumn('qty_per_pack');
        });
    }
};
