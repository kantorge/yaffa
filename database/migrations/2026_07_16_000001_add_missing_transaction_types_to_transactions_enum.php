<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY transaction_type ENUM('withdrawal','deposit','transfer','buy','sell','add_shares','remove_shares','dividend','interest_yield','purchased_interest','product_fee','tax_relief') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $unsupportedRowsCount = DB::table('transactions')
            ->whereIn('transaction_type', ['purchased_interest', 'product_fee', 'tax_relief'])
            ->count();

        if ($unsupportedRowsCount > 0) {
            throw new RuntimeException(
                'Cannot rollback migration because transactions use newly introduced transaction types: '
                . $unsupportedRowsCount
            );
        }

        DB::statement("ALTER TABLE transactions MODIFY transaction_type ENUM('withdrawal','deposit','transfer','buy','sell','add_shares','remove_shares','dividend','interest_yield') NOT NULL");
    }
};