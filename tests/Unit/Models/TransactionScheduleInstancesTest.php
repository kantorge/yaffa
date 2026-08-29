<?php

namespace Tests\Unit\Models;

use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use App\Support\ScheduleInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

        $account = AccountEntity::factory()->asAccount($user)->create();

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
            $this->assertSame('100.0000', (string) $instance->config->amount_from->getAmount());
        }
        $queryCountAfterFew = count(DB::getQueryLog());

        foreach ($instances as $instance) {
            $this->assertInstanceOf(ScheduleInstance::class, $instance);
            $this->assertInstanceOf(TransactionDetailStandard::class, $instance->config);
            $this->assertSame('100.0000', (string) $instance->config->amount_from->getAmount());
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

    /**
     * Regression guard for the by_day/by_month drift fixed by routing scheduleInstances() through
     * RecurrenceRuleService::buildRule() instead of a hand-built Recurr\Rule (see
     * .ai/docs/features/budget-schedule-redesign/architecture.md, "Known Risks"). Before the fix,
     * an ordinal-weekday rule like "first Wednesday of every month" silently fell back to plain
     * FREQ=MONTHLY;INTERVAL=1-from-start_date, landing on the same day-of-month every time
     * regardless of which weekday it fell on.
     */
    public function test_schedule_instances_honor_by_day_ordinal_weekday_pattern(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addYears(2),
        ]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        // 2026-01-07 is the first Wednesday of January 2026.
        $startDate = Carbon::parse('2026-01-07');
        $this->assertSame('Wednesday', $startDate->format('l'));

        $transaction->transactionSchedule->update([
            'start_date' => $startDate,
            'next_date' => $startDate,
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
            'by_day' => '1WE',
        ]);

        $transaction = Transaction::with(['config', 'transactionSchedule'])->findOrFail($transaction->id);

        $instances = $transaction->scheduleInstances(
            constraintStart: $startDate->clone(),
            maxLookAhead: $startDate->clone()->addMonths(6),
        );

        // 6 monthly occurrences, one "first Wednesday" per month - a plain FREQ=MONTHLY fallback
        // would instead land on the 7th of every month, which is only sometimes a Wednesday.
        $this->assertGreaterThanOrEqual(6, $instances->count());

        foreach ($instances as $instance) {
            $this->assertSame(
                'Wednesday',
                $instance->date->format('l'),
                "Occurrence on {$instance->date->toDateString()} is not a Wednesday - by_day was not applied."
            );
            $this->assertLessThanOrEqual(
                7,
                $instance->date->day,
                "Occurrence on {$instance->date->toDateString()} is not the FIRST Wednesday of its month."
            );
        }
    }

    /**
     * Regression coverage for the performance-audit finding that scheduleInstances() recomputed
     * the recurrence expansion from scratch on every call. Seeds the exact cache key it computes
     * with a sentinel value - if the method actually hits the cache instead of recomputing, the
     * returned instance carries the sentinel date verbatim rather than a real occurrence date.
     */
    public function test_schedule_instances_uses_the_cached_occurrence_dates(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['end_date' => now()->addYears(2)]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->transactionSchedule->update([
            'start_date' => Carbon::parse('2024-01-01'),
            'next_date' => Carbon::parse('2024-01-01'),
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        $transaction = Transaction::with(['config', 'transactionSchedule'])->findOrFail($transaction->id);
        $schedule = $transaction->transactionSchedule;

        $constraintStart = Carbon::parse('2024-01-01');
        $maxLookAhead = Carbon::parse('2024-04-01');

        $cacheKey = "schedule-occurrences:{$schedule->id}:{$schedule->updated_at->timestamp}:"
            . "{$transaction->updated_at->timestamp}:2024-01-01:2024-04-01:500";
        Cache::put($cacheKey, ['2099-01-01'], now()->addHour());

        $instances = $transaction->scheduleInstances($constraintStart, $maxLookAhead);

        $this->assertCount(1, $instances);
        $this->assertSame('2099-01-01', $instances->first()->date->toDateString());
    }

    /**
     * Touching the schedule (which bumps updated_at) must change the cache key so the next call
     * recomputes instead of serving a stale result from before the change.
     */
    public function test_schedule_instances_recompute_after_the_schedule_is_touched(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['end_date' => now()->addYears(2)]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->transactionSchedule->update([
            'start_date' => Carbon::parse('2024-01-01'),
            'next_date' => Carbon::parse('2024-01-01'),
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        $transaction = Transaction::with(['config', 'transactionSchedule'])->findOrFail($transaction->id);

        $constraintStart = Carbon::parse('2024-01-01');
        $maxLookAhead = Carbon::parse('2024-04-01');

        $before = $transaction->scheduleInstances($constraintStart->clone(), $maxLookAhead->clone());
        $this->assertCount(4, $before);

        // Second-precision timestamp cache key: without a real time gap, an update landing in
        // the same wall-clock second as create() wouldn't change updated_at->timestamp.
        $this->travel(1)->seconds();
        $transaction->transactionSchedule->update(['frequency' => 'WEEKLY']);
        $transaction = Transaction::with(['config', 'transactionSchedule'])->findOrFail($transaction->id);

        $after = $transaction->scheduleInstances($constraintStart->clone(), $maxLookAhead->clone());

        $this->assertGreaterThan($before->count(), $after->count());
    }
}
