<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('includes the past periods of a closed budget in the chart', function () {
    $user = User::factory()->create(['end_date' => now()->addMonths(2)->endOfMonth()]);
    Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);
    $category = Category::factory()->for($user)->create();

    $budget = Budget::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'account_id' => null,
        'transaction_type' => 'withdrawal',
        'amount' => 500,
        'frequency' => 'MONTHLY',
        'interval' => 1,
        'start_date' => now()->subYears(2)->startOfMonth(),
        'end_date' => now()->subYear()->startOfMonth(),
        'count' => null,
        'inflation' => null,
    ]);
    expect($budget->fresh()->active)->toBeFalse();

    Sanctum::actingAs($user, ['*']);

    $periods = collect(
        test()->getJson(route('api.v1.reports.budget-chart', ['categories' => [$category->id]]))
            ->assertOk()
            ->json('chartData')
    );

    $past = $periods->first(fn ($entry) => str_starts_with($entry['period'], now()->subYear()->startOfMonth()->format('Y-m')));
    expect($past)->not->toBeNull()
        ->and($past['budget'])->toEqualWithDelta(-500.0, 0.001)
        ->and($past['budgetBreakdown'])->toHaveCount(1);
    expect($periods->first(fn ($entry) => str_starts_with($entry['period'], now()->format('Y-m'))))->toBeNull();
});
