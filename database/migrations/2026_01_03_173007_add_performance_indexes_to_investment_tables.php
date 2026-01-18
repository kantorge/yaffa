<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add index for investment_prices queries (investment_id, date)
        // This supports: WHERE investment_id = ? AND date <= ? ORDER BY date
        Schema::table('investment_prices', function (Blueprint $table) {
            $table->index(['investment_id', 'date'], 'idx_investment_prices_lookup');
        });

        // Add indexes for transaction queries used in CalculateAccountMonthlySummary
        Schema::table('transaction_details_investment', function (Blueprint $table) {
            // For queries filtering by account_id and joining with transactions
            $table->index(['account_id', 'investment_id'], 'idx_account_investment');
            // For price lookups in transactions
            $table->index(['investment_id', 'price'], 'idx_investment_price');
        });

        // Add composite index for transaction queries
        Schema::table('transactions', function (Blueprint $table) {
            // For queries filtering by config_type, schedule, and date
            $table->index(['config_type', 'schedule', 'date'], 'idx_config_schedule_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_prices', function (Blueprint $table) {
            $table->dropIndex('idx_investment_prices_lookup');
        });

        Schema::table('transaction_details_investment', function (Blueprint $table) {
            $table->dropIndex('idx_account_investment');
            $table->dropIndex('idx_investment_price');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_config_schedule_date');
        });
    }
};
