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
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/**
 * Pins the budgetChart response shape and values across the BudgetChartService extraction
 * (budget-drift-suggestions phase 1). The golden file was recorded from the pre-extraction controller.
 * Set UPDATE_BUDGET_CHART_GOLDEN=1 to re-record it deliberately.
 */

/**
 * Database ids differ between runs, so replace every *id value by its first-seen ordinal per key.
 */
function normalizeIds(mixed $node, array &$seen = []): mixed
{
    if (!is_array($node)) {
        return $node;
    }

    foreach ($node as $key => $value) {
        if (is_string($key) && ($key === 'id' || str_ends_with($key, '_id')) && is_int($value)) {
            $seen[$key] ??= [];
            $seen[$key][$value] ??= count($seen[$key]) + 1;
            $node[$key] = $seen[$key][$value];
        } else {
            $node[$key] = normalizeIds($value, $seen);
        }
    }

    return $node;
}

function buildBudgetChartFixture(): array
{
    Carbon::setTestNow('2026-06-15 12:00:00');

    $user = User::factory()->create(['end_date' => '2026-12-31']);
    $usd = Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true, 'auto_update' => false]);
    $eur = Currency::factory()->for($user)->fromIsoCodes(['EUR'])->create(['base' => null, 'auto_update' => false]);
    CurrencyRate::factory()->betweenCurrencies($eur, $usd)->create(['date' => '2026-01-10', 'rate' => 1.1]);
    CurrencyRate::factory()->betweenCurrencies($eur, $usd)->create(['date' => '2026-04-10', 'rate' => 1.2]);

    $parent = Category::factory()->for($user)->create(['name' => 'Food', 'parent_id' => null]);
    $child = Category::factory()->for($user)->create(['name' => 'Groceries', 'parent_id' => $parent->id]);
    $other = Category::factory()->for($user)->create(['name' => 'Other', 'parent_id' => null]);

    AccountGroup::factory()->for($user)->create();
    $account = AccountEntity::factory()->for($user)
        ->for(Account::factory()->withUser($user)->state(['currency_id' => $usd->id]), 'config')
        ->create(['name' => 'Checking']);

    foreach ([['2026-01-05', 100, $usd, $child], ['2026-01-20', 50, $eur, $child], ['2026-03-02', 70, $usd, $parent],
        ['2026-05-09', 30, $eur, $other], ['2026-05-10', 12.5, $usd, $child]] as [$date, $amount, $currency, $category]) {
        $transaction = Transaction::factory()->for($user)->withdrawal($user)->create(['date' => $date, 'schedule' => false]);
        // The factory derives currency_id from the account, so set it afterwards.
        $transaction->forceFill(['currency_id' => $currency->id])->save();
        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->create(['category_id' => $category->id, 'amount' => $amount]);
    }

    $deposit = Transaction::factory()->for($user)->deposit($user)->create(['date' => '2026-02-01', 'schedule' => false]);
    $deposit->forceFill(['currency_id' => $usd->id])->save();
    $deposit->transactionItems()->delete();
    $deposit->transactionItems()->create(['category_id' => $child->id, 'amount' => 5]);

    $schedule = Transaction::factory()->for($user)->withdrawal_schedule($user)->create();
    $schedule->forceFill(["currency_id" => $eur->id])->save();
    $schedule->transactionItems()->delete();
    $schedule->transactionItems()->createMany([
        ['category_id' => $child->id, 'amount' => 20],
        ['category_id' => $other->id, 'amount' => 10],
    ]);
    $schedule->transactionSchedule->update([
        'start_date' => '2026-05-01', 'next_date' => '2026-07-01', 'active' => true,
        'end_date' => null, 'count' => null, 'interval' => 1, 'frequency' => 'MONTHLY', 'inflation' => 4.0,
    ]);

    Budget::factory()->create([
        'user_id' => $user->id, 'category_id' => $parent->id, 'account_id' => null,
        'transaction_type' => 'withdrawal', 'amount' => 200, 'frequency' => 'MONTHLY', 'interval' => 1,
        'start_date' => '2026-01-01', 'end_date' => null, 'count' => null, 'inflation' => 3.0, 'active' => true,
    ]);
    Budget::factory()->create([
        'user_id' => $user->id, 'category_id' => $child->id, 'account_id' => $account->id,
        'transaction_type' => 'withdrawal', 'amount' => 90, 'frequency' => 'MONTHLY', 'interval' => 3,
        'start_date' => '2026-02-01', 'end_date' => null, 'count' => null, 'inflation' => null, 'active' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    return [$parent, $other, $account, $child];
}

it('keeps the budgetChart response unchanged', function (string $scenario) {
    [$parent, $other, $account] = buildBudgetChartFixture();

    $query = match ($scenario) {
        'parent+other' => ['categories' => [$parent->id, $other->id]],
        'selected-account' => ['categories' => [$parent->id], 'accountSelection' => 'selected', 'accountEntity' => $account->id],
        'no-account' => ['categories' => [$parent->id], 'accountSelection' => 'none'],
        'no-categories' => [],
    };

    $json = $this->getJson(route('api.v1.reports.budget-chart', $query))->assertOk()->json();
    $actual = json_encode(normalizeIds($json), JSON_PRETTY_PRINT);

    $path = base_path("tests/Fixtures/budget_chart_{$scenario}.json");
    if (getenv('UPDATE_BUDGET_CHART_GOLDEN') === '1') {
        file_put_contents($path, $actual . "\n");
    }

    expect($actual . "\n")->toBe(file_get_contents($path));
})->after(fn () => Carbon::setTestNow())->with(['parent+other', 'selected-account', 'no-account', 'no-categories']);

it('aggregates per category exactly as chart() does for each category', function () {
    [$parent, $other, , $child] = buildBudgetChartFixture();
    $user = auth()->user();
    $service = app(BudgetChartService::class);
    $aggregate = $service->aggregateByCategory($user)['categories'];

    // chart() sums whatever categories it is given (the controller resolves children first).
    $cases = [
        [[$child], [$child->id]],
        [[$other], [$other->id]],
        [[$parent, $child], [$parent->id, $child->id]],
    ];

    foreach ($cases as [$categories, $ids]) {
        foreach ($service->chart($user, collect($categories))['chartData'] as $entry) {
            $period = $entry['period']->format('Y-m-01');
            foreach (['actual', 'budget', 'forecast'] as $series) {
                $sum = null;
                foreach ($ids as $id) {
                    $value = $aggregate[$id][$period][$series] ?? null;
                    $sum = $value === null ? $sum : ($sum ?? Brick\Math\BigDecimal::zero())->plus($value);
                }
                expect($sum?->toFloat() ?? ($series === 'actual' ? null : 0.0))
                    ->toEqualWithDelta($entry[$series] ?? 0.0, 0.0001, "{$period} {$series} for " . implode(',', $ids));
            }
        }
    }
})->after(fn () => Carbon::setTestNow());
