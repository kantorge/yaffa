<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RecordScheduledTransaction;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordScheduledTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_scheduled_transaction_job()
    {
        // Create a user and all necessary assets for a transaction
        /** @var User $user */
        $user = User::factory()->create();

        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        // Create a scheduled transaction
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        // By default, a transaction schedule will be created
        // We need to adjust its properties to better suite our test
        $transaction->transactionSchedule->update([
            'end_date' => null,
            'automatic_recording' => true,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        $start = $transaction->transactionSchedule->next_date;

        // Run the job
        $job = new RecordScheduledTransaction($transaction);
        $job->handle();

        // Check that the transaction was recorded and the date matches the next date
        $transaction->refresh();
        $newTransaction = Transaction::latest('id')->first();

        $this->assertEquals($start, $newTransaction->date);

        // Check that the next date was adjusted
        $this->assertEquals($start->addMonth(), $transaction->transactionSchedule->next_date);
    }
}
