<?php

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetChartService;
use App\Services\BudgetSuggestionService;
use Illuminate\Support\Carbon;

/**
 * Budget drift detector (budget-drift-suggestions phase 2a).
 *
 * "Today" is 2026-12-03, so data_through defaults to 2026-11 and the first period a suggestion
 * can apply to is 2027-01. Feature level: the detector reads the batched aggregation, which needs
 * the database.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-12-03 12:00:00');
    $this->user = User::factory()->create(['end_date' => '2028-12-31']);
    $this->usd = Currency::factory()->for($this->user)->fromIsoCodes(['USD'])->create(['base' => true, 'auto_update' => false]);
    AccountGroup::factory()->for($this->user)->create();
});

afterEach(fn () => Carbon::setTestNow());

function newCategory(array $attributes = []): Category
{
    return Category::factory()->for(test()->user)->create($attributes + ['default_aggregation' => 'month', 'active' => true]);
}

/** One withdrawal (or deposit) of $amount in the category, in USD, on the 10th of the month. */
function spend(Category $category, string $month, float $amount, bool $deposit = false): void
{
    $user = test()->user;
    $factory = Transaction::factory()->for($user);
    $transaction = ($deposit ? $factory->deposit($user) : $factory->withdrawal($user))
        ->create(['date' => "{$month}-10", 'schedule' => false]);
    $transaction->forceFill(['currency_id' => test()->usd->id])->save();
    $transaction->transactionItems()->delete();
    $transaction->transactionItems()->create(['category_id' => $category->id, 'amount' => $amount]);
}

/** Monthly actuals from $from to $to inclusive; $amount is a number or a closure of the month (Y-m). */
function spendMonthly(Category $category, string $from, string $to, float|Closure $amount): void
{
    for ($m = Carbon::parse("{$from}-01"); $m->lte(Carbon::parse("{$to}-01")); $m->addMonth()) {
        spend($category, $m->format('Y-m'), $amount instanceof Closure ? $amount($m->format('Y-m')) : $amount);
    }
}

function budgetRow(Category $category, array $attributes = []): Budget
{
    return Budget::factory()->create($attributes + [
        'user_id' => test()->user->id, 'category_id' => $category->id, 'account_id' => null,
        'transaction_type' => 'withdrawal', 'amount' => 24000, 'frequency' => 'MONTHLY', 'interval' => 1,
        'start_date' => '2025-01-01', 'end_date' => null, 'count' => null, 'inflation' => null, 'active' => true,
    ]);
}

function review(?array $ids = null, ?string $through = null, ?string $sensitivity = null, ?string $bias = null): array
{
    return app(BudgetSuggestionService::class)->run(test()->user, $ids, $through, $sensitivity, $bias);
}

function unitFor(array $result, Category $category): ?array
{
    return collect($result['units'])->firstWhere('category_id', $category->id);
}

// ---------------------------------------------------------------- detector

it('suggests a replacement for the screenshot case: budget -24k, actuals -27.5k', function () {
    $category = newCategory();
    $budget = budgetRow($category);
    spendMonthly($category, '2025-12', '2026-11', 27500);

    $result = review([$category->id]);
    $unit = unitFor($result, $category);

    expect($result['run'])->toMatchArray(['scope' => 'selected', 'data_through' => '2026-11', 'sensitivity' => 'medium', 'bias' => 'neutral', 'detector_version' => 1])
        ->and($unit['verdict'])->toBe('suggestion')
        ->and($unit['granularity'])->toBe('month')
        ->and($unit['suggestion'])->toMatchArray([
            'proposal_type' => 'replace',
            'budget_id' => $budget->id,
            'effective_date' => '2027-01-01',
            'current_amount' => -24000,
            'suggested_amount' => -27500,
            'level' => -27500,
            'expected' => -24000,
            'target' => -27500,
            'direction' => 'expense',
            'persistence' => ['k' => 4, 'n' => 4],
        ])
        ->and($unit['suggestion']['evidence'])->toHaveCount(4)
        ->and($unit['suggestion']['candidates'])->toHaveCount(1);

    // The series applies the proposal from the effective date onward only.
    expect($result['series'][0])->toBe(['period' => '2027-01-01', 'current' => -24000.0, 'recommended' => -27500.0])
        ->and(end($result['series']))->toMatchArray(['period' => '2028-12-01', 'current' => -24000.0, 'recommended' => -27500.0]);
});

