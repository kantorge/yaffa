<?php

namespace Tests\Unit\Models;

use App\Models\TransactionSchedule;
use Carbon\Carbon;
use Tests\TestCase;

class TransactionScheduleTest extends TestCase
{
    public function testIsActiveReturnsTrueWhenNextDateIsSet(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => Carbon::now()->addDay(),
        ]);

        $this->assertTrue($schedule->isActive());
    }

    public function testIsActiveReturnsFalseWhenNextDateIsNotSetAndNoFutureRecurrences(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::now()->subDays(10),
            'next_date' => null,
            'end_date' => Carbon::now()->subDay(),
            'frequency' => 'DAILY',
            'count' => null,
            'interval' => 1,
        ]);

        $this->assertFalse($schedule->isActive());
    }

    public function testIsActiveReturnsTrueWhenNextDateIsNotSetButHasFutureRecurrences(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => null,
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDays(10),
            'count' => null,
            'interval' => 1,
            'frequency' => 'DAILY',
        ]);

        $this->assertTrue($schedule->isActive());
    }

    public function testIsActiveReturnsTrueForOldDailyScheduleBeyondRecurrVirtualLimit(): void
    {
        // start_date is ~3 years back, so the gap to today exceeds Recurr's default
        // virtualLimit of 732 daily occurrences - regression test for that ceiling.
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => null,
            'start_date' => Carbon::now()->subYears(3),
            'end_date' => null,
            'frequency' => 'DAILY',
            'count' => null,
            'interval' => 1,
        ]);

        $this->assertTrue($schedule->isActive());
    }

    public function testGetNextInstanceComputesDailyOccurrenceForOldScheduleBeyondRecurrVirtualLimit(): void
    {
        // next_date is several months in the past, and start_date ~3 years back, so the
        // gap from start_date to today exceeds Recurr's default virtualLimit of 732.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::now()->subYears(3),
            'next_date' => Carbon::now()->subMonths(6),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
        ]);

        $next = $schedule->getNextInstance();

        $this->assertNotNull($next);
        $this->assertSame(
            Carbon::now()->subMonths(6)->addDay()->format('Y-m-d'),
            $next->format('Y-m-d')
        );
    }

    public function testOccursOnReturnsFalseWhenDateDoesNotMatchOrdinalWeekdayRule(): void
    {
        // 2026-01-01 is a Thursday, not the first Wednesday of January 2026.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'count' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'by_day' => '1WE',
        ]);

        $this->assertFalse($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-01')));
    }

    public function testOccursOnReturnsTrueWhenDateMatchesOrdinalWeekdayRule(): void
    {
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'count' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'by_day' => '1WE',
        ]);

        $this->assertTrue($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-07')));
    }

    public function testOccursOnReturnsFalseForANonMatchingWeekInsideTheRule(): void
    {
        // The second Wednesday of January 2026 is not "the first Wednesday".
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'count' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'by_day' => '1WE',
        ]);

        $this->assertFalse($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-14')));
    }

    public function testOccursOnHandlesIntervalForPlainFrequencyRules(): void
    {
        // Every 2 weeks from 2026-01-01: 01-01 and 01-15 occur, 01-08 does not.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'count' => null,
            'frequency' => 'WEEKLY',
            'interval' => 2,
        ]);

        $this->assertTrue($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-01')));
        $this->assertFalse($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-08')));
        $this->assertTrue($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-15')));
    }

    public function testIsActiveReturnsFalseWhenRecurrenceThrowsException(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => null,
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDay(),
            'frequency' => 'INVALID_FREQUENCY',
        ]);

        $this->assertFalse($schedule->isActive());
    }

    public function testGetNextInstanceComputesFirstWeekdayOfMonth(): void
    {
        // 2026-01-07 is the first Wednesday of January 2026.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '1WE',
        ]);

        $next = $schedule->getNextInstance();

        $this->assertNotNull($next);
        $this->assertSame('2026-01-07', $next->format('Y-m-d'));
    }

    public function testGetNextInstanceComputesLastWeekdayOfMonth(): void
    {
        // 2026-01-30 is the last Friday of January 2026.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '-1FR',
        ]);

        $next = $schedule->getNextInstance();

        $this->assertNotNull($next);
        $this->assertSame('2026-01-30', $next->format('Y-m-d'));
    }

    public function testGetNextInstanceComputesYearlyWeekdayScopedToMonth(): void
    {
        // 2026-11-27 is the last Friday of November 2026.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'YEARLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '-1FR',
            'by_month' => 11,
        ]);

        $next = $schedule->getNextInstance();

        $this->assertNotNull($next);
        $this->assertSame('2026-11-27', $next->format('Y-m-d'));
    }

    public function testGetNextInstanceResolvesYearlyWeekdayAcrossWholeYearWhenMonthOmitted(): void
    {
        // Documents recurr's actual semantics: a YEARLY ordinal BYDAY without BYMONTH
        // resolves against the whole year (last Friday of 2026 = 2026-12-25), not per
        // month. TransactionRequest validation rejects this combination at the API
        // boundary, but the model itself doesn't enforce it, so this pins the behavior
        // down as a safety net against a future regression silently changing it.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'YEARLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '-1FR',
            'by_month' => null,
        ]);

        $next = $schedule->getNextInstance();

        $this->assertNotNull($next);
        $this->assertSame('2026-12-25', $next->format('Y-m-d'));
    }

    public function testIsActiveReturnsFalseWhenByDayFrequencyCombinationIsInvalid(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => null,
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDays(10),
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '1WE',
        ]);

        $this->assertFalse($schedule->isActive());
    }

    public function testCatchUpToDateReturnsFalseWhenRecurrenceThrowsException(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => Carbon::now()->subDays(10),
            'start_date' => Carbon::now()->subDays(30),
            'end_date' => Carbon::now()->addDay(),
            'frequency' => 'INVALID_FREQUENCY',
        ]);

        $this->assertFalse($schedule->catchUpToDate());
        $this->assertNotNull($schedule->next_date);
    }
}
