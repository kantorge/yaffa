<?php

namespace Tests\Unit\Services;

use App\Services\RecurrenceRuleService;
use DateTime;
use Illuminate\Support\Carbon;
use Recurr\Rule;
use Tests\TestCase;

class RecurrenceRuleServiceTest extends TestCase
{
    /**
     * Builds an RRULE string the same way App\Models\Concerns\HasRecurrenceRule composes one from
     * discrete fields, so every test below can keep expressing its input the same way the old,
     * pre-rrule-storage version of this test suite did (frequency/interval/end_date/count/by_day/
     * by_month), rather than hand-writing RFC 5545 strings.
     */
    private function makeRrule(
        string $frequency,
        int $interval = 1,
        ?Carbon $endDate = null,
        ?int $count = null,
        ?string $byDay = null,
        ?int $byMonth = null,
        ?int $daysBeforeMonthEnd = null,
        bool $lastBusinessDayOfMonth = false,
    ): string {
        $rule = (new Rule())->setFreq($frequency)->setInterval($interval);

        if ($endDate) {
            $rule->setUntil(new DateTime($endDate->toDateString()));
        }

        if ($count) {
            $rule->setCount($count);
        }

        if ($daysBeforeMonthEnd !== null) {
            $rule->setByMonthDay([-($daysBeforeMonthEnd + 1)]);

            if ($frequency === 'YEARLY' && $byMonth) {
                $rule->setByMonth([$byMonth]);
            }
        } elseif ($lastBusinessDayOfMonth) {
            $rule->setByDay(['MO', 'TU', 'WE', 'TH', 'FR']);
            $rule->setBySetPosition([-1]);

            if ($frequency === 'YEARLY' && $byMonth) {
                $rule->setByMonth([$byMonth]);
            }
        } elseif ($byDay) {
            $rule->setByDay([$byDay]);

            if ($frequency === 'YEARLY' && $byMonth) {
                $rule->setByMonth([$byMonth]);
            }
        }

        return $rule->getString();
    }

    public function test_get_recurrence_returns_occurrences_up_to_end_date(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrence(
            Carbon::parse('2024-01-01'),
            $this->makeRrule('DAILY', 1, Carbon::parse('2024-01-05')),
        );

        $this->assertSame(5, $recurrence->count());
    }

    public function test_get_recurrence_respects_count(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrence(
            Carbon::parse('2024-01-01'),
            $this->makeRrule('MONTHLY', 1, null, 3),
        );

        $this->assertSame(3, $recurrence->count());
    }

    public function test_get_recurrence_respects_interval(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrence(
            Carbon::parse('2024-01-01'),
            $this->makeRrule('DAILY', 2, Carbon::parse('2024-01-07')),
        );

        // 01-01, 01-03, 01-05, 01-07
        $this->assertSame(4, $recurrence->count());
    }

    public function test_has_occurrence_on_or_after_returns_true_for_an_ongoing_rule(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertTrue($service->hasOccurrenceOnOrAfter(
            Carbon::now()->subDays(10),
            $this->makeRrule('DAILY'),
            Carbon::now(),
        ));
    }

    public function test_has_occurrence_on_or_after_returns_false_for_an_ended_rule(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertFalse($service->hasOccurrenceOnOrAfter(
            Carbon::now()->subDays(10),
            $this->makeRrule('DAILY', 1, Carbon::now()->subDay()),
            Carbon::now(),
        ));
    }

    public function test_has_occurrence_on_or_after_returns_false_for_an_invalid_frequency(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertFalse($service->hasOccurrenceOnOrAfter(
            Carbon::now()->subDays(10),
            'FREQ=INVALID_FREQUENCY',
            Carbon::now(),
        ));
    }

    public function test_has_occurrence_on_or_after_is_true_when_the_only_occurrence_is_exactly_the_given_date(): void
    {
        $service = new RecurrenceRuleService();

        // A single-occurrence rule whose one and only date is today: "on or after today" must
        // include today itself, matching the method's own name. Both parameters reuse the same
        // captured instant rather than two separate Carbon::now() calls, which could otherwise
        // straddle a midnight boundary and land on two different calendar dates.
        $now = Carbon::now();
        $this->assertTrue($service->hasOccurrenceOnOrAfter(
            $now,
            $this->makeRrule('DAILY', 1, null, 1),
            $now,
        ));
    }

