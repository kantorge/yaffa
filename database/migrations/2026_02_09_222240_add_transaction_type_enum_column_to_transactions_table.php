<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This variable contains the hard coded list of supported transaction types
        // This label is currently not used in the database, but it maps the necessary migration logic
        $supportedTransactionTypes = [
            1 => 'withdrawal',
            2 => 'deposit',
            3 => 'transfer',
            4 => 'buy',
            5 => 'sell',
            6 => 'add_shares',
            7 => 'remove_shares',
            8 => 'dividend',
            // 9 and 10 are unused
            11 => 'interest_yield',
        ];

        $unsupportedTransactionTypes = [9, 10];

        // Run a check to see if there are any transactions with unsupported transaction types before starting the migration
        $unsupportedTransactionCount = DB::table('transactions')
            ->whereIn('transaction_type_id', $unsupportedTransactionTypes)
            ->count();

        if ($unsupportedTransactionCount > 0) {
            throw new RuntimeException(
                'Unsupported legacy transaction types found in ' . $unsupportedTransactionCount . ' transactions. '
                . 'Only mapped enum values can be migrated. Please fix the data before re-running migration.'
            );
        }

        // Start performing the migration
        Schema::table('transactions', function (Blueprint $table) use ($supportedTransactionTypes) {
            $table->enum(
                'transaction_type',
                array_values($supportedTransactionTypes)
                )
                ->after('date')
                ->nullable();
        });

        // Dynamically build the SQL CASE statement for updating the new enum column based on the mapping of legacy transaction type IDs to new enum values
        $updateSqlCases = [];

        foreach ($supportedTransactionTypes as $legacyId => $transactionType) {
            $updateSqlCases[] = "WHEN {$legacyId} THEN '{$transactionType}'";
        }

        DB::statement(
            'UPDATE transactions '
            . 'SET transaction_type = CASE transaction_type_id '
            . implode(' ', $updateSqlCases)
            . ' END '
            . 'WHERE transaction_type_id IS NOT NULL'
        );

        // Verify that all transactions have been migrated successfully before dropping the old transaction_type_id column and the transaction_types table
        $unmigratedTransactions = DB::table('transactions')
            ->whereNull('transaction_type')
            ->count();

        if ($unmigratedTransactions > 0) {
            throw new RuntimeException(
                "Transaction type migration failed: {$unmigratedTransactions} transactions could not be mapped."
            );
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('transactions_transaction_type_id_foreign');
            $table->dropColumn('transaction_type_id');
        });

        $enumSqlValues = implode(
            ',',
            array_map(fn (string $value) => "'{$value}'", $supportedTransactionTypes)
        );

        DB::statement("ALTER TABLE transactions MODIFY transaction_type ENUM({$enumSqlValues}) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting this migration is not supported
    }
};
