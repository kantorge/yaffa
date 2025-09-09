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
        Schema::table('investments', function (Blueprint $table) {
            $table->string('instrument_type')->default('stock')->after('currency_id');
            $table->string('interest_schedule')->nullable()->after('instrument_type');
            $table->date('maturity_date')->nullable()->after('interest_schedule');
            $table->decimal('apr', 8, 4)->nullable()->comment('Annual Percentage Rate')->after('maturity_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['instrument_type', 'interest_schedule', 'maturity_date', 'apr']);
        });
    }
};