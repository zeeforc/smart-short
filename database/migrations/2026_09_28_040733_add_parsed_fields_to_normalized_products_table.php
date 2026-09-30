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
        Schema::table('normalized_products', function (Blueprint $table) {
            $table->string('parsed_name')->nullable();
            $table->string('parsed_strength')->nullable();
            $table->string('parsed_form')->nullable();
            // normalized_name is already a string, we will just use that to store the canonical string.
            // But just in case, we can keep using normalized_name as the unique canonical key.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('normalized_products', function (Blueprint $table) {
            $table->dropColumn(['parsed_name', 'parsed_strength', 'parsed_form']);
        });
    }
};
