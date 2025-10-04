<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionSchedule;
use App\Models\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
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
}
