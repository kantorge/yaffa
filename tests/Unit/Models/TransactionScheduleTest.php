<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use App\Models\User;
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
}