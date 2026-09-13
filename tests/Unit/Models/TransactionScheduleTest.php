<?php

namespace Tests\Unit\Models;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_transaction_schedule_has_the_active_flag_correctly_set(): void
    {
        // Create a user which will own the assets
        /** @var User $user */
        $user = User::factory()->create();

        // Create: account group, currency, account, payee
        AccountGroup::factory()
            ->for($user)
            ->create();

        Currency::factory()
            ->for($user)
            ->fromIsoCodes(['USD'])
            ->create(['base' => true]);

        AccountEntity::factory()
            ->for($user)
            ->for(
                Account::factory()
                    ->withUser($user)
                    ->create(),
                'config'
            )
            ->create();

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'account_from_id' => $user->accounts()->first()->id,
                    'account_to_id' => $user->payees()->first()->id,
                    'amount_from' => 100,
                    'amount_to' => 100,
                ]),
                'config'
            )
            ->create([
                'date' => null,
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            ]);

        // Intentionally set the schedule flag later, to avoid the creating closure
        $transaction->schedule = true;
        $transaction->save();

        // Create an active transaction schedule for this transaction
        $transaction->transactionSchedule()->create([
            'start_date' => now()->subMonth(),
            'next_date' => now()->subMonth(),
            'end_date' => null,
            'frequency' => 'DAILY',
            'count' => null,
            'interval' => 1,
            'inflation' => null,
            'automatic_recording' => true,
        ]);

        // Assert that the active flag is set to true
        $this->assertTrue($transaction->transactionSchedule->active);

        // Update the transaction schedule to be inactive
        $transaction->transactionSchedule->update([
            'next_date' => null,
            'end_date' => now()->subDay(),
        ]);
        $transaction->transactionSchedule->refresh();

        // Assert that the active flag is set to false
        $this->assertFalse($transaction->transactionSchedule->active);
    }

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
