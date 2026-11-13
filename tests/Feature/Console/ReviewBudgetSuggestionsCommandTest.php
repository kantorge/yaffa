<?php

use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetSuggestionService;
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

it('counts suggestions per calendar year and flags more than two in a year', function () {
    // Months suggested per category, over the 24 runs (2024-12 to 2026-11).
    $suggested = [
        1 => ['2025-01', '2025-02'],                                     // 2 in 2025: not flagged
        2 => ['2026-01'],                                                // 1 in 2026: not flagged
        3 => ['2025-03', '2025-04', '2025-05'],                          // 3 in 2025: flagged
        4 => ['2024-12', '2025-06', '2025-07', '2025-08'],               // 1 in 2024, 3 in 2025: flagged
        5 => array_map(fn ($m) => sprintf('2026-%02d', $m), range(1, 11)), // 11 in 2026: flagged
    ];

    $this->mock(BudgetSuggestionService::class)
        ->shouldReceive('run')->times(24)
        ->andReturnUsing(fn ($user, $ids, $through) => [
            'run' => ['data_through' => $through],
            'series' => null,
            'units' => array_map(fn ($id) => [
                'category_id' => $id,
                'category_name' => "Category {$id}",
                'verdict' => in_array($through, $suggested[$id], true) ? 'suggestion' : 'no_drift',
                'suggestion' => null,
            ], array_keys($suggested)),
        ]);

    Illuminate\Support\Facades\Artisan::call('budget-suggestions:review', ['user' => $this->user->id, '--backtest' => true, '--json' => true]);
    $rows = collect(json_decode(Illuminate\Support\Facades\Artisan::output(), true))->keyBy('category_id');

    expect($rows)->toHaveCount(5)
        ->and($rows[1]['suggestions_per_year'])->toEqual([2025 => 2])
        ->and($rows[2]['suggestions_per_year'])->toEqual([2026 => 1])
        ->and($rows[3]['suggestions_per_year'])->toEqual([2025 => 3])
        ->and($rows[4]['suggestions_per_year'])->toEqual([2024 => 1, 2025 => 3])
        ->and($rows[5]['suggestions_per_year'])->toEqual([2026 => 11])
        ->and($rows->where('flagged', true)->keys()->sort()->values()->all())->toBe([3, 4, 5]);
});

it('shows the impact columns and follows the service order', function () {
    Illuminate\Support\Facades\Artisan::call('budget-suggestions:review', ['user' => $this->user->id]);
    $output = Illuminate\Support\Facades\Artisan::output();

    expect($output)->toContain('Monthly impact')
        ->toContain('% of monthly expenses')
        ->toContain('-3500')
        ->toContain('12.73%'); // 3,500 of 27,500 average monthly expenses
});
