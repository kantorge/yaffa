<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each of these single-column indexes is a left prefix of a wider composite
 * index that already exists on the same table, so it serves no query the
 * composite doesn't already cover — it only adds write overhead. MySQL still
 * satisfies each column's FOREIGN KEY index requirement via the composite's
 * leftmost column, so these can be dropped without touching the FKs
 * themselves (same reasoning as
 * 2026_07_07_000001_drop_redundant_config_id_config_type_index_from_transactions_table).
 *
 *   table                       dropped index                              subsumed by
 *   account_entities            account_entities_user_id_foreign          (user_id, config_type, active)
 *   account_monthly_summaries   account_monthly_summaries_user_id_foreign (user_id, transaction_type, data_type, account_entity_id, date)
 *   investment_prices           investment_prices_investment_id_foreign   (investment_id, date)
 *   transactions                transactions_user_id_foreign              (user_id, config_type, schedule, budget, date)
 *   currency_rates              currency_rates_from_id_foreign            (from_id, to_id, date)
 *   category_learning           category_learning_user_id_index           (user_id, item_description) [unique]
 */
return new class () extends Migration {
    public function up(): void
    {
        // Guarded with hasIndex(): on instances upgraded incrementally from
        // older releases, some of these single-column indexes may already be
        // gone (e.g. dropped manually, or by a partially-applied earlier run
        // of this same migration, since MySQL DDL auto-commits statement by
        // statement regardless of the migration's own transaction).
        if (Schema::hasIndex('account_entities', 'account_entities_user_id_foreign')) {
            Schema::table('account_entities', function (Blueprint $table) {
                $table->dropIndex('account_entities_user_id_foreign');
            });
        }

        if (Schema::hasIndex('account_monthly_summaries', 'account_monthly_summaries_user_id_foreign')) {
            Schema::table('account_monthly_summaries', function (Blueprint $table) {
                $table->dropIndex('account_monthly_summaries_user_id_foreign');
            });
        }

        if (Schema::hasIndex('investment_prices', 'investment_prices_investment_id_foreign')) {
            Schema::table('investment_prices', function (Blueprint $table) {
                $table->dropIndex('investment_prices_investment_id_foreign');
            });
        }

        if (Schema::hasIndex('transactions', 'transactions_user_id_foreign')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex('transactions_user_id_foreign');
            });
        }

        if (Schema::hasIndex('currency_rates', 'currency_rates_from_id_foreign')) {
            Schema::table('currency_rates', function (Blueprint $table) {
                $table->dropIndex('currency_rates_from_id_foreign');
            });
        }

        if (Schema::hasIndex('category_learning', 'category_learning_user_id_index')) {
            Schema::table('category_learning', function (Blueprint $table) {
                $table->dropIndex('category_learning_user_id_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('account_entities', 'account_entities_user_id_foreign')) {
            Schema::table('account_entities', function (Blueprint $table) {
                $table->index('user_id', 'account_entities_user_id_foreign');
            });
        }

        if (! Schema::hasIndex('account_monthly_summaries', 'account_monthly_summaries_user_id_foreign')) {
            Schema::table('account_monthly_summaries', function (Blueprint $table) {
                $table->index('user_id', 'account_monthly_summaries_user_id_foreign');
            });
        }

        if (! Schema::hasIndex('investment_prices', 'investment_prices_investment_id_foreign')) {
            Schema::table('investment_prices', function (Blueprint $table) {
                $table->index('investment_id', 'investment_prices_investment_id_foreign');
            });
        }

        if (! Schema::hasIndex('transactions', 'transactions_user_id_foreign')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index('user_id', 'transactions_user_id_foreign');
            });
        }

        if (! Schema::hasIndex('currency_rates', 'currency_rates_from_id_foreign')) {
            Schema::table('currency_rates', function (Blueprint $table) {
                $table->index('from_id', 'currency_rates_from_id_foreign');
            });
        }

        if (! Schema::hasIndex('category_learning', 'category_learning_user_id_index')) {
            Schema::table('category_learning', function (Blueprint $table) {
                $table->index('user_id', 'category_learning_user_id_index');
            });
        }
    }
};
