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

        // Run a direct DB update query to set the currency_id and cashflow_value
        // This is a one-time operation, so it's fine to use a direct query, which is needed for performance
        // It is the clone of the getTransactionCurrencyId and getTransactionCashflowValue methods from TransactionService
        DB::update("
            WITH calculations AS (
            SELECT
              t.id,
              tt.type,
              tt.name,
              CASE tt.type
                WHEN 'standard' THEN
                  CASE tt.name
                    WHEN 'withdrawal' THEN tds.amount_from * -1
                    WHEN 'deposit' THEN tds.amount_to
                    ELSE null
                  END
                WHEN 'investment' THEN
                   (IFNULL(tdi.price, 0) * IFNULL(tdi.quantity, 0)) * IF(tt.amount_operator = 'minus', -1, 1)
                   + IFNULL(tdi.dividend, 0)
                   - IFNULL(tdi.tax, 0)
                   - IFNULL(tdi.commission, 0)
              END AS cashflow_value,
              CASE tt.type
                WHEN 'standard' THEN
                  CASE tt.name
                    WHEN 'withdrawal' THEN
                      (SELECT a.currency_id FROM accounts AS a LEFT JOIN account_entities AS ae ON ae.config_id = a.id AND ae.config_type = 'account' WHERE ae.id = tds.account_from_id)
                    WHEN 'deposit' THEN
                      (SELECT a.currency_id FROM accounts AS a LEFT JOIN account_entities AS ae ON ae.config_id = a.id AND ae.config_type = 'account' WHERE ae.id = tds.account_to_id)
                    ELSE null
                  END
                WHEN 'investment' THEN
                  (SELECT a.currency_id FROM accounts AS a LEFT JOIN account_entities AS ae ON ae.config_id = a.id AND ae.config_type = 'account' WHERE ae.id = tdi.account_id)
              END AS currency_id
              
            FROM transactions AS t
            
            LEFT JOIN transaction_types AS tt ON t.transaction_type_id = tt.id
            LEFT JOIN transaction_details_standard AS tds ON t.config_id = tds.id AND t.config_type = 'standard'
            LEFT JOIN transaction_details_investment AS tdi ON t.config_id = tdi.id AND t.config_type = 'investment'
            )
            
            UPDATE transactions
            SET
                currency_id = (SELECT calculations.currency_id FROM calculations WHERE calculations.id = transactions.id),
                cashflow_value = (SELECT calculations.cashflow_value FROM calculations WHERE calculations.id = transactions.id)
        ");
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
