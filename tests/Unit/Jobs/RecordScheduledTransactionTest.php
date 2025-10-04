<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RecordScheduledTransaction;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordScheduledTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_scheduled_standard_transaction(): void
    {
        // Create a user and all necessary assets for a transaction
        /** @var User $user */
        $user = User::factory()->create();

        AccountEntity::factory()->for($user)->for(Account::factory()->withUser($user), 'config')->create();
        AccountEntity::factory()->for($user)->for(Payee::factory()->withUser($user), 'config')->create();

        // Create a scheduled transaction
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        // By default, a transaction schedule will be created
        // We need to adjust its properties to better suite our test
        $transaction->transactionSchedule->update([
            'start_date' => now(),
            'next_date' => now(),
            'end_date' => null,
            'automatic_recording' => true,
            'count' => null,
            'interval' => 1,
            'frequency' => 'DAILY',
        ]);

        $start = $transaction->transactionSchedule->next_date;

        // Run the job
        $job = new RecordScheduledTransaction($transaction);
        $job->handle();

        // Check that the transaction was recorded and the date matches the next date
        $transaction->refresh();
        $newTransaction = Transaction::latest('id')->first();

        $this->assertEquals($start, $newTransaction->date);

        // Check that the new transaction is not a schedule
        $this->assertFalse($newTransaction->schedule);

        // Check that the transaction items were cloned, at least on the level of count
        $this->assertEquals($transaction->transactionItems->count(), $newTransaction->transactionItems->count());

        // Check that the next date was adjusted
        $this->assertEquals($start->addDay(), $transaction->transactionSchedule->next_date);
    }

    public function test_record_scheduled_investment_transaction(): void
    {
        // Create a user and all necessary assets for a transaction
        /** @var User $user */
        $user = User::factory()->create();

        InvestmentGroup::factory()->for($user)->create();
        AccountEntity::factory()->for($user)->for(Account::factory()->withUser($user), 'config')->create();
        Investment::factory()->for($user)->withUser($user)->create();

        // Create a scheduled investment transaction
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->buy_schedule($user)
            ->create();

        // By default, a transaction schedule will be created
        // We need to adjust its properties to better suite our test
        $transaction->transactionSchedule->update([
            'start_date' => now(),
            'next_date' => now(),
            'end_date' => null,
            'automatic_recording' => true,
            'count' => null,
            'interval' => 1,
            'frequency' => 'DAILY',
        ]);

        $start = $transaction->transactionSchedule->next_date;

        // Run the job
        $job = new RecordScheduledTransaction($transaction);
        $job->handle();

        // Check that the transaction was recorded and the date matches the next date
        $transaction->refresh();
        $newTransaction = Transaction::latest('id')->first();

        $this->assertEquals($start, $newTransaction->date);

        // Check that the new transaction is not a schedule
        $this->assertFalse($newTransaction->schedule);

        // Check that the transaction items were cloned, at least on the level of count
        $this->assertEquals($transaction->transactionItems->count(), $newTransaction->transactionItems->count());

        // Check that the next date was adjusted
        $this->assertEquals($start->addDay(), $transaction->transactionSchedule->next_date);
    }
}
