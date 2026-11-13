<?php

use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/** budget-suggestions:review (budget-drift-suggestions phase 2b). "Today" is 2026-12-03. */
beforeEach(function () {
    Carbon::setTestNow('2026-12-03 12:00:00');
    $this->user = User::factory()->create(['end_date' => '2028-12-31']);
    $currency = Currency::factory()->for($this->user)->fromIsoCodes(['USD'])->create(['base' => true, 'auto_update' => false]);
    AccountGroup::factory()->for($this->user)->create();

    $category = Category::factory()->for($this->user)->create(['name' => 'Groceries', 'default_aggregation' => 'month', 'active' => true]);
    Budget::factory()->create([
        'user_id' => $this->user->id, 'category_id' => $category->id, 'account_id' => null,
        'transaction_type' => 'withdrawal', 'amount' => 24000, 'frequency' => 'MONTHLY', 'interval' => 1,
        'start_date' => '2024-01-01', 'end_date' => null, 'count' => null, 'inflation' => null, 'active' => true,
    ]);
    // 24 months of -27.5k actuals, so every backtest month has history.
    for ($m = Carbon::parse('2024-12-01'); $m->lte(Carbon::parse('2026-11-01')); $m->addMonth()) {
        $transaction = Transaction::factory()->for($this->user)->withdrawal($this->user)
            ->create(['date' => $m->format('Y-m') . '-10', 'schedule' => false]);
        $transaction->forceFill(['currency_id' => $currency->id])->save();
        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->create(['category_id' => $category->id, 'amount' => 27500]);
    }
});

afterEach(fn () => Carbon::setTestNow());

it('prints the same result as the endpoint with --json', function () {
    Illuminate\Support\Facades\Artisan::call('budget-suggestions:review', ['user' => $this->user->id, '--json' => true]);
    $command = json_decode(Illuminate\Support\Facades\Artisan::output(), true);

    Sanctum::actingAs($this->user, ['read']);
    $endpoint = $this->getJson(route('api.v1.reports.budget-suggestions', ['all' => 1]))->json();

    unset($command['run']['computed_at'], $endpoint['run']['computed_at']);
    expect($command)->toEqual($endpoint)
        ->and(collect($command['units'])->pluck('verdict')->all())->toContain('suggestion');
});

it('lists suggestions in the table', function () {
    $this->artisan('budget-suggestions:review', ['user' => $this->user->id])
        ->expectsOutputToContain('Groceries')
        ->expectsOutputToContain('Elapsed')
        ->assertSuccessful();
});

it('fails on an unknown user or an invalid option', function (array $args) {
    $this->artisan('budget-suggestions:review', $args)->assertFailed();
})->with([
    'user' => [['user' => 999999]],
    'sensitivity' => [fn () => ['user' => test()->user->id, '--sensitivity' => 'extreme']],
    'bias' => [fn () => ['user' => test()->user->id, '--bias' => 'x']],
    'through with backtest' => [fn () => ['user' => test()->user->id, '--through' => '2026-01', '--backtest' => true]],
    'through' => [fn () => ['user' => test()->user->id, '--through' => '2026-13']],
]);

it('backtests the last 24 months and counts suggestions per year', function () {
    Illuminate\Support\Facades\Artisan::call('budget-suggestions:review', ['user' => $this->user->id, '--backtest' => true, '--json' => true]);
    $rows = json_decode(Illuminate\Support\Facades\Artisan::output(), true);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['category_name'])->toBe('Groceries')
        ->and(array_keys($rows[0]['suggestions_per_year']))->each->toBeInt()
        ->and(array_sum($rows[0]['suggestions_per_year']))->toBeGreaterThan(0);
});
