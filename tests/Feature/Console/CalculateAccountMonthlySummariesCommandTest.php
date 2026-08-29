<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalculateAccountMonthlySummariesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cancels_only_the_targeted_users_own_stale_batches_before_dispatching(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $staleBatchId = 'stale-budget-batch';
        $otherUsersBatchId = 'other-users-budget-batch';
        $finishedBatchId = 'finished-budget-batch';

        $makeBatchRow = fn (string $id, string $name, ?int $finishedAt) => [
            'id' => $id,
            'name' => $name,
            'total_jobs' => 1,
            'pending_jobs' => 1,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => serialize([]),
            'cancelled_at' => null,
            'created_at' => now()->timestamp,
            'finished_at' => $finishedAt,
        ];

        DB::table('job_batches')->insert([
            $makeBatchRow($staleBatchId, "CalculateAccountMonthlySummariesJob-account_balance-budget-{$user->id}", null),
            $makeBatchRow($otherUsersBatchId, "CalculateAccountMonthlySummariesJob-account_balance-budget-{$otherUser->id}", null),
            // Already-finished batch for the same name must be left alone (nothing to cancel).
            $makeBatchRow($finishedBatchId, "CalculateAccountMonthlySummariesJob-account_balance-budget-{$user->id}", now()->timestamp),
        ]);

        $this->artisan('app:cache:account-monthly-summaries', ['userId' => $user->id])
            ->assertExitCode(0);

        $this->assertNotNull(DB::table('job_batches')->where('id', $staleBatchId)->value('cancelled_at'));
        $this->assertNull(DB::table('job_batches')->where('id', $otherUsersBatchId)->value('cancelled_at'));
        $this->assertNull(DB::table('job_batches')->where('id', $finishedBatchId)->value('cancelled_at'));
    }
}
