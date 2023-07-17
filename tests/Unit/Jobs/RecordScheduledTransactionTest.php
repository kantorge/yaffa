<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RecordScheduledTransaction;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\User;
use Carbon\Carbon;
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

        AccountGroup::factory()->for($user)->create();
        Currency::factory()->for($user)->create();
        AccountEntity::factory()->account($user)->for($user)->create();
        Category::factory()->for($user)->create();
        AccountEntity::factory()->payee($user)->for($user)->create();

        // Create a scheduled transaction
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->withdrawal_schedule()
            ->for($user)
            ->create();

        $start = Carbon::parse('first day of next month')->startOfDay();

        TransactionSchedule::factory()
            ->create([
                'start_date' => $start,
                'next_date' => $start,
                'end_date' => null,
                'transaction_id' => $transaction->id,
                'automatic_recording' => true,
                'count' => null,
                'interval' => 1,
                'frequency' => 'MONTHLY',
            ]);

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
