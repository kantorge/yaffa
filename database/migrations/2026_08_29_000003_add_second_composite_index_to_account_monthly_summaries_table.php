<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The existing account_monthly_summaries_user_type_dtype_account_date_index
 * (user_id, transaction_type, data_type, account_entity_id, date) correctly
 * serves the budget-summary write query in
 * App\Jobs\CalculateAccountMonthlySummary (filters user_id + transaction_type
 * + data_type) and is left untouched here.
 *
 * ReportApiController::getCashflowData filters user_id (always), data_type
 * (conditionally), account_entity_id (conditionally) but NEVER
 * transaction_type — so with only the existing index, MySQL can use only
 * user_id as a seek prefix: transaction_type sits unbound at position 2,
 * blocking use of data_type/account_entity_id as index range columns even
 * though they're filtered. This report/dashboard query runs on every page
 * load with no date bound, so it currently walks every summary row for the
 * user instead of seeking.
 *
 * This new index matches that query's actual filter order.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('account_monthly_summaries', function (Blueprint $table) {
            $table->index(
                ['user_id', 'data_type', 'account_entity_id', 'date'],
                'account_monthly_summaries_user_dtype_account_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('account_monthly_summaries', function (Blueprint $table) {
            $table->dropIndex('account_monthly_summaries_user_dtype_account_date_index');
        });
    }
};
