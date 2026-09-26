<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First of a 3-migration sequence collapsing transaction_schedules' recurrence shape
 * (frequency/interval/count/end_date/by_day/by_month) into a single `rrule` (RFC 5545 RRULE
 * string) column - see .ai/docs/specifications/budget-schedule-redesign/recurrence-rrule-storage.md.
 *
 * Unlike create_budgets_table (rewritten in place elsewhere in this same feature, since `budgets`
 * itself is new and unshipped), transaction_schedules predates this branch: frequency/interval/
 * count/end_date are real 3.x-shipped columns with real operator data. `by_day`/`by_month` are the
 * only two columns this migration originally added, and are the only part of this table's shape
 * that never shipped externally - safe to drop directly with no transform (see step 3).
 *
 * This step only adds `rrule`, nullable, so the old columns remain fully readable for
 * 2026_08_04_000002 (the backfill) to populate it from. Splitting add/backfill/drop into 3
 * migrations - rather than one file, as create_budgets_table's sibling changes can afford - is
 * the same guard-before-DDL discipline already established by
 * 2026_08_05_000002/000003 (transform_budget_transactions_to_budgets /
 * drop_budget_column_and_enforce_account_not_null): a destructive column drop must never run in
 * the same migration as the data it depends on being read.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->text('rrule')->nullable()->after('next_date');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->dropColumn('rrule');
        });
    }
};
