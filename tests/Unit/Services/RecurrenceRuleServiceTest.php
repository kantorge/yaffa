<?php

namespace Tests\Unit\Services;

use App\Services\RecurrenceRuleService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurrenceRuleServiceTest extends TestCase
{
    public function test_get_recurrence_returns_occurrences_up_to_end_date(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrence(
            Carbon::parse('2024-01-01'),
            'DAILY',
            1,
            Carbon::parse('2024-01-05'),
        );

        $this->assertSame(5, $recurrence->count());
    }

    public function test_get_recurrence_respects_count(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrence(
            Carbon::parse('2024-01-01'),
            'MONTHLY',
            1,
            null,
            3,
        );

        $this->assertSame(3, $recurrence->count());
    }

    public function test_get_recurrence_respects_interval(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrence(
            Carbon::parse('2024-01-01'),
            'DAILY',
            2,
            Carbon::parse('2024-01-07'),
        );

        // 01-01, 01-03, 01-05, 01-07
        $this->assertSame(4, $recurrence->count());
    }

    public function test_has_occurrence_on_or_after_returns_true_for_an_ongoing_rule(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertTrue($service->hasOccurrenceOnOrAfter(
            Carbon::now()->subDays(10),
            'DAILY',
            1,
            null,
            null,
            Carbon::now(),
        ));
    }

    public function test_has_occurrence_on_or_after_returns_false_for_an_ended_rule(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertFalse($service->hasOccurrenceOnOrAfter(
            Carbon::now()->subDays(10),
            'DAILY',
            1,
            Carbon::now()->subDay(),
            null,
            Carbon::now(),
        ));
    }

    public function test_has_occurrence_on_or_after_returns_false_for_an_invalid_frequency(): void
    {
        $service = new RecurrenceRuleService();

        $this->assertFalse($service->hasOccurrenceOnOrAfter(
            Carbon::now()->subDays(10),
            'INVALID_FREQUENCY',
            1,
            null,
            null,
            Carbon::now(),
        ));
    }

    public function test_has_occurrence_on_or_after_is_true_when_the_only_occurrence_is_exactly_the_given_date(): void
    {
        $service = new RecurrenceRuleService();

        // A single-occurrence rule whose one and only date is today: "on or after today" must
        // include today itself, matching the method's own name.
        $this->assertTrue($service->hasOccurrenceOnOrAfter(
            Carbon::now(),
            'DAILY',
            1,
            null,
            1,
            Carbon::now(),
        ));
    }

    public function test_get_recurrence_excludes_after_date_by_default(): void
    {
        $service = new RecurrenceRuleService();

        $recurrence = $service->getRecurrence(
            Carbon::parse('2024-01-01'),
            'DAILY',
            1,
            Carbon::parse('2024-01-05'),
            null,
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
            'DAILY',
            1,
            Carbon::parse('2024-01-05'),
            null,
            Carbon::parse('2024-01-01'),
            afterDateInclusive: true,
        );

        // 01-01 through 01-05, inclusive.
        $this->assertSame(5, $recurrence->count());
    }
}
