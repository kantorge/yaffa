<?php

namespace Tests\Feature\API;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FR-2/FR-7 coverage for ReportApiController::budgetChart() (see
 * .ai/docs/specifications/budget-schedule-redesign/specification.md). There was no prior test
 * coverage for this endpoint at all.
 */
class ReportApiBudgetChartTest extends TestCase
{
    use RefreshDatabase;

    private function periodEntry(array $chartData, Carbon $month): ?array
    {
        foreach ($chartData as $entry) {
            if (Carbon::parse($entry['period'])->isSameMonth($month)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The "critical realization" example from background.md: a telco bill scheduled withdrawal
     * split across two categories must be counted once per period (the sum of its relevant
     * items), never once per category. Schedule-derived items land in the 'forecast' series
     * (kept separate from 'budget', which is standalone Budget rows only).
     */
    public function test_schedule_derived_items_are_summed_once_per_period_not_once_per_category(): void
    {
        $user = User::factory()->create([
            'end_date' => now()->addMonths(2)->endOfMonth(),
        ]);
        Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);

        $tvCategory = Category::factory()->for($user)->create();
        $broadbandCategory = Category::factory()->for($user)->create();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->createMany([
            ['category_id' => $tvCategory->id, 'amount' => 30],
            ['category_id' => $broadbandCategory->id, 'amount' => 20],
        ]);
        $transaction->config()->update(['amount_from' => 50, 'amount_to' => 50]);

        $currentMonth = now()->startOfMonth();
        $transaction->transactionSchedule->update([
            'start_date' => $currentMonth,
            'next_date' => $currentMonth,
            'active' => true,
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
            'inflation' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(route('api.v1.reports.budget-chart', [
            'categories' => [$tvCategory->id, $broadbandCategory->id],
        ]));

        $response->assertOk();

        $entry = $this->periodEntry($response->json('chartData'), $currentMonth);

        $this->assertNotNull($entry);
        // Withdrawal of the two items' combined amount (30 + 20), not double-counted per category.
        $this->assertEqualsWithDelta(-50.0, $entry['forecast'], 0.001);
        // The old 'budget' series no longer includes schedule-derived amounts.
        $this->assertEqualsWithDelta(0.0, $entry['budget'], 0.001);
        $this->assertCount(1, $entry['scheduleBreakdown']);
        $this->assertEqualsWithDelta(-50.0, $entry['scheduleBreakdown'][0]['amount'], 0.001);
    }

    /**
     * FR-8/inflation-adjacent UI needs: the schedule's own inflation rate must be visible in the
     * forecast breakdown, and projection must start from next_date, not start_date - an occurrence
     * before next_date has either already been recorded as an actual transaction or skipped, so it
     * must not also appear as a forecast.
     */
    public function test_forecast_series_projects_from_next_date_and_excludes_inactive_schedules(): void
    {
        $user = User::factory()->create([
            'end_date' => now()->addMonths(2)->endOfMonth(),
        ]);
        Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);

        $category = Category::factory()->for($user)->create();

        /** @var Transaction $activeTransaction */
        $activeTransaction = Transaction::factory()->for($user)->withdrawal_schedule($user)->create();
        $activeTransaction->transactionItems()->delete();
        $activeTransaction->transactionItems()->create(['category_id' => $category->id, 'amount' => 40]);
        $activeTransaction->config()->update(['amount_from' => 40, 'amount_to' => 40]);

        $pastMonth = now()->subMonth()->startOfMonth();
        $nextMonth = now()->addMonth()->startOfMonth();
        $activeTransaction->transactionSchedule->update([
            'start_date' => $pastMonth,
            'next_date' => $nextMonth,
            'active' => true,
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
            'inflation' => 5.0,
        ]);

        /** @var Transaction $inactiveTransaction */
        $inactiveTransaction = Transaction::factory()->for($user)->withdrawal_schedule($user)->create();
        $inactiveTransaction->transactionItems()->delete();
        $inactiveTransaction->transactionItems()->create(['category_id' => $category->id, 'amount' => 999]);
        $inactiveTransaction->config()->update(['amount_from' => 999, 'amount_to' => 999]);
        $inactiveTransaction->transactionSchedule->update([
            'start_date' => now()->subYears(2),
            // No next_date and an end_date in the past: isActive() falls back to checking the
            // recurrence rule itself, which yields no more occurrences - active becomes false.
            'next_date' => null,
            'end_date' => now()->subYear(),
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
            'inflation' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(route('api.v1.reports.budget-chart', [
            'categories' => [$category->id],
        ]));

        $response->assertOk();

        // start_date's month must not appear as a forecast occurrence - only next_date onward.
        $startMonthEntry = $this->periodEntry($response->json('chartData'), $pastMonth);
        $this->assertNull($startMonthEntry);

        $nextMonthEntry = $this->periodEntry($response->json('chartData'), $nextMonth);
        $this->assertNotNull($nextMonthEntry);
        $this->assertEqualsWithDelta(-40.0, $nextMonthEntry['forecast'], 0.001);
        $this->assertEqualsWithDelta(5.0, $nextMonthEntry['scheduleBreakdown'][0]['transaction_schedule']['inflation'], 0.001);

        // The inactive schedule's 999 amount must never appear anywhere in the response.
        foreach ($response->json('chartData') as $entry) {
            $this->assertNotEqualsWithDelta(-999.0, $entry['forecast'], 0.001);
        }
    }

    public function test_account_selection_filters_budgets_the_same_way_it_filters_actual_and_forecast_data(): void
    {
        $user = User::factory()->create([
            'end_date' => now()->addMonths(2)->endOfMonth(),
        ]);
        Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);

        $category = Category::factory()->for($user)->create();

        AccountGroup::factory()->for($user)->create();
        $account = Account::factory()->withUser($user)->create();
        $accountEntity = AccountEntity::factory()
            ->for($user)
            ->for($account, 'config')
            ->create(['name' => 'Checking Account']);

        $currentMonth = now()->startOfMonth();

        $accountScopedBudget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $accountEntity->id,
            'transaction_type' => 'withdrawal',
            'amount' => 200,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => $currentMonth,
            'end_date' => null,
            'count' => null,
            'inflation' => null,
        ]);

        $agnosticBudget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => null,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => $currentMonth,
            'end_date' => null,
            'count' => null,
            'inflation' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        // 'selected' + this account: only the account-scoped budget counts, not the agnostic one.
        $selectedResponse = $this->getJson(route('api.v1.reports.budget-chart', [
            'categories' => [$category->id],
            'accountSelection' => 'selected',
            'accountEntity' => $accountEntity->id,
        ]));
        $selectedResponse->assertOk();
        $selectedEntry = $this->periodEntry($selectedResponse->json('chartData'), $currentMonth);
        $this->assertNotNull($selectedEntry);
        $this->assertEqualsWithDelta(-200.0, $selectedEntry['budget'], 0.001);
        $this->assertSame([$accountScopedBudget->id], collect($selectedEntry['budgetBreakdown'])->pluck('budget_id')->all());

        // 'none': only account-agnostic budgets count (mirrors getScheduledItems()'s identical
        // 'none' => whereNull('account_id') handling of Budget rows) - not "no budgets at all".
        $noneResponse = $this->getJson(route('api.v1.reports.budget-chart', [
            'categories' => [$category->id],
            'accountSelection' => 'none',
        ]));
        $noneResponse->assertOk();
        $noneEntry = $this->periodEntry($noneResponse->json('chartData'), $currentMonth);
        $this->assertNotNull($noneEntry);
        $this->assertEqualsWithDelta(-100.0, $noneEntry['budget'], 0.001);
        $this->assertSame([$agnosticBudget->id], collect($noneEntry['budgetBreakdown'])->pluck('budget_id')->all());
    }

    public function test_account_scoped_and_account_agnostic_budgets_for_the_same_category_are_summed_and_exposed_in_breakdown(): void
    {
        $user = User::factory()->create([
            'end_date' => now()->addMonths(2)->endOfMonth(),
        ]);
        Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);

        $category = Category::factory()->for($user)->create();

        AccountGroup::factory()->for($user)->create();
        $account = Account::factory()->withUser($user)->create();
        $accountEntity = AccountEntity::factory()
            ->for($user)
            ->for($account, 'config')
            ->create(['name' => 'Checking Account']);

        $currentMonth = now()->startOfMonth();

        $accountScopedBudget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $accountEntity->id,
            'transaction_type' => 'withdrawal',
            'amount' => 200,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => $currentMonth,
            'end_date' => null,
            'count' => null,
            'inflation' => null,
        ]);

