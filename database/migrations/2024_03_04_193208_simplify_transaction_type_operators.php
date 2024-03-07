<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create new columns for the operators as multipliers
        Schema::table('transaction_types', function ($table) {
            $table->smallInteger('amount_multiplier')->nullable()->after('amount_operator');
            $table->smallInteger('quantity_multiplier')->nullable()->after('quantity_operator');
        });

        // Set the multipliers for the existing transaction types
        $transactionTypes = App\Models\TransactionType::all();
        $transactionTypes->each(function ($transactionType) {
            if ($transactionType->amount_operator === 'plus') {
                $transactionType->amount_multiplier = 1;
            } elseif ($transactionType->amount_operator === 'minus') {
                $transactionType->amount_multiplier = -1;
            }

            if ($transactionType->quantity_operator === 'plus') {
                $transactionType->quantity_multiplier = 1;
            } elseif ($transactionType->quantity_operator === 'minus') {
                $transactionType->quantity_multiplier = -1;
            }

            $transactionType->saveQuietly();
        });

        // Remove the old columns
        Schema::table('transaction_types', function ($table) {
            $table->dropColumn('amount_operator');
            $table->dropColumn('quantity_operator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the old columns
        Schema::table('transaction_types', function ($table) {
            $table->string('amount_operator')->nullable();
            $table->string('quantity_operator')->nullable();
        });

        // Set the old operators for the existing transaction types
        $transactionTypes = App\Models\TransactionType::all();
        $transactionTypes->each(function ($transactionType) {
            if ($transactionType->amount_multiplier === 1) {
                $transactionType->amount_operator = 'plus';
            } elseif ($transactionType->amount_multiplier === -1) {
                $transactionType->amount_operator = 'minus';
            }

            if ($transactionType->quantity_multiplier === 1) {
                $transactionType->quantity_operator = 'plus';
            } elseif ($transactionType->quantity_multiplier === -1) {
                $transactionType->quantity_operator = 'minus';
            }

            $transactionType->saveQuietly();
        });

        // Remove the new columns
        Schema::table('transaction_types', function ($table) {
            $table->dropColumn('amount_multiplier');
            $table->dropColumn('quantity_multiplier');
        });
    }
};
