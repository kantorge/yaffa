<?php

use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * GET /api/v1/reports/budget-suggestions (budget-drift-suggestions phase 2b).
 * "Today" is 2026-12-03, so data_through defaults to 2026-11.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-12-03 12:00:00');
    $this->user = User::factory()->create(['end_date' => '2028-12-31']);
    $currency = Currency::factory()->for($this->user)->fromIsoCodes(['USD'])->create(['base' => true, 'auto_update' => false]);
    AccountGroup::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user, ['read']);

    // The screenshot case: budget -24k since 2025, actuals -27.5k for 11 months of 2026.
    $this->category = Category::factory()->for($this->user)->create(['default_aggregation' => 'month', 'active' => true]);
    $this->budget = Budget::factory()->create([
        'user_id' => $this->user->id, 'category_id' => $this->category->id, 'account_id' => null,
        'transaction_type' => 'withdrawal', 'amount' => 24000, 'frequency' => 'MONTHLY', 'interval' => 1,
        'start_date' => '2025-01-01', 'end_date' => null, 'count' => null, 'inflation' => null, 'active' => true,
    ]);
    for ($m = 1; $m <= 11; $m++) {
        $transaction = Transaction::factory()->for($this->user)->withdrawal($this->user)
            ->create(['date' => sprintf('2026-%02d-10', $m), 'schedule' => false]);
        $transaction->forceFill(['currency_id' => $currency->id])->save();
        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->create(['category_id' => $this->category->id, 'amount' => 27500]);
    }
});

afterEach(fn () => Carbon::setTestNow());

it('returns the response contract with a series for explicit categories', function () {
    $response = $this->getJson(route('api.v1.reports.budget-suggestions', ['category_ids' => [$this->category->id]]))
        ->assertOk()
        ->assertJsonStructure(['run' => ['computed_at', 'scope', 'data_through', 'sensitivity', 'bias', 'tolerance', 'detector_version'], 'units', 'series']);

    expect($response->json('run.scope'))->toBe('selected')
        ->and($response->json('run.data_through'))->toBe('2026-11')
        ->and($response->json('units.0.verdict'))->toBe('suggestion')
        ->and($response->json('units.0.suggestion.proposal_type'))->toBe('replace')
        ->and($response->json('units.0.suggestion.budget_id'))->toBe($this->budget->id)
        ->and($response->json('series'))->not->toBeEmpty();
});

it('returns no series for review all', function () {
    $response = $this->getJson(route('api.v1.reports.budget-suggestions', ['all' => 1]))->assertOk();

    expect($response->json('run.scope'))->toBe('all')
        ->and($response->json('series'))->toBeNull();
});

it('rejects invalid input', function (array $query) {
    $this->getJson(route('api.v1.reports.budget-suggestions', $query))->assertUnprocessable();
})->with([
    'neither categories nor all' => [[]],
    'both categories and all' => [fn () => ['all' => 1, 'category_ids' => [test()->category->id]]],
    'empty category id' => [['category_ids' => ['']]],
    'bad month' => [['all' => 1, 'data_through' => '2026-13']],
    'bad sensitivity' => [['all' => 1, 'sensitivity' => 'extreme']],
    'bad bias' => [['all' => 1, 'bias' => 'optimistic']],
]);

it('rejects a category of another user', function () {
    $foreign = Category::factory()->for(User::factory()->create())->create();

    $this->getJson(route('api.v1.reports.budget-suggestions', ['category_ids' => [$foreign->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_ids.0');
});

it('gives identical suggestion keys for identical runs', function () {
    $query = ['category_ids' => [$this->category->id]];
    $first = $this->getJson(route('api.v1.reports.budget-suggestions', $query))->json('units.0.suggestion.key');
    $second = $this->getJson(route('api.v1.reports.budget-suggestions', $query))->json('units.0.suggestion.key');

    expect($first)->not->toBeNull()->and($second)->toBe($first);
});

it('writes nothing', function () {
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|alter|create|drop|truncate|replace)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    $this->getJson(route('api.v1.reports.budget-suggestions', ['category_ids' => [$this->category->id]]))->assertOk();
    $this->getJson(route('api.v1.reports.budget-suggestions', ['all' => 1]))->assertOk();

    expect($writes)->toBe([]);
});

it('matches the recommended series with a saved and re-projected budget', function () {
    $query = ['category_ids' => [$this->category->id]];
    $before = $this->getJson(route('api.v1.reports.budget-suggestions', $query));
    $suggestion = $before->json('units.0.suggestion');
    $recommended = collect($before->json('series'))->pluck('recommended', 'period');

    // Apply the proposal like the replace flow does: end the old row, start the new one.
    $this->budget->update(['end_date' => Carbon::parse($suggestion['effective_date'])->subDay()->toDateString()]);
    Budget::factory()->create([
        'user_id' => $this->user->id, 'category_id' => $this->category->id, 'account_id' => null,
        'transaction_type' => 'withdrawal', 'amount' => abs($suggestion['suggested_amount']),
        'frequency' => $suggestion['cadence']['frequency'], 'interval' => $suggestion['cadence']['interval'],
        'start_date' => $suggestion['effective_date'], 'end_date' => null, 'count' => null, 'inflation' => null, 'active' => true,
    ]);

    // The saved row now contributes to the chart's own budget + forecast for the category.
    $periods = app(App\Services\BudgetChartService::class)->aggregateByCategory($this->user)['categories'][$this->category->id];

    foreach ($recommended as $period => $value) {
        $saved = $periods[$period]['budget']->plus($periods[$period]['forecast'])->toFloat();
        expect($saved)->toEqualWithDelta($value, 0.01, "period {$period}");
    }
});

it('returns 422 for a user without any currency', function () {
    $other = User::factory()->create();
    Sanctum::actingAs($other, ['read']);

    $this->getJson(route('api.v1.reports.budget-suggestions', ['all' => 1]))->assertUnprocessable();
});
