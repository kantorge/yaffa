<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Third of the 3-migration sequence (see 2026_08_04_000001's docblock). Must run after
 * 2026_08_04_000002 (the backfill), which guarantees every row has a populated `rrule`.
 *
 * Guard checked first, before any DDL: MySQL DDL statements auto-commit and cannot be rolled
 * back, so if this check were interspersed with (or after) the schema changes below, a failed
 * guard would still leave a partially-applied, corrupted migration behind - same reasoning as
 * 2026_08_05_000003_drop_budget_column_and_enforce_account_not_null's guard.
 *
 * `by_day`/`by_month` are dropped conditionally: a real 3.x-upgrading database never had them
 * (they were never part of a shipped release), but a database that already ran this table's
 * original, unshipped by_day/by_month migration still has them.
 *
 * down() note: this is a lossy rollback, same as the migration it replaces - reconstructing
 * frequency/interval/count/end_date/by_day/by_month from an arbitrary `rrule` string is out of
 * scope for a schema rollback; any schedule saved under the rrule-based shape loses its
 * recurrence on downgrade. Only a concern for an ops rollback after this has reached production,
 * not the normal upgrade path.
 */
return new class () extends Migration {
    public function up(): void
    {
        $missingRrule = DB::table('transaction_schedules')
            ->where(function ($query) {
                $query->whereNull('rrule')->orWhere('rrule', '');
            })
            ->count();

        if ($missingRrule > 0) {
            throw new RuntimeException(
                "{$missingRrule} transaction_schedules row(s) still have no `rrule` value. This "
                . 'should be impossible once 2026_08_04_000002_backfill_rrule_on_transaction_schedules_table '
                . 'has run successfully; investigate before re-running this migration.'
            );
        }

        Schema::table('transaction_schedules', function (Blueprint $table) {
            $columnsToDrop = array_filter(
                ['frequency', 'interval', 'by_day', 'by_month', 'count', 'end_date'],
                fn (string $column) => Schema::hasColumn('transaction_schedules', $column)
            );

            $table->dropColumn($columnsToDrop);
        });

        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->text('rrule')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->text('rrule')->nullable()->change();
        });

        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->string('frequency')->after('next_date');
            $table->integer('interval')->default(1)->after('frequency');
            $table->string('by_day', 4)->nullable()->after('interval');
            $table->unsignedTinyInteger('by_month')->nullable()->after('by_day');
            $table->integer('count')->nullable()->after('by_month');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }
};
