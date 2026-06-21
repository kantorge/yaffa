<?php

namespace Tests\Feature\Console;

use App\Console\Commands\RecordScheduledTransactions;
use App\Jobs\RecordScheduledTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecordScheduledTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_dispatches_job_for_transaction_due_today(): void
    {
        Queue::fake();

        Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->hasTransactionSchedule([
                'next_date' => Carbon::today()->toDateString(),
                'automatic_recording' => true,
            ])
            ->create(['user_id' => $this->user->id]);

        $this->artisan(RecordScheduledTransactions::class)->assertSuccessful();

        Queue::assertPushed(RecordScheduledTransaction::class, 1);
    }

    public function test_dispatches_job_for_transaction_overdue(): void
    {
        Queue::fake();

        Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->hasTransactionSchedule([
                'next_date' => Carbon::yesterday()->toDateString(),
                'automatic_recording' => true,
            ])
            ->create(['user_id' => $this->user->id]);

        $this->artisan(RecordScheduledTransactions::class)->assertSuccessful();

        Queue::assertPushed(RecordScheduledTransaction::class, 1);
    }

    public function test_does_not_dispatch_job_for_transaction_due_tomorrow(): void
    {
        Queue::fake();

        Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->hasTransactionSchedule([
                'next_date' => Carbon::tomorrow()->toDateString(),
                'automatic_recording' => true,
            ])
            ->create(['user_id' => $this->user->id]);

        $this->artisan(RecordScheduledTransactions::class)->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_does_not_dispatch_job_when_automatic_recording_is_disabled(): void
    {
        Queue::fake();

        Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->hasTransactionSchedule([
                'next_date' => Carbon::today()->toDateString(),
                'automatic_recording' => false,
            ])
            ->create(['user_id' => $this->user->id]);

        $this->artisan(RecordScheduledTransactions::class)->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_date_boundary_is_calendar_date_not_datetime(): void
    {
        // This test guards against the previous bug where Carbon::now() (a datetime)
        // was compared against the DATE column, so a transaction due today would
        // be missed if the command ran before midnight UTC on a UTC+N server.
        // The fix uses Carbon::today()->toDateString() for a pure date comparison.
        Queue::fake();

        // Travel to 23:59:59 on a given date — the command must still pick up
        // transactions due on that calendar date regardless of the time of day.
        $this->travelTo(Carbon::parse('2025-06-15 23:59:59'));

        Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->hasTransactionSchedule([
                'next_date' => '2025-06-15',
                'automatic_recording' => true,
            ])
            ->create(['user_id' => $this->user->id]);

        $this->artisan(RecordScheduledTransactions::class)->assertSuccessful();

        Queue::assertPushed(RecordScheduledTransaction::class, 1);
    }
}