it('gives no_drift for stable data', function () {
    $category = newCategory();
    budgetRow($category);
    spendMonthly($category, '2025-12', '2026-11', 24000);

    expect(unitFor(review([$category->id]), $category)['verdict'])->toBe('no_drift');
});

it('does not suggest anything from a single spike', function () {
    $category = newCategory();
    budgetRow($category);
    spendMonthly($category, '2025-12', '2026-11', fn ($m) => $m === '2026-09' ? 60000 : 24000);

    expect(unitFor(review([$category->id]), $category)['verdict'])->toBe('no_drift');
});

it('suggests once after a sustained three-period shift', function () {
    $category = newCategory();
    budgetRow($category);
    spendMonthly($category, '2025-12', '2026-11', fn ($m) => $m >= '2026-09' ? 40000 : 24000);

    $unit = unitFor(review([$category->id]), $category);

    expect($unit['verdict'])->toBe('suggestion')
        ->and($unit['suggestion']['persistence'])->toBe(['k' => 3, 'n' => 4])
        ->and($unit['suggestion']['suggested_amount'])->toBe(-28000.0);
});

it('gives cooldown while a contributing budget started recently', function () {
    $category = newCategory();
    budgetRow($category, ['start_date' => '2026-10-01']);
    spendMonthly($category, '2025-12', '2026-11', 40000);

    expect(unitFor(review([$category->id]), $category)['verdict'])->toBe('cooldown');
});

it('does not trigger on a gap that exists only in the past', function () {
    $category = newCategory();
    budgetRow($category);
    spendMonthly($category, '2025-12', '2026-11', fn ($m) => $m <= '2026-06' ? 40000 : 24000);

    expect(unitFor(review([$category->id]), $category)['verdict'])->toBe('no_drift');
});

it('applies the conservative bias asymmetrically', function () {
    $unfavorable = newCategory();
    budgetRow($unfavorable);
    spendMonthly($unfavorable, '2025-12', '2026-11', 25920); // 8% above the budget: below the 10% tolerance, above 7.5%

    $favorable = newCategory();
    budgetRow($favorable);
    spendMonthly($favorable, '2025-12', '2026-11', 21120); // 12% below the budget: above 10%, below 15%

    $neutral = review([$unfavorable->id, $favorable->id]);
    $conservative = review([$unfavorable->id, $favorable->id], null, null, 'conservative');

    expect(unitFor($neutral, $unfavorable)['verdict'])->toBe('no_drift')
        ->and(unitFor($neutral, $favorable)['verdict'])->toBe('suggestion')
        ->and(unitFor($conservative, $unfavorable)['verdict'])->toBe('suggestion')
        ->and(unitFor($conservative, $favorable)['verdict'])->toBe('no_drift');

    // Unfavorable side: the target sits 3% beyond L (-25920 * 1.03)
    expect(unitFor($conservative, $unfavorable)['suggestion']['target'])->toBe(-26697.6);
});

it('gives nothing when a schedule matches the actuals', function () {
    $category = newCategory();
    scheduleFor($category, 24000);
    spendMonthly($category, '2025-12', '2026-11', 24000);

    expect(unitFor(review([$category->id]), $category)['verdict'])->toBe('no_drift');
});

it('creates a budget for a schedule-only unit with a gap', function () {
    $category = newCategory();
    scheduleFor($category, 20000);
    spendMonthly($category, '2025-12', '2026-11', 24000);

    $suggestion = unitFor(review([$category->id]), $category)['suggestion'];

    // Budget rows add to schedules, so the new row covers only the difference
    expect($suggestion)->toMatchArray(['proposal_type' => 'create', 'budget_id' => null, 'suggested_amount' => -4000.0, 'expected' => -20000.0])
        ->and($suggestion['cadence'])->toBe(['frequency' => 'MONTHLY', 'interval' => 1]);
});

