<?php

use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvestmentService;
use Laravel\Sanctum\Sanctum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function accountFilterSetup(): array
{
    $user = User::factory()->create();
    $currency = Currency::factory()->for($user)->create();
    $investment = Investment::factory()->for($user)->create([
        'currency_id' => $currency->id,
        'investment_group_id' => InvestmentGroup::factory()->for($user)->create()->id,
    ]);
    $accounts = [];
    foreach ([2, 5] as $quantity) {
        $account = AccountEntity::factory()->asAccount($user, ['currency_id' => $currency->id])->create();
        Transaction::factory()->for($user)->buy($user, [
            'account_id' => $account->id,
            'investment_id' => $investment->id,
            'quantity' => $quantity,
        ])->create();
        $accounts[] = $account;
    }

    return [$user, $investment, $accounts];
}

it('scopes display-data to one account', function () {
    [$user, $investment, [$a, $b]] = accountFilterSetup();
    Sanctum::actingAs($user, ['*']);

    $all = $this->getJson(route('api.v1.investments.display-data', $investment))->assertOk();
    expect($all->json('transactions'))->toHaveCount(2)
        ->and((float) $all->json('investment.current_quantity'))->toBe(7.0)
        ->and($all->json('accounts'))->toHaveCount(2)
        ->and(collect($all->json('accounts'))->pluck('quantity')->sort()->values()->all())->toEqual([2, 5]);

    $filtered = $this->getJson(route('api.v1.investments.display-data', [$investment, 'account' => $b->id]))->assertOk();
    expect($filtered->json('transactions'))->toHaveCount(1)
        ->and((float) $filtered->json('investment.current_quantity'))->toBe(5.0)
        ->and(last($filtered->json('quantities'))['quantity'])->toEqual(5)
        ->and($filtered->json('accounts'))->toHaveCount(2);
});

it('passes the selected account to the show page', function () {
    [$user, $investment, [$a]] = accountFilterSetup();

    $this->actingAs($user)->get(route('investments.show', [$investment, 'account' => $a->id]))
        ->assertOk()
        ->assertViewHas('selectedAccountId', $a->id)
        ->assertViewHas('transactions', fn ($t) => $t->count() === 1);
});

it('rejects foreign and non-account entities', function () {
    [$user, $investment] = accountFilterSetup();
    $foreign = AccountEntity::factory()->asAccount(User::factory()->create())->create();
    $payee = AccountEntity::factory()->asPayee($user)->create();

    foreach ([$foreign->id, $payee->id, 999999] as $id) {
        $this->actingAs($user)->get(route('investments.show', [$investment, 'account' => $id]))->assertNotFound();
    }
});

it('filters the quantity history by account', function () {
    [, $investment, [, $b]] = accountFilterSetup();
    $service = app(InvestmentService::class);

    expect(last($service->enrichInvestmentWithQuantityHistory($investment)->quantities)['quantity'])->toEqual(7)
        ->and(last($service->enrichInvestmentWithQuantityHistory($investment, $b)->quantities)['quantity'])->toEqual(5);
});
