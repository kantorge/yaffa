<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use App\Support\ScheduleInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for FR-9 (see .ai/docs/specifications/budget-schedule-redesign/forecast-performance.md
 * and specification.md): Transaction::scheduleInstances() must not clone a full Eloquent model
 * (and re-query already-loaded relations) per virtual occurrence.
 */
class TransactionScheduleInstancesTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_instances_are_plain_dtos_and_issue_no_extra_queries_per_occurrence(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addYears(5),
        ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->config()->update([
            'account_from_id' => $account->id,
            'amount_from' => 100,
            'amount_to' => 100,
        ]);

        $transaction->transactionSchedule->update([
            'start_date' => now()->startOfMonth(),
            'next_date' => now()->startOfMonth(),
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        // Reload with the relations a real forecast caller would eager-load, so scheduleInstances()
        // has something to reuse (see App\Support\ScheduleInstance).
        $transaction = Transaction::with(['config', 'transactionSchedule'])->findOrFail($transaction->id);
        $startDate = $transaction->transactionSchedule->start_date->clone();

        DB::enableQueryLog();

        // A monthly schedule with no end_date, over a 5 year look-ahead, generates ~60 occurrences -
        // plenty to expose an N+1 if one were reintroduced. Whatever fixed, occurrence-count-independent
        // cost scheduleInstances() itself pays (e.g. resolving the source transaction's currency once)
        // has already happened by the time it returns, so reading every occurrence below must not add
        // to the query count at all.
        $instances = $transaction->scheduleInstances(constraintStart: $startDate, maxLookAhead: now()->addYears(5));
        $this->assertGreaterThan(50, $instances->count());

        foreach ($instances->take(5) as $instance) {
            $x = $instance->config->amount_from;
        }
        $queryCountAfterFew = count(DB::getQueryLog());

        foreach ($instances as $instance) {
            $this->assertInstanceOf(ScheduleInstance::class, $instance);
            $this->assertInstanceOf(TransactionDetailStandard::class, $instance->config);
            $this->assertSame(100.0, $instance->config->amount_from);
        }
        $queryCountAfterAll = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame(
            $queryCountAfterFew,
            $queryCountAfterAll,
            'Reading config on more virtual occurrences must not issue more queries.'
        );
    }

    public function test_schedule_instances_support_ad_hoc_mutation_like_replicate_did(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addYear(),
        ]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->transactionSchedule->update([
            'start_date' => now()->startOfMonth(),
            'next_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        $transaction = Transaction::with(['config', 'transactionSchedule'])->findOrFail($transaction->id);

        $instances = $transaction->scheduleInstances();

        // Calling code (e.g. MainController::account_details()) stashes ad-hoc values onto
        // virtual instances after generation (a running total, in that case) - confirm the DTO
        // supports arbitrary get/set the same way a replicated Eloquent model did.
        foreach ($instances as $index => $instance) {
            $instance->running_total = $index * 10;
        }

        $this->assertSame([0, 10, 20], $instances->pluck('running_total')->all());
    }
}
