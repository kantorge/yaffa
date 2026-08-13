<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Widens transaction_details_investment.price from DECIMAL(10,4) to
     * DECIMAL(20,10), matching investment_prices.price's scale for the same
     * logical value (an investment's price at a point in time).
     */
    public function up(): void
    {
        if (! Schema::hasTable('transaction_details_investment') || ! Schema::hasColumn('transaction_details_investment', 'price')) {
            return;
        }

        DB::statement('ALTER TABLE `transaction_details_investment` MODIFY `price` DECIMAL(20,10) UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('transaction_details_investment') || ! Schema::hasColumn('transaction_details_investment', 'price')) {
            return;
        }

        DB::statement('ALTER TABLE `transaction_details_investment` MODIFY `price` DECIMAL(10,4) UNSIGNED NULL');
    }
};
