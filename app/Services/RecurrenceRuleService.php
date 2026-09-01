<?php

namespace App\Services;

use DateTime;
use Exception;
use Illuminate\Support\Carbon;
use Recurr\Exception\InvalidArgument;
use Recurr\Exception\InvalidWeekday;
use Recurr\RecurrenceCollection;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\AfterConstraint;
use Recurr\Transformer\Constraint\BetweenConstraint;
use DateTimeInterface;

/**
 * Shared calendar-occurrence math for anything defined by a
 * frequency/interval/start_date/end_date/count(/by_day/by_month) recurrence
 * rule (TransactionSchedule, Budget).
 */
class RecurrenceRuleService
{
    /**
     * Recurr's default virtualLimit (732) caps how many candidate occurrences are scanned
     * from start_date before giving up - for a DAILY rule that's only ~2 years. Past that,
     * getOccurrencesAfter()/occursOn()/hasOccurrenceOnOrAfter() silently report "no occurrence
     * found" even when one clearly exists further out. Raised generously (covers ~270 years of
     * DAILY occurrences) since this is still a single bounded scan, not a loop - protects
     * against a genuinely malformed/infinite rule without breaking realistic long-lived rules.
     */
    private const int RECURRENCE_VIRTUAL_LIMIT = 100000;

    /**
     * Resolve occurrences of $rule that start within [$after, $before] (bounds inclusive iff
     * $inclusive), used everywhere below that previously called
     * `$transformer->transform($rule, new BetweenConstraint(...), false)` directly.
     *
     * That `false` (countConstraintFailures) tells Recurr not to spend a rule's `count` budget
     * on candidate occurrences that fail the constraint - the right call for the overwhelming
     * majority of rules (count === null, no budget to protect), since it lets BetweenConstraint
     * stop the transformer as soon as it passes $before instead of scanning to virtualLimit.
     * But for a rule that DOES have a count, it means occurrences before the window don't count
     * against it either: a count-limited rule whose real occurrences are entirely in the past
     * (e.g. a one-time bond-maturity sell, already recorded years ago) gets its count budget
     * treated as untouched, and the scan - which must walk every candidate day/period from
     * start_date up to $before before it can conclude anything, since none of those pre-window
     * candidates increment the transformer's own scan counter either - manufactures a phantom
     * "fresh" occurrence at the window's edge instead of correctly finding none.
     *
     * A count-limited rule only ever produces $count occurrences total, so computing that small,
     * unconstrained set up front and filtering it in PHP is both correct (count is spent
     * normally, in rule order, independent of any window) and cheap - genuinely cheaper than the
     * miscounting scan above, not just safer.
     */
    private function transformWithinWindow(
        Rule $rule,
        DateTimeInterface $after,
        DateTimeInterface $before,
        bool $inclusive,
        ?int $virtualLimit,
    ): RecurrenceCollection {
        $transformer = $this->makeArrayTransformer($virtualLimit);

        if ($rule->getCount() === null) {
            $constraint = new BetweenConstraint($after, $before, $inclusive);

            return $transformer->transform($rule, $constraint, false);
        }

        return $transformer->transform($rule)->startsBetween($after, $before, $inclusive);
    }

    /**
     * Cheap analytic estimate of how many recurrence periods fall between $startDate and
     * $referenceDate (typically "now") - the number of iterations the bounded scans above need
     * to walk through to resolve an occurrence relative to today, without actually running the
     * transformer. A by_day/by_month ordinal-weekday rule still yields ~1 occurrence per base
     * period, so period count (not exact occurrence count) is intentionally used as the proxy.
     * Returns 0 when $referenceDate is on or before $startDate (nothing to scan yet).
     */
    public function estimatePeriodsBetween(
        Carbon $startDate,
        string $frequency,
        int $interval,
        Carbon $referenceDate,
    ): int {
        if ($referenceDate->lessThanOrEqualTo($startDate)) {
            return 0;
        }

        $interval = max($interval, 1);

        $periods = match ($frequency) {
            'DAILY' => $startDate->diffInDays($referenceDate) / $interval,
            'WEEKLY' => $startDate->diffInDays($referenceDate) / (7 * $interval),
            'MONTHLY' => $startDate->diffInMonths($referenceDate) / $interval,
            'YEARLY' => $startDate->diffInYears($referenceDate) / $interval,
            default => 0,
        };

        return (int) floor($periods);
    }

