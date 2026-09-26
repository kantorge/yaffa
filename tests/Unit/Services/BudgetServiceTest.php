<?php

namespace Tests\Unit\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\RecurrenceRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BudgetService
    {
        return new BudgetService(new RecurrenceRuleService());
    }

    public function test_store_creates_a_budget_owned_by_the_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        // amount (MoneyCast) resolves currency via the user's base currency (no account_id here).
        Currency::factory()->for($user)->create(['base' => true]);

        $budget = $this->service()->store($user, [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay(),
        ]);

        $this->assertSame($user->id, $budget->user_id);
        $this->assertTrue($budget->active);
        $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'user_id' => $user->id]);
    }

    public function test_update_persists_changed_attributes(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 100,
        ]);

        $updated = $this->service()->update($budget, ['amount' => 300]);

        $this->assertSame(300.0, $updated->amount->getAmount()->toFloat());
        $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'amount' => 300]);
    }

    public function test_delete_removes_the_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $result = $this->service()->delete($budget);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_project_occurrences_returns_dates_within_the_requested_range(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::parse('2024-01-15'),
            'end_date' => null,
            'count' => null,
        ]);

        $occurrences = $this->service()->projectOccurrences(
            $budget,
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-04-01'),
        );

        $this->assertCount(3, $occurrences);
        $this->assertSame('2024-01-15', $occurrences[0]->toDateString());
        $this->assertSame('2024-03-15', $occurrences[2]->toDateString());
    }

    /**
     * Regression test: projecting from exactly the budget's own start_date (the common case for
     * both budgetChart() and the account-balance budget bucket) must include the occurrence that
     * lands on start_date itself, not silently drop it.
     */
    public function test_project_occurrences_includes_an_occurrence_exactly_on_the_from_date(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::parse('2024-01-15'),
            'end_date' => null,
            'count' => null,
        ]);

        $occurrences = $this->service()->projectOccurrences(
            $budget,
            $budget->start_date,
            Carbon::parse('2024-02-01'),
        );

        $this->assertCount(1, $occurrences);
        $this->assertSame('2024-01-15', $occurrences[0]->toDateString());
    }

    /**
     * Regression coverage for the performance-audit finding that projectOccurrences() recomputed
     * the recurrence expansion from scratch on every call. Seeds the exact cache key it computes
     * with a sentinel value - if the method actually hits the cache instead of recomputing, it
     * returns the sentinel verbatim rather than the real occurrence dates.
     */
    public function test_project_occurrences_uses_the_cached_result(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::parse('2024-01-15'),
            'end_date' => null,
            'count' => null,
        ]);

        $from = Carbon::parse('2024-01-01');
        $to = Carbon::parse('2024-04-01');

        $cacheKey = "budget-occurrences:{$budget->id}:{$budget->updated_at->timestamp}:"
            . "{$from->toDateString()}:{$to->toDateString()}";
        Cache::put($cacheKey, ['2099-01-01'], now()->addHour());

        $occurrences = $this->service()->projectOccurrences($budget, $from, $to);

        $this->assertCount(1, $occurrences);
        $this->assertSame('2099-01-01', $occurrences[0]->toDateString());
    }

    /**
     * Touching the budget (which bumps updated_at) must change the cache key so the next call
     * recomputes instead of serving a stale result from before the change.
     */
    public function test_project_occurrences_recomputes_after_the_budget_is_touched(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::parse('2024-01-15'),
            'end_date' => null,
            'count' => null,
        ]);

        $from = Carbon::parse('2024-01-01');
        $to = Carbon::parse('2024-04-01');

        $before = $this->service()->projectOccurrences($budget, $from, $to);
        $this->assertCount(3, $before);

        // Second-precision timestamp cache key: without a real time gap, an update landing in
        // the same wall-clock second as create() wouldn't change updated_at->timestamp.
        $this->travel(1)->seconds();
        $budget->update(['frequency' => 'WEEKLY']);

        $after = $this->service()->projectOccurrences($budget, $from, $to);

        $this->assertGreaterThan(count($before), count($after));
    }
}
