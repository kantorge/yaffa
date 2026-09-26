<?php

namespace Tests\Unit\Models;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FR-8 wiring coverage: Transaction::scheduleInstances() attaches an inflation-compounded
 * multiplier to each virtual occurrence, computed via InflationCalculator - separate from FR-5's
 * occurrence-generation logic, which decides only which periods exist.
 */
class TransactionScheduleInstancesInflationTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiplier_steps_up_at_the_calendar_year_boundary_not_the_start_date_anniversary(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addYears(3),
        ]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->transactionSchedule->update([
            'start_date' => Carbon::parse('2024-12-15'),
            'next_date' => Carbon::parse('2024-12-15'),
            'end_date' => Carbon::parse('2025-02-15'),
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
            'inflation' => 10.0,
        ]);

        $transaction = Transaction::with(['config', 'transactionSchedule'])->findOrFail($transaction->id);

        $instances = $transaction->scheduleInstances(
            constraintStart: Carbon::parse('2024-12-15'),
            maxLookAhead: Carbon::parse('2025-03-01'),
        );

        $byDate = $instances->keyBy(fn ($instance) => $instance->date->toDateString());

        // Still the start year (2024-12-15): no compounding yet.
        $this->assertEqualsWithDelta(1.0, $byDate['2024-12-15']->inflationMultiplier, 0.0001);
        // One calendar-year boundary crossed (2025-01-01), despite almost none of the start
        // year having elapsed at start_date.
        $this->assertEqualsWithDelta(1.1, $byDate['2025-01-15']->inflationMultiplier, 0.0001);
        // Still within the same year as the previous occurrence: no further compounding.
        $this->assertEqualsWithDelta(1.1, $byDate['2025-02-15']->inflationMultiplier, 0.0001);
    }

    public function test_multiplier_is_one_when_inflation_is_not_set(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addYears(3),
        ]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->transactionSchedule->update([
            'start_date' => Carbon::parse('2024-01-01'),
            'next_date' => Carbon::parse('2024-01-01'),
            'end_date' => Carbon::parse('2026-01-01'),
            'count' => null,
            'interval' => 1,
            'frequency' => 'YEARLY',
            'inflation' => null,
        ]);

        $transaction = Transaction::with(['config', 'transactionSchedule'])->findOrFail($transaction->id);

        $instances = $transaction->scheduleInstances(
            constraintStart: Carbon::parse('2024-01-01'),
            maxLookAhead: Carbon::parse('2026-06-01'),
        );

        $this->assertGreaterThan(0, $instances->count());

        foreach ($instances as $instance) {
            $this->assertSame('1', $instance->inflationMultiplier);
        }
    }
}
