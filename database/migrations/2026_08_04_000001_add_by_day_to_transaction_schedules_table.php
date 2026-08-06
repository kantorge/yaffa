<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds optional RFC5545 ordinal-weekday recurrence support to schedules, e.g.
 * "first Wednesday of every month" (by_day=1WE) or "last Friday of November,
 * every year" (by_day=-1FR, by_month=11). Both columns are nullable and
 * additive: existing schedules are unaffected, since TransactionSchedule::
 * getRecurrence() only applies them when by_day is set.
 *
 * down() note: dropping these columns is lossy, not merely a schema change,
 * for any schedule that has adopted an ordinal-weekday rule by the time of
 * rollback - TransactionSchedule::getRecurrence() would silently fall back
 * to plain anniversary-of-start-date semantics for that schedule. This is
 * only a concern for an ops rollback after the feature has already been
 * used in production, not for the normal upgrade path.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->string('by_day', 4)->nullable()->after('interval');
            $table->unsignedTinyInteger('by_month')->nullable()->after('by_day');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->dropColumn(['by_day', 'by_month']);
        });
    }
};
