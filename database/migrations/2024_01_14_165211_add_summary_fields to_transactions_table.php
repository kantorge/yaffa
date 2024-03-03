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
        // Create the column as a shortcut to the currency of the config
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('currency_id')
                ->after('config_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->decimal('cashflow_value', 12, 4)
                ->after('currency_id')
                ->nullable();
        });

        // Write the data for all existing transactions of all users
        App\Models\Transaction::all()->load([
            'transactionType',
            'config'
        ])
            ->each(function ($transaction) {
                $transactionService = new App\Services\TransactionService();

                $transaction->currency_id = $transactionService->getTransactionCurrencyId($transaction);
                $transaction->cashflow_value = $transactionService->getTransactionCashFlow($transaction);

                if ($transaction->currency_id === null && $transaction->cashflow_value === null) {
                    return;
                }

                $transaction->save();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
            $table->dropColumn('cashflow_value');
        });
    }
};