    /**
     * The one place a Recurr\Rule gets constructed from a frequency/interval/start_date/
     * end_date/count/by_day/by_month tuple - public so a caller that needs the raw Rule itself
     * (e.g. Transaction::scheduleInstances(), which runs its own ArrayTransformer/constraint
     * with a caller-supplied virtualLimit rather than one of this service's own occurrence
     * methods) still goes through this instead of hand-building one and silently dropping
     * by_day/by_month, per this service's own class-level contract.
     */
    public function buildRule(
        Carbon $startDate,
        string $frequency,
        ?int $interval,
        ?Carbon $endDate,
        ?int $count,
        ?string $byDay,
        ?int $byMonth,
    ): Rule {
        $interval = max($interval ?? 1, 1);

        $rule = (new Rule())
            ->setStartDate(new DateTime($startDate->toDateString()))
            ->setFreq($frequency)
            ->setInterval($interval);

        if ($endDate) {
            $rule->setUntil(new DateTime($endDate->toDateString()));
        }

        if ($count) {
            $rule->setCount($count);
        }

        if ($byDay) {
            $rule->setByDay([$byDay]);

            if ($frequency === 'YEARLY' && $byMonth) {
                $rule->setByMonth([$byMonth]);
            }
        }

        return $rule;
    }

    /**
     * The ArrayTransformer used to expand a rule into concrete occurrences,
     * configured identically wherever it's needed.
     */
    private function makeArrayTransformer(?int $virtualLimit = null): ArrayTransformer
    {
        $transformer = new ArrayTransformer();
        $transformerConfig = new ArrayTransformerConfig();
        $transformerConfig->enableLastDayOfMonthFix();
        $transformerConfig->setVirtualLimit($virtualLimit ?? self::RECURRENCE_VIRTUAL_LIMIT);
        $transformer->setConfig($transformerConfig);

        return $transformer;
    }

    /**
     * Build the occurrence collection for a recurrence rule, optionally constrained to
     * occurrences after (or, with $afterDateInclusive, on or after) a given date.
     *
     * $afterDateInclusive defaults to false to preserve the exclusive semantics
     * TransactionSchedule::getNextInstance()/isActive() have always relied on (each wants "the
     * next occurrence strictly after this date"). A caller that instead wants a full "from X
     * through Y" projection - e.g. BudgetService::projectOccurrences(), where $afterDate is
     * often a rule's own start_date, which must itself be included - should pass true.
     *
     * Unlike getOccurrencesAfter() below, this uses an unbounded AfterConstraint, so it's only
     * appropriate for callers that themselves cap the result (e.g. filtering to a display
     * window) rather than ones that just need the single next occurrence.
     *
     * @throws InvalidWeekday
     * @throws InvalidArgument
     * @throws Exception
     */
    public function getRecurrence(
        Carbon $startDate,
        string $frequency,
        int $interval = 1,
        ?Carbon $endDate = null,
        ?int $count = null,
        ?string $byDay = null,
        ?int $byMonth = null,
        ?Carbon $afterDate = null,
        bool $afterDateInclusive = false,
    ): RecurrenceCollection {
        $rule = $this->buildRule($startDate, $frequency, $interval, $endDate, $count, $byDay, $byMonth);
        $transformer = $this->makeArrayTransformer();

        $constraint = $afterDate
            ? new AfterConstraint(new DateTime($afterDate->toDateString()), $afterDateInclusive)
            : null;

        return $transformer->transform($rule, $constraint);
    }

    /**
     * Determine whether a recurrence rule yields at least one occurrence on or after the given date.
     * Returns false (rather than throwing) if the rule itself is invalid.
     *
     * Bounded like getOccurrencesAfter() rather than routed through getRecurrence()'s unbounded
     * AfterConstraint - the same "scans all the way to virtualLimit regardless of how soon a
     * match is found" cost applies here, and this is called synchronously on every Budget
     * create/update (Budget::booted()), so it must stay cheap. The search window's upper bound
     * is anchored to the later of $onOrAfterDate/$startDate so a rule that hasn't started yet
     * (a future-dated budget) still has its first candidate occurrence land inside the window.
     */
    public function hasOccurrenceOnOrAfter(
        Carbon $startDate,
        string $frequency,
        int $interval,
        ?Carbon $endDate,
        ?int $count,
        ?string $byDay,
        ?int $byMonth,
        Carbon $onOrAfterDate,
    ): bool {
        try {
            $rule = $this->buildRule($startDate, $frequency, $interval, $endDate, $count, $byDay, $byMonth);

            $after = new DateTime($onOrAfterDate->toDateString());
            $windowStart = $startDate->greaterThan($onOrAfterDate) ? $startDate : $onOrAfterDate;
            $before = new DateTime(
                $windowStart->copy()->addDays($this->recurrenceLookaheadDays($frequency, $interval))->toDateString()
            );

            $recurrence = $this->transformWithinWindow($rule, $after, $before, true, null);
        } catch (InvalidArgument|InvalidWeekday|Exception) {
            return false;
        }

        return $recurrence->count() > 0;
    }

    /**
     * A rule's ByDay/interval semantics guarantee at least one occurrence per period (e.g.
     * "1st Wednesday of every month" occurs exactly once per month), so a window of 2 periods
     * from the query date is a generous, safely-overestimating bound to search within - using
     * calendar-day approximations for month/year lengths since exact unit arithmetic isn't
     * needed for a safety margin.
     */
    private function recurrenceLookaheadDays(string $frequency, ?int $interval): int
    {
        $periodDays = match ($frequency) {
            'DAILY' => 1,
            'WEEKLY' => 7,
            'MONTHLY' => 31,
            'YEARLY' => 366,
            default => 366,
        };

        return $periodDays * max($interval ?? 1, 1) * 2;
    }

