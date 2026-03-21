<?php

namespace Tests\Feature\Console;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MergeStandardTransactionItemsCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * The command dispatches merge jobs for standard transactions of all users.
     */
    public function test_dispatches_jobs_for_all_standard_transactions(): void
    {
        Bus::fake();

        $standard = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => false, 'budget' => false]);

        $this->artisan('app:transactions:merge-standard-items')
            ->assertSuccessful();

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() >= 1);
    }

    /**
     * The command accepts a userId argument and only processes that user's transactions.
     */
    public function test_accepts_user_id_argument(): void
    {
        Bus::fake();

        $otherUser = User::factory()->create();

        Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => false, 'budget' => false]);

        Transaction::factory()
            ->withdrawal($otherUser)
            ->create(['user_id' => $otherUser->id, 'schedule' => false, 'budget' => false]);

        $this->artisan('app:transactions:merge-standard-items', ['userId' => $this->user->id])
            ->assertSuccessful();

        // Should have dispatched a batch with exactly 1 job (only for $this->user)
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }

    /**
     * The command skips schedule and budget transactions.
     */
    public function test_skips_schedule_and_budget_transactions(): void
    {
        Bus::fake();

        Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => true, 'budget' => false]);

        Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => false, 'budget' => true]);

        $this->artisan('app:transactions:merge-standard-items', ['userId' => $this->user->id])
            ->assertSuccessful();

        Bus::assertNothingBatched();
    }

    /**
     * The command fails gracefully with an invalid userId.
     */
    public function test_fails_with_invalid_user_id(): void
    {
        $this->artisan('app:transactions:merge-standard-items', ['userId' => 9999])
            ->assertFailed();
    }
}
