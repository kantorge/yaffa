<?php

namespace Tests\Unit\Console\Commands;

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

        AccountGroup::factory()->for($user)->create();
        Currency::factory()->for($user)->create();
        AccountEntity::factory()->account()->create(['user_id' => $user->id]);
        Category::factory()->for($user)->create();
        AccountEntity::factory()->payee()->create(['user_id' => $user->id]);

        // Create a scheduled transaction
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->withdrawal_schedule($user)
            ->for($user)
            ->create();

        $start = Carbon::parse('first day of next month')->startOfDay();

        TransactionSchedule::factory()
            ->create(
                array_merge(
                    [
                        'start_date' => $start,
                        'next_date' => $start,
                        'end_date' => null,
                        'transaction_id' => $transaction->id,
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

    /** @test */
    public function test_transaction_with_next_date_today_is_recorded()
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

    /** @test */
    public function test_transaction_with_next_date_in_the_past_is_recorded()
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

    /** @test */
    public function test_transaction_with_next_date_in_the_future_is_not_recorded()
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

    /** @test */
    public function test_transaction_with_empty_next_date_is_not_recorded()
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
