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
     *
     * Narrowing back to DECIMAL(10,4) is only safe if every stored value still fits that
     * scale/range - a row written after the widening with more than 4 fractional digits, or
     * a magnitude above 999999.9999, would silently lose precision or overflow otherwise.
     */
    public function down(): void
    {
        if (! Schema::hasTable('transaction_details_investment') || ! Schema::hasColumn('transaction_details_investment', 'price')) {
            return;
        }

        $outOfRange = DB::table('transaction_details_investment')
            ->whereNotNull('price')
            ->where(function ($query) {
                $query->whereRaw('price != ROUND(price, 4)')
                    ->orWhere('price', '>', '999999.9999');
            })
            ->exists();

        if ($outOfRange) {
            throw new RuntimeException(
                'Cannot reverse transaction_details_investment.price to DECIMAL(10,4): '
                . 'one or more rows have more than 4 fractional digits or exceed 999999.9999. '
                . 'Resolve or round these rows before rolling back this migration.'
            );
        }

        DB::statement('ALTER TABLE `transaction_details_investment` MODIFY `price` DECIMAL(10,4) UNSIGNED NULL');
    }
};
