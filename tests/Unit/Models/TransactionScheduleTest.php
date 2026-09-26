<?php

namespace Tests\Unit\Models;

use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionScheduleTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * A COUNT-bounded rule is filtered via RecurrenceCollection::startsBetween(), which
     * preserves keys - so the first remaining occurrence is not at index 0.
     */
    public function test_get_next_instance_with_count_returns_the_first_occurrence_after_next_date(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => 3,
        ]);

        $this->assertSame('2026-01-02', $schedule->getNextInstance()->format('Y-m-d'));
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

    /**
     * Guards against skipNextInstance() being called without a row lock - without it,
     * this test still passes today (it's a single-threaded, sequential call), but the
     * lock is what stops a genuinely concurrent caller (e.g. a double-submitted manual
     * "skip"/"enter" click) from computing the next occurrence off the same stale
     * next_date another caller already advanced past. See
     * test_skip_next_instance_does_not_lose_an_update_to_a_concurrent_caller below for
     * the behavioral guarantee this enables.
     */
    public function test_skip_next_instance_locks_the_schedule_row_for_update(): void
    {
        $user = User::factory()->create();

        $transaction = Transaction::factory()
            ->withdrawal_schedule($user)
            ->create(['user_id' => $user->id]);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $transaction->transactionSchedule->skipNextInstance();

        $lockedRead = collect($queries)->contains(
            fn (string $sql) => str_contains($sql, 'transaction_schedules') && str_contains(mb_strtolower($sql), 'for update')
        );

        $this->assertTrue($lockedRead, 'Expected a `select ... for update` query against transaction_schedules.');
    }

    /**
     * Simulates two overlapping callers of skipNextInstance() (e.g. a double-submitted
     * manual "skip"/"enter" click) that both loaded the schedule before either advanced
     * it. Without the row lock, both compute the next occurrence from the same stale
     * next_date and the second save() overwrites the first with an identical value -
     * one of the two advances is silently lost.
     */
    public function test_skip_next_instance_does_not_lose_an_update_to_a_concurrent_caller(): void
    {
        $user = User::factory()->create();

        $transaction = Transaction::factory()
            ->withdrawal_schedule($user)
            ->hasTransactionSchedule([
                'start_date' => now()->subMonths(2),
                'next_date' => now(),
                'end_date' => null,
                'frequency' => 'MONTHLY',
                'interval' => 1,
                'count' => null,
            ])
            ->create(['user_id' => $user->id]);

        $scheduleId = $transaction->transactionSchedule->id;
        $originalNextDate = $transaction->transactionSchedule->next_date;

        $copyA = TransactionSchedule::find($scheduleId);
        $copyB = TransactionSchedule::find($scheduleId);

        $this->assertTrue($copyA->skipNextInstance());
        $this->assertTrue($copyB->skipNextInstance());

        $finalNextDate = TransactionSchedule::find($scheduleId)->next_date;

        // Both calls should have taken effect - two months on from the original, not
        // one (which is what a lost update would produce).
        $this->assertTrue($finalNextDate->equalTo($originalNextDate->copy()->addMonthsNoOverflow(2)));
    }
}
