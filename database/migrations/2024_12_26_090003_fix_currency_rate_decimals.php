<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to increase the allowed value and decimal places for the currency rate.
     */
    public function up(): void
    {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->decimal('rate', 20, 10)->change();
        });
    }

    /**
     * Reverse the migrations to the original state.
     * WARNING: This can result in data loss.
     */
    public function down(): void
    {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->decimal('rate', 8, 4)->change();
        });
    }
};
