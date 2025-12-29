<?php

namespace Tests\Unit\Console\Commands;

use App\Jobs\RecordScheduledTransaction;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecordScheduledTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND_SIGNATURE = 'app:record-scheduled-transactions';

    private function createTestTransaction(array $schedule): Transaction
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
        $transaction->transactionSchedule->update(
            array_merge(
                [
                    'end_date' => null,
                    'automatic_recording' => true,
                    'count' => null,
                    'interval' => 1,
                    'frequency' => 'MONTHLY',
                ],
                $schedule
            )
        );

        return $transaction;
    }

    public function test_transaction_with_next_date_today_is_recorded(): void
    {
        $date = Carbon::today()->startOfDay();

        $transaction = $this->createTestTransaction([
            'start_date' => $date,
            'next_date' => $date,
        ]);

        Queue::fake();

        // Run the command
        $this->artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(0);

        // Assert that the job was pushed
        Queue::assertPushed(RecordScheduledTransaction::class, fn ($job) => $job->transaction->id === $transaction->id);
    }

    public function test_transaction_with_next_date_in_the_past_is_recorded(): void
    {
        $date = Carbon::yesterday()->startOfDay();

        $transaction = $this->createTestTransaction([
            'start_date' => $date,
            'next_date' => $date,
        ]);

        Queue::fake();

        // Run the command
        $this->artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(0);

        // Assert that the job was pushed
        Queue::assertPushed(RecordScheduledTransaction::class, fn ($job) => $job->transaction->id === $transaction->id);
    }

    public function test_transaction_with_next_date_in_the_future_is_not_recorded(): void
    {
        $date = Carbon::tomorrow()->startOfDay();

        $this->createTestTransaction([
            'start_date' => $date,
            'next_date' => $date,
        ]);

        Queue::fake();

        // Run the command
        $this->artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(0);

        // Assert that the job was not pushed
        Queue::assertNotPushed(RecordScheduledTransaction::class);
    }

    public function test_transaction_with_empty_next_date_is_not_recorded(): void
    {
        $this->createTestTransaction(['next_date' => null]);

        Queue::fake();

        // Run the command
        $this->artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(0);

        // Assert that the job was not pushed
        Queue::assertNotPushed(RecordScheduledTransaction::class);
    }
}
