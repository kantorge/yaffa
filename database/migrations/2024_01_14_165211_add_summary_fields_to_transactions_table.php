<?php

use Illuminate\Database\Eloquent\Collection;
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

        App\Models\Transaction::with(['transactionType', 'config'])
            ->chunkById(1000, function (Collection $transactions) {

                $transactions->each(function ($transaction) {
                    $transactionService = new App\Services\TransactionService();
                    $transaction->currency_id = $transactionService->getTransactionCurrencyId($transaction);
                    $transaction->cashflow_value = $transactionService->getTransactionCashFlow($transaction);

                    if ($transaction->currency_id === null && $transaction->cashflow_value === null) {
                        return;
                    }

                    $transaction->saveQuietly();
                });

                echo 'Processed ' . $transactions->count() . ' transactions' . PHP_EOL;
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
