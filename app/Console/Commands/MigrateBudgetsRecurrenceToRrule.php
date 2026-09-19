<?php

namespace App\Console\Commands;

use DateTime;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule;
use Throwable;

/**
 * TEMPORARY, NON-SHIPPING, PERSONAL-INSTANCE-ONLY. Unlike transaction_schedules (a real,
 * already-shipped 3.x table - see the 2026_08_04_0000{1,2,3} migration sequence), `budgets` has
 * never shipped to any external installation: every real 3.x->4.0 upgrader gets it created
 * directly with `rrule` by the (already rewritten) 2026_08_05_000001_create_budgets_table
 * migration. The ONLY database that can ever be in a stale state is this branch author's own
 * personal instance, which already ran that migration's pre-rrule-rewrite content (frequency/
 * interval/by_day/by_month/count/end_date as real columns) before the rrule addendum landed -
 * Laravel tracks migrations by filename, so re-deploying the rewritten file never re-runs it
 * there.
 *
 * This is why this is a one-off command, not a guarded/idempotent migration like
 * transaction_schedules' sequence: no future installation - including a fresh install of this
 * exact branch - can ever reach the state this command fixes, so shipping permanent guards for
 * it would be permanent complexity for a condition that can only ever occur once, on one
 * instance.
 *
 * Usage on the affected instance only:
 *   1. php artisan app:dev:migrate-budgets-recurrence-to-rrule
 *   2. Verify the reported counts (migrated should equal total rows, failed should be 0).
 *   3. Manually drop `frequency`/`interval`/`by_day`/`by_month`/`count`/`end_date` from `budgets`
 *      and make `rrule` NOT NULL (a throwaway local migration or manual DDL - your call).
 *   4. Delete this command (and this docblock's instructions become moot) - it must never ship.
 */
#[Signature('app:dev:migrate-budgets-recurrence-to-rrule')]
#[Description('[TEMPORARY/non-shipping/personal-instance-only] Backfill budgets.rrule from the old frequency/interval/by_day/by_month/count/end_date columns.')]
class MigrateBudgetsRecurrenceToRrule extends Command
{
    public function handle(): void
    {
        if (!Schema::hasColumn('budgets', 'frequency')) {
            $this->info('budgets.frequency does not exist - this instance already uses rrule-based storage. Nothing to do.');

            return;
        }

        if (!Schema::hasColumn('budgets', 'rrule')) {
            Schema::table('budgets', fn ($blueprint) => $blueprint->text('rrule')->nullable());
        }

        $migrated = 0;
        $failed = 0;

        DB::table('budgets')->orderBy('id')->each(function ($row) use (&$migrated, &$failed) {
            try {
                $rrule = $this->buildRruleString($row);
                DB::table('budgets')->where('id', $row->id)->update(['rrule' => $rrule]);
                $migrated++;
            } catch (Throwable $exception) {
                $failed++;
                $this->error("  [budgets#{$row->id}] failed to rebuild rrule: {$exception->getMessage()}");
            }
        });

        $this->info("budgets: migrated {$migrated}, failed {$failed}.");
    }

    /**
     * Duplicates the pre-rrule-addendum recurrence assembly (frequency/interval/end_date/count/
     * by_day/by_month only - budgets never had the month-end pattern fields, which are
     * request-only and post-date this table's original shape entirely) purely to reconstruct the
     * equivalent RRULE string from a row's old columns.
     *
     * @throws InvalidRRule
     */
    private function buildRruleString(object $row): string
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

        if ($row->by_day) {
            $rule->setByDay([$row->by_day]);

            if ($row->frequency === 'YEARLY' && $row->by_month) {
                $rule->setByMonth([(int) $row->by_month]);
            }
        }

        return $rule->getString();
    }
}