function scheduleFor(Category $category, float $amount): void
{
    $user = test()->user;
    $schedule = Transaction::factory()->for($user)->withdrawal_schedule($user)->create();
    $schedule->forceFill(['currency_id' => test()->usd->id])->save();
    $schedule->transactionItems()->delete();
    $schedule->transactionItems()->create(['category_id' => $category->id, 'amount' => $amount]);
    $schedule->transactionSchedule->update([
        'start_date' => '2025-01-05', 'next_date' => '2026-12-05', 'active' => true,
        'end_date' => null, 'count' => null, 'interval' => 1, 'frequency' => 'MONTHLY', 'inflation' => null,
    ]);
}

it('compares a yearly budget row in a monthly category at yearly granularity', function () {
    $category = newCategory();
    budgetRow($category, ['amount' => 120000, 'frequency' => 'YEARLY', 'start_date' => '2023-01-01']);
    foreach (['2023-01', '2024-01', '2025-01'] as $month) {
        spend($category, $month, 120000);
    }

    $unit = unitFor(review([$category->id]), $category);

    expect($unit['granularity'])->toBe('year')
        ->and($unit['verdict'])->toBe('no_drift');
});

it('converts a weekly row to a per-occurrence amount', function () {
    $category = newCategory();
    budgetRow($category, ['amount' => 1000, 'frequency' => 'WEEKLY', 'start_date' => '2025-01-06']);
    spendMonthly($category, '2025-12', '2026-11', 5200);

    $suggestion = unitFor(review([$category->id]), $category)['suggestion'];

    // 2027 has 52 Mondays: E = -52000 / 12, gap per month -866.67, 4.33 occurrences a month: -200 each
    expect($suggestion)->toMatchArray(['current_amount' => -1000.0, 'suggested_amount' => -1200.0])
        ->and($suggestion['cadence'])->toBe(['frequency' => 'WEEKLY', 'interval' => 1]);
});

it('converts to the account currency of an account-scoped row at the latest rate', function () {
    $eur = Currency::factory()->for($this->user)->fromIsoCodes(['EUR'])->create(['base' => null, 'auto_update' => false]);
    CurrencyRate::factory()->betweenCurrencies($eur, $this->usd)->create(['date' => '2025-01-01', 'rate' => 2.0]);
    $account = AccountEntity::factory()->for($this->user)
        ->for(Account::factory()->withUser($this->user)->state(['currency_id' => $eur->id]), 'config')
        ->create(['name' => 'Euro account']);

    $category = newCategory();
    budgetRow($category, ['amount' => 100, 'account_id' => $account->id]); // EUR 100 = USD 200 a month
    spendMonthly($category, '2025-12', '2026-11', 300);

    $suggestion = unitFor(review([$category->id]), $category)['suggestion'];

    // The USD 100 gap per month is EUR 50
    expect($suggestion)->toMatchArray(['current_amount' => -100.0, 'suggested_amount' => -150.0])
        ->and($suggestion['currency']['iso_code'])->toBe('EUR')
        ->and($suggestion['candidates'][0]['account_name'])->toBe('Euro account');
});

it('applies inflation to the current amount and carries it to the new base amount', function () {
    $category = newCategory();
    budgetRow($category, ['amount' => 1000, 'inflation' => 10.0]);
    spendMonthly($category, '2025-12', '2026-11', 1500);

    $suggestion = unitFor(review([$category->id]), $category)['suggestion'];

    // Two inflation steps by 2027-01-01: 1000 * 1.1^2 = 1210
    expect($suggestion)->toMatchArray(['current_amount' => -1210.0, 'expected' => -1210.0, 'suggested_amount' => -1500.0]);
});

it('gives a level equal to the mean of the chart actuals over the window', function () {
    $category = newCategory();
    budgetRow($category);
    spendMonthly($category, '2025-06', '2026-11', fn ($m) => 24000 + 100 * (int) mb_substr($m, 5) + ($m >= '2026-08' ? 12000 : 0));

    $level = unitFor(review([$category->id]), $category)['suggestion']['level'];

    $actuals = collect(app(BudgetChartService::class)->chart($this->user, collect([$category]))['chartData'])
        ->filter(fn ($entry) => $entry['period']->between('2025-12-01', '2026-11-30'))
        ->pluck('actual');

    expect($actuals)->toHaveCount(12)
        ->and($level)->toEqualWithDelta($actuals->avg(), 0.01);
});

