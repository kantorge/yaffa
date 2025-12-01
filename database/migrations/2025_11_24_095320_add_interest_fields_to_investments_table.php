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
            $table->decimal('interest_rate', 5, 4)->nullable()->after('price_factor')->comment('Annual interest rate as decimal (e.g., 0.0450 for 4.5%)');
            $table->date('maturity_date')->nullable()->after('interest_rate')->comment('Date when fixed term investment matures');
            $table->date('last_interest_payment_date')->nullable()->after('maturity_date')->comment('Last date interest was calculated/paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'maturity_date', 'last_interest_payment_date']);
        });
    }
};