    public function test_get_recurrence_excludes_after_date_by_default(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrence(
            Carbon::parse('2024-01-01'),
            $this->makeRrule('DAILY', 1, Carbon::parse('2024-01-05')),
            Carbon::parse('2024-01-01'),
        );

        // 01-01 itself is excluded: 01-02, 01-03, 01-04, 01-05.
        $this->assertSame(4, $recurrence->count());
    }

    public function test_get_recurrence_includes_after_date_when_inclusive(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrence(
            Carbon::parse('2024-01-01'),
            $this->makeRrule('DAILY', 1, Carbon::parse('2024-01-05')),
            Carbon::parse('2024-01-01'),
            afterDateInclusive: true,
        );

        // 01-01 through 01-05, inclusive.
        $this->assertSame(5, $recurrence->count());
    }

    public function test_get_occurrences_after_respects_by_day_and_by_month(): void
    {
        $service = new RecurrenceRuleService();

        // "Last Friday of November, yearly" - starting from a non-matching start_date. The
        // lookahead window is 2 yearly periods wide, so both the 2024 and 2025 occurrences
        // fall inside it; only the nearest one matters here.
        $recurrence = $service->getOccurrencesAfter(
            Carbon::parse('2024-01-01'),
            $this->makeRrule('YEARLY', 1, null, null, '-1FR', 11),
            Carbon::parse('2024-01-01'),
        );

        $this->assertGreaterThanOrEqual(1, $recurrence->count());
        $this->assertSame('2024-11-29', $recurrence[0]->getStart()->format('Y-m-d'));
    }

    public function test_occurs_on_matches_a_by_day_pattern(): void
    {
        $service = new RecurrenceRuleService();
        $rrule = $this->makeRrule('YEARLY', 1, null, null, '-1FR', 11);

        $this->assertTrue($service->occursOn(
            Carbon::parse('2024-01-01'),
            $rrule,
            Carbon::parse('2024-11-29'),
        ));

        $this->assertFalse($service->occursOn(
            Carbon::parse('2024-01-01'),
            $rrule,
            Carbon::parse('2024-11-28'),
        ));
    }

    public function test_occurs_on_matches_a_days_before_month_end_pattern(): void
    {
        $service = new RecurrenceRuleService();
        // 5 days before the end of each month - February 2024 (leap year, 29 days): 2024-02-24.
        $rrule = $this->makeRrule('MONTHLY', 1, null, null, null, null, 5);

        $this->assertTrue($service->occursOn(
            Carbon::parse('2024-01-01'),
            $rrule,
            Carbon::parse('2024-02-24'),
        ));

        // February 2023 (non-leap, 28 days): 2023-02-23.
        $this->assertTrue($service->occursOn(
            Carbon::parse('2023-01-01'),
            $rrule,
            Carbon::parse('2023-02-23'),
        ));

        $this->assertFalse($service->occursOn(
            Carbon::parse('2024-01-01'),
            $rrule,
            Carbon::parse('2024-02-25'),
        ));
    }

    public function test_occurs_on_matches_a_last_business_day_of_month_pattern(): void
    {
        $service = new RecurrenceRuleService();
        $rrule = $this->makeRrule('MONTHLY', 1, null, null, null, null, null, true);

        // March 2024 ends on a Sunday - the last business day is Friday 2024-03-29.
        $this->assertTrue($service->occursOn(
            Carbon::parse('2024-01-01'),
            $rrule,
            Carbon::parse('2024-03-29'),
        ));

        $this->assertFalse($service->occursOn(
            Carbon::parse('2024-01-01'),
            $rrule,
            Carbon::parse('2024-03-31'),
        ));

        // June 2024 ends on a Sunday too - last business day is Friday 2024-06-28.
        $this->assertTrue($service->occursOn(
            Carbon::parse('2024-01-01'),
            $rrule,
            Carbon::parse('2024-06-28'),
        ));
    }

