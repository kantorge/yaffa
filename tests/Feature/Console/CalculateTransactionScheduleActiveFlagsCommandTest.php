<?php

namespace Tests\Feature\Console;

use App\Console\Commands\CalculateTransactionScheduleActiveFlags;
use App\Jobs\CalculateBudgetActiveFlag;
use App\Jobs\CalculateTransactionScheduleActiveFlag;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * FR-1 (isSchedule() replaces byScheduleType('any')) and FR-4 (the same recalculation command
 * now also (re)computes Budget.active) coverage for
 * app/Console/Commands/CalculateTransactionScheduleActiveFlags.php.
 */
class CalculateTransactionScheduleActiveFlagsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_a_job_for_every_schedule_and_every_budget(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Transaction::factory()
            ->withdrawal_schedule($user)
            ->create(['user_id' => $user->id]);

        // A non-schedule transaction must not get a job dispatched for it.
        Transaction::factory()
            ->deposit($user)
            ->create(['user_id' => $user->id, 'schedule' => false]);

        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->artisan(CalculateTransactionScheduleActiveFlags::class)->assertSuccessful();

        Queue::assertPushed(CalculateTransactionScheduleActiveFlag::class, 1);
        Queue::assertPushed(CalculateBudgetActiveFlag::class, 1);
    }

    /**
     * End-to-end (sync queue driver, jobs actually run): a stale `active = true` flag - written
     * directly, bypassing the models' own booted() hooks, simulating time having passed since it
     * was last computed - is corrected back to false for both a TransactionSchedule and a Budget.
     */
    public function test_recomputes_a_stale_active_flag_for_both_schedules_and_budgets(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->withdrawal_schedule($user)
            ->create(['user_id' => $user->id]);

        $transaction->transactionSchedule->update([
            'start_date' => now()->subYears(2),
            'next_date' => null,
            'end_date' => now()->subYear(),
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);
        $this->assertFalse($transaction->transactionSchedule->fresh()->active);

        DB::table('transaction_schedules')
            ->where('id', $transaction->transactionSchedule->id)
            ->update(['active' => true]);

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'start_date' => now()->subYears(2),
            'end_date' => now()->subYear(),
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'count' => null,
        ]);
        $this->assertFalse($budget->fresh()->active);

        DB::table('budgets')->where('id', $budget->id)->update(['active' => true]);

        $this->artisan(CalculateTransactionScheduleActiveFlags::class)->assertSuccessful();

        $this->assertFalse($transaction->transactionSchedule->fresh()->active);
        $this->assertFalse($budget->fresh()->active);
    }
}
