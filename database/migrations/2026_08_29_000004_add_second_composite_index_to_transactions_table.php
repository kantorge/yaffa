<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The existing transactions_user_type_flags_date_index
 * (user_id, config_type, schedule, date) is left untouched here — other call
 * sites (getScheduledItems, budgetChart) rely on date being the range column
 * immediately after schedule.
 *
 * TransactionApiController::findTransactions (the find-transactions report)
 * applies whereIn('transaction_type', ...) as a plain post-filter with no
 * supporting index, and its date range (FindTransactionsRequest's
 * date_from/date_to) is OPTIONAL — a user can legally request "every
 * withdrawal ever" with no date bound, which currently means scanning the
 * user's entire non-scheduled standard-transaction history row-by-row to
 * test transaction_type.
 *
 * This new index puts transaction_type ahead of date so that filter
 * combination can seek instead of scan.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(
                ['user_id', 'config_type', 'schedule', 'transaction_type', 'date'],
                'transactions_user_type_schedule_ttype_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_type_schedule_ttype_date_index');
        });
    }
};