    /**
     * Find the occurrence(s) strictly after $afterDate, bounded to a short lookahead window
     * rather than scanning unbounded.
     *
     * Recurr's AfterConstraint never stops the transformer early (it always scans all the way
     * to virtualLimit, regardless of how soon a match is found) - with RECURRENCE_VIRTUAL_LIMIT
     * raised to 100000 to fix the silent-cutoff bug above, an unbounded AfterConstraint scan
     * here would take seconds per call. BetweenConstraint *does* stop early once it passes its
     * upper bound, so bounding the query to a window that's comfortably wider than one
     * recurrence period keeps the scan cheap while still finding occurrences arbitrarily far
     * past start_date.
     *
     * @throws InvalidWeekday
     * @throws InvalidArgument
     * @throws Exception
     */
    public function getOccurrencesAfter(
        Carbon $startDate,
        string $frequency,
        int $interval,
        ?Carbon $endDate,
        ?int $count,
        ?string $byDay,
        ?int $byMonth,
        Carbon $afterDate,
    ): RecurrenceCollection {
        $rule = $this->buildRule($startDate, $frequency, $interval, $endDate, $count, $byDay, $byMonth);

        $after = new DateTime($afterDate->toDateString());
        $before = new DateTime(
            $afterDate->copy()->addDays($this->recurrenceLookaheadDays($frequency, $interval))->toDateString()
        );

        return $this->transformWithinWindow($rule, $after, $before, false, null);
    }

    /**
     * Build the occurrence collection for a recurrence rule, bounded to a closed [$from, $to]
     * window (both ends inclusive). Unlike getRecurrence()'s unbounded AfterConstraint, a
     * BetweenConstraint genuinely stops the transformer once it passes $to instead of scanning to
     * virtualLimit - the right choice for a caller (BudgetService::projectOccurrences()) that
     * already has its own upper bound rather than needing "all future occurrences".
     *
     * @throws InvalidWeekday
     * @throws InvalidArgument
     * @throws Exception
     */
    public function getRecurrenceBetween(
        Carbon $startDate,
        string $frequency,
        int $interval,
        ?Carbon $endDate,
        ?int $count,
        ?string $byDay,
        ?int $byMonth,
        Carbon $from,
        Carbon $to,
        ?int $virtualLimit = null,
    ): RecurrenceCollection {
        $rule = $this->buildRule($startDate, $frequency, $interval, $endDate, $count, $byDay, $byMonth);

        return $this->transformWithinWindow(
            $rule,
            new DateTime($from->toDateString()),
            new DateTime($to->toDateString()),
            true,
            $virtualLimit,
        );
    }

    /**
     * Whether $date is a genuine occurrence of the given recurrence rule.
     *
     * next_date is trusted verbatim wherever a real transaction gets materialized (automatic
     * recording via RecordScheduledTransactions, and the manual "enter" flow both use it as-is
     * for the new transaction's date) - unlike forecast/calendar views, which always re-derive
     * occurrences from the rule. This lets callers (validation) catch a next_date that was
     * never actually produced by the rule - e.g. left over from before a frequency/by_day
     * change - before it gets persisted and eventually recorded on the wrong day.
     *
     * @throws InvalidWeekday
     * @throws InvalidArgument
     * @throws Exception
     */
    public function occursOn(
        Carbon $startDate,
        string $frequency,
        int $interval,
        ?Carbon $endDate,
        ?int $count,
        ?string $byDay,
        ?int $byMonth,
        Carbon $date,
    ): bool {
        // Bound the rule's own UNTIL to $date (unless the caller's $endDate is earlier)
        // rather than relying solely on the BetweenConstraint below to short-circuit:
        // Recurr doesn't reliably stop early for a YEARLY+BYMONTH+ordinal-BYDAY rule (e.g.
        // "-1FR" of November) with no UNTIL/COUNT - measured ~33s at RECURRENCE_VIRTUAL_LIMIT
        // because it keeps generating candidates past $date until the limit is hit, even
        // though BetweenConstraint::stopsTransformer() should have ended it on the very next
        // candidate. Capping UNTIL makes the rule itself stop generating right after $date,
        // independent of that stopsTransformer quirk, while still resolving correctly for
        // dates arbitrarily far from $startDate.
        $searchEndDate = $endDate && $endDate->lessThan($date) ? $endDate : $date;

        $rule = $this->buildRule($startDate, $frequency, $interval, $searchEndDate, $count, $byDay, $byMonth);
        $transformer = $this->makeArrayTransformer();

        $day = new DateTime($date->toDateString());
        $constraint = new BetweenConstraint($day, $day, true);

        return $transformer->transform($rule, $constraint)->count() > 0;
    }
}
