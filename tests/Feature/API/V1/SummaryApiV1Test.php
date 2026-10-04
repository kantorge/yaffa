<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

it('returns balances, month flows and budget status as decimal strings in the base currency', function () {
    $withdrawal = Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id, 'date' => now()->toDateString()]);
    $deposit = Transaction::factory()->deposit($this->user)->create(['user_id' => $this->user->id, 'date' => now()->toDateString()]);
    $old = Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id, 'date' => now()->subMonths(3)->toDateString()]);

    $response = $this->getJson(route('api.v1.summary'))->assertOk();

    $response->assertJsonPath('result', 'success')
        ->assertJsonPath('base_currency', $this->user->currencies()->orderBy('id')->value('iso_code'))
        ->assertJsonStructure([
            'accounts' => [['id', 'name', 'active', 'currency', 'balance']],
            'total_balance',
            'month' => ['from', 'to', 'income', 'expense'],
            'budget' => ['budgeted', 'spent', 'remaining'],
        ]);

    foreach (['total_balance', 'month.income', 'month.expense', 'budget.budgeted', 'budget.spent', 'budget.remaining'] as $path) {
        expect($response->json($path))->toBeString()->toMatch('/^-?\d+(\.\d+)?$/');
    }

    // Only this month's transactions count, each by its absolute cashflow
    $expected = fn (Transaction $t) => abs((float) (string) $t->cashflow_value->getAmount());
    expect((float) $response->json('month.expense'))->toEqualWithDelta($expected($withdrawal), 0.01);
    expect((float) $response->json('month.income'))->toEqualWithDelta($expected($deposit), 0.01);
    expect($old->date->isSameMonth(now()))->toBeFalse();
});

it('counts this month\'s spending in budgeted categories against the budget', function () {
    $category = Category::factory()->for($this->user)->create();
    Budget::factory()->for($this->user)->create([
        'category_id' => $category->id,
        'transaction_type' => 'withdrawal',
        'start_date' => now()->startOfMonth()->toDateString(),
        'frequency' => 'MONTHLY',
        'interval' => 1,
        'amount' => 500,
        'active' => true,
    ]);

    $response = $this->getJson(route('api.v1.summary'))->assertOk();

    expect((float) $response->json('budget.budgeted'))->toBe(500.0);
    expect((float) $response->json('budget.remaining'))->toEqualWithDelta(500.0 - (float) $response->json('budget.spent'), 0.01);
});