it('gives the same suggestion key for identical runs', function () {
    $category = newCategory();
    budgetRow($category);
    spendMonthly($category, '2025-12', '2026-11', 27500);

    $first = unitFor(review([$category->id]), $category)['suggestion']['key'];

    expect(unitFor(review([$category->id]), $category)['suggestion']['key'])->toBe($first)
        ->and($first)->toHaveLength(16);
});

it('returns needs_review when the suggested amount would flip sign', function () {
    $category = newCategory();
    budgetRow($category, ['amount' => 1000]);
    for ($m = Carbon::parse('2025-12-01'); $m->lte(Carbon::parse('2026-11-01')); $m->addMonth()) {
        spend($category, $m->format('Y-m'), 1500, true); // refunds: a net deposit of 1500 a month
    }

    expect(unitFor(review([$category->id]), $category)['verdict'])->toBe('needs_review');
});

it('returns missing_rates when a currency has no rates in the window', function () {
    $eur = Currency::factory()->for($this->user)->fromIsoCodes(['EUR'])->create(['base' => null, 'auto_update' => false]);
    $category = newCategory();
    budgetRow($category);
    spendMonthly($category, '2025-12', '2026-10', 24000);
    $transaction = Transaction::factory()->for($this->user)->withdrawal($this->user)->create(['date' => '2026-11-10', 'schedule' => false]);
    $transaction->forceFill(['currency_id' => $eur->id])->save();
    $transaction->transactionItems()->delete();
    $transaction->transactionItems()->create(['category_id' => $category->id, 'amount' => 24000]);

    $result = review([$category->id]);

    expect(unitFor($result, $category)['verdict'])->toBe('missing_rates')
        ->and($result['run']['currencies_without_rates'][0]['iso_code'])->toBe('EUR');
});

it('gives insufficient_data below the minimum history', function () {
    $category = newCategory();
    budgetRow($category);
    spendMonthly($category, '2026-08', '2026-11', 40000); // 4 months, 6 needed

    expect(unitFor(review([$category->id]), $category)['verdict'])->toBe('insufficient_data');
});

// ---------------------------------------------------------------- scope and cutoff

it('skips excluded and inactive categories in review-all but reports an explicitly selected excluded one', function () {
    $normal = newCategory();
    $excluded = newCategory(['calculate_budget_suggestions' => false]);
    $inactive = newCategory(['active' => false]);
    foreach ([$normal, $excluded, $inactive] as $category) {
        budgetRow($category);
        spendMonthly($category, '2025-12', '2026-11', 24000);
    }

    $all = review();
    $selected = review([$excluded->id, $inactive->id]);

    expect(collect($all['units'])->pluck('category_id')->all())->toBe([$normal->id])
        ->and($all['series'])->toBeNull()
        ->and($all['run']['scope'])->toBe('all')
        ->and(unitFor($selected, $excluded)['verdict'])->toBe('excluded')
        ->and(unitFor($selected, $inactive))->toBeNull();
});

it('excludes the children of an excluded parent', function () {
    $parent = newCategory(['calculate_budget_suggestions' => false]);
    $child = newCategory(['parent_id' => $parent->id]);
    budgetRow($child);
    spendMonthly($child, '2025-12', '2026-11', 24000);

    expect(review()['units'])->toBe([])
        ->and(unitFor(review([$child->id]), $child)['verdict'])->toBe('excluded');
});

it('counts an excluded child without budgets toward its parent unit', function () {
    $parent = newCategory();
    $child = newCategory(['parent_id' => $parent->id, 'calculate_budget_suggestions' => false]);
    budgetRow($parent, ['amount' => 30000]);
    spendMonthly($parent, '2025-12', '2026-11', 20000);
    spendMonthly($child, '2025-12', '2026-11', 10000);

    $result = review();

    // 20k + 10k matches the 30k budget; without the child the parent would look underspent
    expect(collect($result['units'])->pluck('category_id')->all())->toBe([$parent->id])
        ->and(unitFor($result, $parent)['verdict'])->toBe('no_drift');
});

