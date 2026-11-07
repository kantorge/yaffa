<?php

use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->createForUser($this->user, Currency::class);
    $this->account = AccountEntity::factory()->asAccount($this->user)->create();
});

function holding($test, string $type, Investment $investment, string $quantity): void
{
    Transaction::factory()->{$type}($test->user, [
        'account_id' => $test->account->id,
        'investment_id' => $investment->id,
        'quantity' => $quantity,
        'price' => 10,
    ])->create();
}

function newInvestment($test): Investment
{
    return Investment::factory()->for($test->user)->withUser($test->user)->create();
}

it('lists only positive holdings with value math, sorted by value, null price last', function () {
    Sanctum::actingAs($this->user, ['*']);
    $small = newInvestment($this);
    $big = newInvestment($this);
    $unpriced = newInvestment($this);
    $sold = newInvestment($this);
    $negative = newInvestment($this);

    foreach ([$small, $big, $unpriced] as $i) {
        holding($this, 'buy', $i, '2.5');
    }
    holding($this, 'buy', $sold, '3');
    holding($this, 'sell', $sold, '3');
    holding($this, 'sell', $negative, '1');

    InvestmentPrice::query()->whereIn('investment_id', [$small->id, $big->id])->delete();
    // Price from stored price table (newer than the transaction price date)
    InvestmentPrice::factory()->create(['investment_id' => $small->id, 'date' => now()->addDay()->toDateString(), 'price' => 2]);
    InvestmentPrice::factory()->create(['investment_id' => $big->id, 'date' => now()->addDay()->toDateString(), 'price' => 100]);
    Transaction::query()->whereHasMorph('config', [App\Models\TransactionDetailInvestment::class], fn ($q) => $q->where('investment_id', $unpriced->id))
        ->get()->each(fn ($t) => $t->config->update(['price' => null]));

    $response = $this->getJson(route('api.v1.accounts.investments', $this->account->id))->assertOk();

    expect(collect($response->json())->pluck('investment_id')->all())
        ->toBe([$big->id, $small->id, $unpriced->id]);
    expect($response->json('0.value'))->toBeNumeric()->and((float) $response->json('0.value'))->toBe(250.0)
        ->and((float) $response->json('1.value'))->toBe(5.0)
        ->and($response->json('2.value'))->toBeNull()
        ->and($response->json('2.price'))->toBeNull();
});

it('denies other users and rejects non-account entities', function () {
    $other = User::factory()->create();
    Sanctum::actingAs($other, ['*']);
    $this->getJson(route('api.v1.accounts.investments', $this->account->id))->assertForbidden();

    Sanctum::actingAs($this->user, ['*']);
    $payee = AccountEntity::factory()->asPayee($this->user)->create();
    $this->getJson(route('api.v1.accounts.investments', $payee->id))->assertStatus(400);
});

it('renders the investments card container on the account page', function () {
    $this->actingAs($this->user)
        ->get(route('account-entity.show', ['type' => 'account', 'account_entity' => $this->account->id]))
        ->assertOk()
        ->assertSee('investmentHoldingsCard');
});
