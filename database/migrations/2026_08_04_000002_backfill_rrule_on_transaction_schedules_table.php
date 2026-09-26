<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Recurr\Rule;

/**
 * Second of the 3-migration sequence (see 2026_08_04_000001's docblock). Backfills the `rrule`
 * column added by that migration from every row's existing frequency/interval/count/end_date
 * (and by_day/by_month, only present on a database that already ran this table's original,
 * unshipped by_day/by_month migration - a real upgrade from 3.x never has them) columns. Ships to
 * every installation - this is real operator data, not a throwaway/dev-only step.
 *
 * Duplicates the pre-redesign RecurrenceRuleService::buildRule() column assembly (that method's
 * old 7-parameter shape no longer exists in this codebase) purely to reconstruct the equivalent
 * RRULE string from each row's old columns. Kept here rather than reused from
 * App\Models\Concerns\HasRecurrenceRule because that trait composes from an Eloquent model's
 * *current* attributes, which no longer include these raw columns at all once the model layer is
 * updated - this migration works directly against the old row shape via the query builder.
 *
 * Reversible: down() simply clears `rrule` back to null, since the old columns are untouched
 * until 2026_08_04_000003 drops them.
 */
return new class () extends Migration {
    public function up(): void
    {
        $hasByDay = Schema::hasColumn('transaction_schedules', 'by_day');
        $hasByMonth = Schema::hasColumn('transaction_schedules', 'by_month');

        DB::table('transaction_schedules')->orderBy('id')->each(function ($row) use ($hasByDay, $hasByMonth) {
            $rrule = $this->buildRruleString($row, $hasByDay, $hasByMonth);

            DB::table('transaction_schedules')->where('id', $row->id)->update(['rrule' => $rrule]);
        });
    }

    public function down(): void
    {
        DB::table('transaction_schedules')->update(['rrule' => null]);
    }

    private function buildRruleString(object $row, bool $hasByDay, bool $hasByMonth): string
    {
        $rule = (new Rule())
            ->setStartDate(new DateTime($row->start_date))
            ->setFreq($row->frequency)
            ->setInterval(max((int) ($row->interval ?? 1), 1));

        if ($row->end_date) {
            $rule->setUntil(new DateTime($row->end_date));
        }

        if ($row->count) {
            $rule->setCount((int) $row->count);
        }

        $byDay = $hasByDay ? $row->by_day : null;
        $byMonth = $hasByMonth ? $row->by_month : null;

        if ($byDay) {
            $rule->setByDay([$byDay]);

            if ($row->frequency === 'YEARLY' && $byMonth) {
                $rule->setByMonth([(int) $byMonth]);
            }
        }

        return $rule->getString();
    }
};
