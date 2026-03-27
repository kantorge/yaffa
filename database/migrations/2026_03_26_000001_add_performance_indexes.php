<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance-oriented database indexes identified through query pattern analysis.
 *
 * Rationale for each index is documented inline. All indexes are non-unique and
 * additive — no existing constraints or data are modified.
 */
return new class () extends Migration {
    public function up(): void
    {
        // ----------------------------------------------------------------
        // transactions
        // ----------------------------------------------------------------
        // The single most-queried table (30 k+ standard + 1 k investment rows).
        // Almost every read hits: user_id + config_type + schedule/budget flags
        // + a date range. A composite covering those five columns turns full
        // user-scoped table scans into narrow range scans.
        //
        // Query patterns served:
        //   AccountMonthlySummary::calculateAccountBalanceFact (date range + flags)
        //   TransactionApiController::findTransactions          (user + type + flags + date)
        //   ReportApiController::budgetChart / waterfall        (user + type + flags)
        //   InvestmentService::getCurrentQuantity               (user + type + flags)
        //   RecordScheduledTransactions command                 (user + schedule flag)
        //   PayeeCategoryStatsService                           (user + type + flags + date)
        //
        // Column order: equality columns first (user_id, config_type, schedule, budget),
        // then the range column (date) last, so MySQL can skip straight to the right
        // rows after applying all equality filters.
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(
                ['user_id', 'config_type', 'schedule', 'budget', 'date'],
                'transactions_user_type_flags_date_index'
            );
        });

        // ----------------------------------------------------------------
        // account_monthly_summaries
        // ----------------------------------------------------------------
        // Balance and cashflow queries always filter on:
        //   user_id + transaction_type + data_type + account_entity_id
        // and optionally group/filter by date.
        //
        // The investment-value sub-query needs MAX(date) per
        // (user_id, transaction_type, data_type, account_entity_id); with this
        // covering index MySQL can resolve that aggregation entirely from the index
        // without touching the clustered rows.
        //
        // Query patterns served:
        //   AccountApiController::getAccountBalance (standard + investment SUM/MAX)
        //   ReportApiController cash-flow range queries
        //   CalculateAccountMonthlySummary job writes + reads
        Schema::table('account_monthly_summaries', function (Blueprint $table) {
            $table->index(
                ['user_id', 'transaction_type', 'data_type', 'account_entity_id', 'date'],
                'account_monthly_summaries_user_type_dtype_account_date_index'
            );
        });

        // ----------------------------------------------------------------
        // investment_prices
        // ----------------------------------------------------------------
        // InvestmentService::getLatestStoredPrice uses:
        //   WHERE investment_id = ? AND date <= ? ORDER BY date DESC LIMIT 1
        //
        // The existing UNIQUE KEY is (date, investment_id) — date-first — so it
        // cannot support this access pattern efficiently. The existing plain
        // KEY on investment_id alone forces a full per-investment scan to find
        // the latest qualifying row.
        //
        // A (investment_id, date) composite lets MySQL do a single range seek:
        // find all rows for the investment, then read backwards from the given
        // date to get the first match.
        Schema::table('investment_prices', function (Blueprint $table) {
            $table->index(
                ['investment_id', 'date'],
                'investment_prices_investment_date_index'
            );
        });

        // ----------------------------------------------------------------
        // currency_rates
        // ----------------------------------------------------------------
        // CurrencyRateService / CurrencyTrait lookups for a specific pair use:
        //   WHERE from_id = ? AND to_id = ? AND date <= ? ORDER BY date DESC LIMIT 1
        //
        // The existing UNIQUE KEY is (date, from_id, to_id) — date-first.
        // Individual FK indexes exist on from_id and to_id separately, but
        // neither supports the "find latest rate for a pair before a date" pattern.
        //
        // With 45 k rows spread over ~10 currencies, this can save many rows
        // per lookup compared to a full from_id scan.
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->index(
                ['from_id', 'to_id', 'date'],
                'currency_rates_from_to_date_index'
            );
        });

        // ----------------------------------------------------------------
        // transaction_schedules
        // ----------------------------------------------------------------
        // RecordScheduledTransactions command uses:
        //   WHERE active = 1 AND next_date <= ? AND automatic_recording = 1
        //   (via whereHas on the parent Transaction)
        //
        // Without an index MySQL must scan all ~300 schedule rows; negligible
        // today but useful as the table grows. Including next_date in the index
        // also speeds up any date-based schedule display queries.
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->index(
                ['active', 'next_date'],
                'transaction_schedules_active_next_date_index'
            );
        });

        // ----------------------------------------------------------------
        // account_entities
        // ----------------------------------------------------------------
        // Very frequent pattern: list all active accounts (or payees) for a user.
        //   WHERE user_id = ? AND config_type = 'account' AND active = 1
        //
        // The existing UNIQUE KEY (config_type, name, user_id) has user_id last
        // and includes name as the middle column, so it is not selective for
        // this filter. The plain user_id FK index requires MySQL to load every
        // entity for the user and then apply config_type/active filters.
        Schema::table('account_entities', function (Blueprint $table) {
            $table->index(
                ['user_id', 'config_type', 'active'],
                'account_entities_user_type_active_index'
            );
        });

    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_type_flags_date_index');
        });

        Schema::table('account_monthly_summaries', function (Blueprint $table) {
            $table->dropIndex('account_monthly_summaries_user_type_dtype_account_date_index');
        });

        Schema::table('investment_prices', function (Blueprint $table) {
            $table->dropIndex('investment_prices_investment_date_index');
        });

        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropIndex('currency_rates_from_to_date_index');
        });

        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->dropIndex('transaction_schedules_active_next_date_index');
        });

        Schema::table('account_entities', function (Blueprint $table) {
            $table->dropIndex('account_entities_user_type_active_index');
        });

    }
};
