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

    private function buildRule(
        Carbon $startDate,
        string $frequency,
        int $interval,
        ?Carbon $endDate,
        ?int $count,
        ?string $byDay,
        ?int $byMonth,
    ): Rule {
        $rule = (new Rule())
            ->setStartDate(new DateTime($startDate->toDateString()))
            ->setFreq($frequency);

        if ($endDate) {
            $rule->setUntil(new DateTime($endDate->toDateString()));
        }

        if ($count) {
            $rule->setCount($count);
        }

        if ($interval) {
            $rule->setInterval($interval);
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
    private function makeArrayTransformer(): ArrayTransformer
    {
        $transformer = new ArrayTransformer();
        $transformerConfig = new ArrayTransformerConfig();
        $transformerConfig->enableLastDayOfMonthFix();
        $transformerConfig->setVirtualLimit(self::RECURRENCE_VIRTUAL_LIMIT);
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
            $recurrence = $this->getRecurrence(
                $startDate,
                $frequency,
                $interval,
                $endDate,
                $count,
                $byDay,
                $byMonth,
                $onOrAfterDate,
                afterDateInclusive: true,
            );
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
        $transformer = $this->makeArrayTransformer();

        $after = new DateTime($afterDate->toDateString());
        $before = new DateTime(
            $afterDate->copy()->addDays($this->recurrenceLookaheadDays($frequency, $interval))->toDateString()
        );
        $constraint = new BetweenConstraint($after, $before, false);

        return $transformer->transform($rule, $constraint, false);
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
        $rule = $this->buildRule($startDate, $frequency, $interval, $endDate, $count, $byDay, $byMonth);
        $transformer = $this->makeArrayTransformer();

        $day = new DateTime($date->toDateString());
        $constraint = new BetweenConstraint($day, $day, true);

        return $transformer->transform($rule, $constraint)->count() > 0;
    }
}