        $agnosticBudget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => null,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => $currentMonth,
            'end_date' => null,
            'count' => null,
            'inflation' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(route('api.v1.reports.budget-chart', [
            'categories' => [$category->id],
        ]));

        $response->assertOk();

        $entry = $this->periodEntry($response->json('chartData'), $currentMonth);

        $this->assertNotNull($entry);
        // Both budgets count in full, regardless of account_id - not deduplicated (FR-2/#2).
        $this->assertEqualsWithDelta(-300.0, $entry['budget'], 0.001);

        $breakdownBudgetIds = collect($entry['budgetBreakdown'])->pluck('budget_id')->sort()->values()->all();
        $this->assertSame(
            collect([$accountScopedBudget->id, $agnosticBudget->id])->sort()->values()->all(),
            $breakdownBudgetIds
        );

        $accountScopedEntry = collect($entry['budgetBreakdown'])
            ->firstWhere('budget_id', $accountScopedBudget->id);
        $this->assertSame($accountEntity->id, $accountScopedEntry['account_id']);
        $this->assertSame('Checking Account', $accountScopedEntry['account_name']);
        $this->assertEqualsWithDelta(-200.0, $accountScopedEntry['amount'], 0.001);

        $agnosticEntry = collect($entry['budgetBreakdown'])
            ->firstWhere('budget_id', $agnosticBudget->id);
        $this->assertNull($agnosticEntry['account_id']);
        $this->assertNull($agnosticEntry['account_name']);
        $this->assertEqualsWithDelta(-100.0, $agnosticEntry['amount'], 0.001);
    }

    public function test_inactive_budget_does_not_contribute_to_the_chart(): void
    {
        $user = User::factory()->create([
            'end_date' => now()->addMonths(2)->endOfMonth(),
        ]);
        Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);

        $category = Category::factory()->for($user)->create();

        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => null,
            'transaction_type' => 'withdrawal',
            'amount' => 500,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => now()->subYears(2),
            'end_date' => now()->subYear(),
            'count' => null,
            'inflation' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(route('api.v1.reports.budget-chart', [
            'categories' => [$category->id],
        ]));

        $response->assertOk();

        $entry = $this->periodEntry($response->json('chartData'), now());
        $this->assertNull($entry);
    }
}
