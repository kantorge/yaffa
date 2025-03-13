<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to increase the allowed value and decimal places for the investment price.
     */
    public function up(): void
    {
        Schema::table('investment_prices', function (Blueprint $table) {
            $table->decimal('price', 20, 10)->change();
        });
    }

    /**
     * Reverse the migrations to the original state.
     * WARNING: This can result in data loss.
     */
    public function down(): void
    {
        Schema::table('investment_prices', function (Blueprint $table) {
            $table->decimal('price', 10, 4)->change();
        });
    }
};