    public function test_get_occurrences_after_finds_nothing_for_an_exhausted_count_limited_rule(): void
    {
        $service = new RecurrenceRuleService();

        // A one-time (count=1) rule whose single occurrence was years ago must not manufacture
        // a phantom "fresh" occurrence just because the query window starts long after it -
        // regression for a bug where a matured bond's one-time sell schedule kept re-firing in
        // every forecast run.
        $recurrence = $service->getOccurrencesAfter(
            Carbon::now()->subYears(3),
            $this->makeRrule('DAILY', 1, null, 1),
            Carbon::now(),
        );

        $this->assertSame(0, $recurrence->count());
    }

    public function test_get_recurrence_between_finds_nothing_for_an_exhausted_count_limited_rule(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrenceBetween(
            Carbon::now()->subYears(3),
            $this->makeRrule('DAILY', 1, null, 1),
            Carbon::now(),
            Carbon::now()->addYears(30),
        );

        $this->assertSame(0, $recurrence->count());
    }

    public function test_has_occurrence_on_or_after_is_false_for_an_exhausted_count_limited_rule(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertFalse($service->hasOccurrenceOnOrAfter(
            Carbon::now()->subYears(3),
            $this->makeRrule('DAILY', 1, null, 1),
            Carbon::now(),
        ));
    }

    public function test_get_recurrence_between_still_finds_a_later_occurrence_of_a_count_limited_rule(): void
    {
        $service = new RecurrenceRuleService();

        // Sanity check for the above: a count-limited rule with occurrences still ahead of the
        // window must keep resolving them, not be treated as exhausted just because it has a
        // count at all.
        $recurrence = $service->getRecurrenceBetween(
            Carbon::now()->subMonths(2),
            $this->makeRrule('MONTHLY', 1, null, 5),
            Carbon::now(),
            Carbon::now()->addYears(1),
        );

        $this->assertGreaterThan(0, $recurrence->count());
    }

    public function test_estimate_periods_between_returns_zero_when_reference_date_is_not_after_start(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertSame(0, $service->estimatePeriodsBetween(
            Carbon::parse('2024-06-01'),
            'FREQ=DAILY;INTERVAL=1',
            Carbon::parse('2024-06-01'),
        ));

        $this->assertSame(0, $service->estimatePeriodsBetween(
            Carbon::parse('2024-06-01'),
            'FREQ=DAILY;INTERVAL=1',
            Carbon::parse('2024-01-01'),
        ));
    }

    public function test_estimate_periods_between_counts_daily_periods_respecting_interval(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertSame(10, $service->estimatePeriodsBetween(
            Carbon::parse('2024-01-01'),
            'FREQ=DAILY;INTERVAL=1',
            Carbon::parse('2024-01-11'),
        ));

        $this->assertSame(5, $service->estimatePeriodsBetween(
            Carbon::parse('2024-01-01'),
            'FREQ=DAILY;INTERVAL=2',
            Carbon::parse('2024-01-11'),
        ));
    }

    public function test_estimate_periods_between_counts_monthly_and_yearly_periods(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertSame(6, $service->estimatePeriodsBetween(
            Carbon::parse('2024-01-01'),
            'FREQ=MONTHLY;INTERVAL=1',
            Carbon::parse('2024-07-01'),
        ));

        $this->assertSame(3, $service->estimatePeriodsBetween(
            Carbon::parse('2020-01-01'),
            'FREQ=YEARLY;INTERVAL=1',
            Carbon::parse('2023-01-01'),
        ));
    }

    public function test_estimate_periods_between_flags_a_pathological_ancient_daily_start_date(): void
    {
        $service = new RecurrenceRuleService();

        $periods = $service->estimatePeriodsBetween(
            Carbon::now()->subYears(1000),
            'FREQ=DAILY;INTERVAL=1',
            Carbon::now(),
        );

        $this->assertGreaterThan(2000, $periods);
    }

    public function test_estimate_periods_between_defaults_interval_when_absent_from_rrule(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertSame(10, $service->estimatePeriodsBetween(
            Carbon::parse('2024-01-01'),
            'FREQ=DAILY',
            Carbon::parse('2024-01-11'),
        ));
    }
}
