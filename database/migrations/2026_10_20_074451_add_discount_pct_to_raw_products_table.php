<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_products', function (Blueprint $table) {
            $table->decimal('discount_pct', 8, 4)->default(0)->after('discount')
                ->comment('Persentase diskon asli dari file supplier (0-100)');
        });
    }

    public function down(): void
    {
        Schema::table('raw_products', function (Blueprint $table) {
            $table->dropColumn('discount_pct');
        });
    }
};
