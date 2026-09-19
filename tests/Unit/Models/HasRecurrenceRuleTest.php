<?php

namespace Tests\Unit\Models;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FR-12 coverage for App\Models\Concerns\HasRecurrenceRule, shared by TransactionSchedule and
 * Budget: the discrete recurrence fields (frequency/interval/count/end_date/by_day/by_month, plus
 * the two month-end patterns) round-trip through the single `rrule` column without loss, and
 * `rrule` itself is never client-fillable.
 */
class HasRecurrenceRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_schedule_recurrence_fields_round_trip_through_save_and_reload(): void
    {
        $user = User::factory()->create();
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->transactionSchedule->update([
            'start_date' => Carbon::parse('2026-01-01'),
            'frequency' => 'YEARLY',
            'interval' => 2,
            'by_day' => '-1FR',
            'by_month' => 11,
            'count' => 5,
            'end_date' => null,
        ]);

        $reloaded = $transaction->transactionSchedule->fresh();

        $this->assertSame('YEARLY', $reloaded->frequency);
        $this->assertSame(2, $reloaded->interval);
        $this->assertSame('-1FR', $reloaded->by_day);
        $this->assertSame(11, $reloaded->by_month);
        $this->assertSame(5, $reloaded->count);
        $this->assertNull($reloaded->end_date);
        $this->assertNull($reloaded->days_before_month_end);
        $this->assertFalse($reloaded->last_business_day_of_month);
    }

    public function test_transaction_schedule_month_end_patterns_round_trip_through_save_and_reload(): void
    {
        $user = User::factory()->create();
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->transactionSchedule->update([
            'start_date' => Carbon::parse('2026-01-01'),
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'days_before_month_end' => 3,
            'by_day' => null,
            'by_month' => null,
            'count' => null,
            'end_date' => null,
        ]);

        $reloaded = $transaction->transactionSchedule->fresh();

        $this->assertSame(3, $reloaded->days_before_month_end);
        $this->assertNull($reloaded->by_day);
        $this->assertFalse($reloaded->last_business_day_of_month);

        $transaction->transactionSchedule->update([
            'days_before_month_end' => null,
            'last_business_day_of_month' => true,
        ]);

        $reloaded = $transaction->transactionSchedule->fresh();

        $this->assertTrue($reloaded->last_business_day_of_month);
        $this->assertNull($reloaded->days_before_month_end);
        $this->assertNull($reloaded->by_day);
    }

    public function test_transaction_schedule_rrule_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $before = $transaction->transactionSchedule->fresh()->getAttributes()['rrule'];

        $transaction->transactionSchedule->fill(['rrule' => 'FREQ=SECONDLY']);
        $transaction->transactionSchedule->save();

        $after = $transaction->transactionSchedule->fresh()->getAttributes()['rrule'];

        $this->assertSame($before, $after);
    }

    public function test_budget_recurrence_fields_round_trip_through_save_and_reload(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'start_date' => Carbon::parse('2026-01-01'),
            'frequency' => 'MONTHLY',
            'interval' => 3,
            'by_day' => '1WE',
            'count' => null,
            'end_date' => Carbon::parse('2027-01-01'),
        ]);

        $reloaded = $budget->fresh();

        $this->assertSame('MONTHLY', $reloaded->frequency);
        $this->assertSame(3, $reloaded->interval);
        $this->assertSame('1WE', $reloaded->by_day);
        $this->assertSame('2027-01-01', $reloaded->end_date->toDateString());
        $this->assertNull($reloaded->count);
    }

    public function test_budget_rrule_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $before = $budget->fresh()->getAttributes()['rrule'];

        $budget->fill(['rrule' => 'FREQ=SECONDLY']);
        $budget->save();

        $after = $budget->fresh()->getAttributes()['rrule'];

        $this->assertSame($before, $after);
    }
}