it('ignores periods after data_through', function () {
    $category = newCategory();
    budgetRow($category);
    spendMonthly($category, '2025-12', '2026-11', fn ($m) => $m >= '2026-10' ? 40000 : 24000);

    expect(unitFor(review([$category->id], '2026-09'), $category)['verdict'])->toBe('no_drift')
        ->and(unitFor(review([$category->id]), $category)['verdict'])->toBe('no_drift'); // 2 of 4 periods is not enough persistence either way

});

it('counts a partly covered quarter as incomplete', function () {
    $category = newCategory(['default_aggregation' => 'quarter']);
    budgetRow($category, ['amount' => 90000, 'interval' => 3]);
    foreach (['2025-01', '2025-04', '2025-07', '2025-10', '2026-01', '2026-04', '2026-07'] as $month) {
        spend($category, $month, 90000);
    }
    // The open quarter (Oct-Dec 2026) holds a huge amount that must not count
    spend($category, '2026-10', 500000);
    spend($category, '2026-11', 500000);

    $unit = unitFor(review([$category->id]), $category);

    expect($unit['granularity'])->toBe('quarter')
        ->and($unit['verdict'])->toBe('no_drift');
});

// ---------------------------------------------------------------- new budgets

it('gives irregular for a category with occasional spending', function () {
    $category = newCategory();
    foreach (['2025-03', '2026-02', '2026-06'] as $month) {
        spend($category, $month, 50000);
    }

    expect(unitFor(review([$category->id]), $category)['verdict'])->toBe('irregular');
});

it('creates a monthly budget for a monthly-regular category without one', function () {
    $category = newCategory();
    spendMonthly($category, '2025-10', '2026-11', 2000);

    $unit = unitFor(review([$category->id]), $category);

    expect($unit['verdict'])->toBe('suggestion')
        ->and($unit['suggestion'])->toMatchArray(['proposal_type' => 'create', 'budget_id' => null, 'effective_date' => '2027-01-01', 'suggested_amount' => -2000.0])
        ->and($unit['suggestion']['cadence'])->toBe(['frequency' => 'MONTHLY', 'interval' => 1]);
});

it('creates a quarterly budget for a quarterly-regular category without one', function () {
    $category = newCategory();
    foreach (['2025-07', '2025-10', '2026-01', '2026-04', '2026-07'] as $month) {
        spend($category, $month, 45000);
    }

    $unit = unitFor(review([$category->id]), $category);

    expect($unit['granularity'])->toBe('quarter')
        ->and($unit['suggestion'])->toMatchArray(['proposal_type' => 'create', 'suggested_amount' => -45000.0])
        ->and($unit['suggestion']['cadence'])->toBe(['frequency' => 'MONTHLY', 'interval' => 3]);
});

it('spreads the gap over inflation multipliers when the horizon crosses a year boundary', function () {
    Carbon::setTestNow('2027-03-03 12:00:00');
    $category = newCategory();
    budgetRow($category, ['amount' => 1000, 'inflation' => 10.0]);
    spendMonthly($category, '2026-03', '2027-02', 1500);

    $suggestion = unitFor(review([$category->id]), $category)['suggestion'];

    // 9 months at x1 and 3 at x1.1 after 1210 now: the projected mean of the new row must equal the 1500 level
    expect($suggestion['current_amount'])->toBe(-1210.0)
        ->and(abs($suggestion['suggested_amount']))->toEqualWithDelta(1463.41, 0.01);
});

it('reports an excluded member the user explicitly selected under a non-excluded root', function () {
    $parent = newCategory();
    $child = newCategory(['parent_id' => $parent->id, 'calculate_budget_suggestions' => false]);
    budgetRow($parent, ['amount' => 30000]);
    spendMonthly($parent, '2025-12', '2026-11', 20000);
    spendMonthly($child, '2025-12', '2026-11', 10000);

    expect(unitFor(review([$child->id]), $child)['verdict'])->toBe('excluded');
});
