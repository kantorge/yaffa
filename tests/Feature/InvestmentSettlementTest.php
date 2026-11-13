<?php

use App\Casts\MoneyCast;
use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
 * A foreign-currency account can settle into an account in the user's default currency. An investment transaction in such
 * an account can then create the settlement transfer in the same request, moving exactly its cash flow.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->base = Currency::factory()->for($this->user)->create(['iso_code' => 'GBP', 'base' => true]);
    $this->foreign = Currency::factory()->for($this->user)->create(['iso_code' => 'USD']);
    $this->parent = AccountEntity::factory()->asAccount($this->user, ['currency_id' => $this->base->id])->create(['active' => true]);
    $this->child = AccountEntity::factory()->asAccount($this->user, [
        'currency_id' => $this->foreign->id,
        'settlement_account_id' => $this->parent->id,
    ])->create(['active' => true]);
    $this->investment = Investment::factory()->create([
        'user_id' => $this->user->id,
        'currency_id' => $this->foreign->id,
        'investment_group_id' => InvestmentGroup::factory()->for($this->user)->create()->id,
    ]);
});

function settlementPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'action' => 'create',
        'transaction_type' => 'sell',
        'config_type' => 'investment',
        'date' => '2026-11-12',
        'reconciled' => false,
        'schedule' => false,
        'config' => [
            'account_id' => test()->child->id,
            'investment_id' => test()->investment->id,
            'price' => '12.34',
            'quantity' => '1.2345',
            'commission' => null,
            'tax' => null,
        ],
        'settlement' => ['amount' => '11.50'],
    ], $overrides);
}

function accountFormData(AccountEntity $account, array $config = []): array
{
    return [
        'config_type' => 'account',
        'name' => $account->name,
        'active' => 1,
        'config' => [
            'opening_balance' => MoneyCast::toFloat($account->config->opening_balance),
            'account_group_id' => $account->config->account_group_id,
            'currency_id' => $account->config->currency_id,
            ...$config,
        ],
    ];
}

describe('settlement account on the account form', function () {
    it('links a foreign-currency account to an account in the default currency', function () {
        $other = AccountEntity::factory()->asAccount($this->user, ['currency_id' => $this->foreign->id])->create();

        $this->actingAs($this->user)
            ->patch(route('account-entity.update', $other), accountFormData($other, ['settlement_account_id' => $this->parent->id]))
            ->assertRedirect(route('account-entity.index', ['type' => 'account']));

        expect($other->fresh()->config->settlement_account_id)->toBe($this->parent->id);
    });

    it('clears the link when no settlement account is chosen', function () {
        $this->actingAs($this->user)
            ->patch(route('account-entity.update', $this->child), accountFormData($this->child, ['settlement_account_id' => null]))
            ->assertSessionHasNoErrors();

        expect($this->child->fresh()->config->settlement_account_id)->toBeNull();
    });

    it('rejects an invalid settlement account', function (Closure $settlementAccount, Closure $accountCurrency) {
        $account = AccountEntity::factory()->asAccount($this->user, ['currency_id' => $accountCurrency()->id])->create();

        $this->actingAs($this->user)
            ->patch(route('account-entity.update', $account), accountFormData($account, ['settlement_account_id' => $settlementAccount()->id]))
            ->assertSessionHasErrors('config.settlement_account_id');

        expect($account->fresh()->config->settlement_account_id)->toBeNull();
    })->with([
        'settlement account not in the default currency' => [
            fn () => AccountEntity::factory()->asAccount(test()->user, ['currency_id' => test()->foreign->id])->create(),
            fn () => test()->foreign,
        ],
        'account itself in the default currency' => [
            fn () => test()->parent,
            fn () => test()->base,
        ],
        'another user\'s account' => [
            function () {
                $otherUser = User::factory()->create();
                $currency = Currency::factory()->for($otherUser)->create(['base' => true]);

                return AccountEntity::factory()->asAccount($otherUser, ['currency_id' => $currency->id])->create();
            },
            fn () => test()->foreign,
        ],
        'a payee' => [
            fn () => AccountEntity::factory()->asPayee(test()->user)->create(),
            fn () => test()->foreign,
        ],
    ]);

    it('lists only default-currency accounts as settlement options', function () {
        $foreignAccount = AccountEntity::factory()->asAccount($this->user, ['currency_id' => $this->foreign->id])->create();

        $this->actingAs($this->user)
            ->get(route('account-entity.edit', $this->child))
            ->assertOk()
            ->assertViewHas('allSettlementAccounts', fn (array $options) => array_key_exists($this->parent->id, $options)
                && ! array_key_exists($foreignAccount->id, $options)
                && ! array_key_exists($this->child->id, $options));
    });

    it('offers only active accounts, but keeps the one already chosen', function () {
        $inactive = AccountEntity::factory()->asAccount($this->user, ['currency_id' => $this->base->id])->create(['active' => false]);

        $this->actingAs($this->user)
            ->get(route('account-entity.edit', $this->child))
            ->assertViewHas('allSettlementAccounts', fn (array $options) => ! array_key_exists($inactive->id, $options));

        $this->parent->update(['active' => false]);

        $this->actingAs($this->user)
            ->get(route('account-entity.edit', $this->child))
            ->assertViewHas('allSettlementAccounts', fn (array $options) => array_key_exists($this->parent->id, $options)
                && ! array_key_exists($inactive->id, $options));
    });

    it('includes the settlement account in the reference data', function () {
        Sanctum::actingAs($this->user, ['*']);

        $accounts = collect($this->getJson(route('api.v1.reference-data'))->assertOk()->json('accounts'))->keyBy('id');

        expect($accounts[$this->child->id]['settlement_account_id'])->toBe($this->parent->id)
            ->and($accounts[$this->parent->id]['settlement_account_id'])->toBeNull();
    });
});

describe('posting an investment transaction with its settlement', function () {
    beforeEach(fn () => Sanctum::actingAs($this->user, ['*']));

    it('moves exactly the cash flow of a sale to the settlement account', function () {
        $response = $this->postJson(route('api.v1.transactions.store-investment'), settlementPayload())->assertOk();

        $sale = Transaction::findOrFail($response->json('transaction.id'));
        $transfer = Transaction::with('config')->findOrFail($response->json('settlement_transaction.id'));

        // 12.34 * 1.2345 = 15.233730, stored at 4 dp
        expect((string) $sale->cashflow_value->getAmount())->toBe('15.2337')
            ->and($transfer->transaction_type->value)->toBe('transfer')
            ->and($transfer->settles_transaction_id)->toBe($sale->id)
            ->and($transfer->date->toDateString())->toBe('2026-11-12')
            ->and($transfer->config->account_from_id)->toBe($this->child->id)
            ->and($transfer->config->account_to_id)->toBe($this->parent->id)
            ->and((string) $transfer->config->amount_from->getAmount())->toBe('15.2337')
            ->and((string) $transfer->config->amount_to->getAmount())->toBe('11.5000');
    });

    it('settles a dividend net of tax and commission', function () {
        $response = $this->postJson(route('api.v1.transactions.store-investment'), settlementPayload([
            'transaction_type' => 'dividend',
            'config' => ['price' => null, 'quantity' => null, 'dividend' => '10', 'tax' => '1.5', 'commission' => '0.25'],
            'settlement' => ['amount' => '6.20'],
        ]))->assertOk();

        $transfer = Transaction::with('config')->findOrFail($response->json('settlement_transaction.id'));

        expect($transfer->config->account_from_id)->toBe($this->child->id)
            ->and((string) $transfer->config->amount_from->getAmount())->toBe('8.2500');
    });

    it('funds a purchase from the settlement account', function () {
        $response = $this->postJson(route('api.v1.transactions.store-investment'), settlementPayload([
            'transaction_type' => 'buy',
            'config' => ['commission' => '1'],
            'settlement' => ['amount' => '12.40'],
        ]))->assertOk();

        $transfer = Transaction::with('config')->findOrFail($response->json('settlement_transaction.id'));

        expect($transfer->config->account_from_id)->toBe($this->parent->id)
            ->and($transfer->config->account_to_id)->toBe($this->child->id)
            ->and((string) $transfer->config->amount_from->getAmount())->toBe('12.4000')
            ->and((string) $transfer->config->amount_to->getAmount())->toBe('16.2337');
    });

    it('creates no transfer without a settlement amount', function () {
        $response = $this->postJson(route('api.v1.transactions.store-investment'), settlementPayload(['settlement' => null]))->assertOk();

        expect($response->json('settlement_transaction'))->toBeNull()
            ->and(Transaction::count())->toBe(1);
    });

    it('rejects a settlement that cannot be recorded, and saves nothing', function (array $overrides) {
        $this->postJson(route('api.v1.transactions.store-investment'), settlementPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settlement.amount');

        expect(Transaction::count())->toBe(0);
    })->with([
        'scheduled transaction' => [['schedule' => true, 'schedule_config' => ['start_date' => '2026-11-12', 'frequency' => 'MONTHLY', 'interval' => 1]]],
        'type without cash flow' => [['transaction_type' => 'add_shares', 'config' => ['price' => null]]],
        'zero amount' => [['settlement' => ['amount' => '0']]],
        'more than 4 decimals' => [['settlement' => ['amount' => '1.23456']]],
    ]);

    it('rejects a settlement for an account without a settlement account', function () {
        $this->child->config->update(['settlement_account_id' => null]);

        $this->postJson(route('api.v1.transactions.store-investment'), settlementPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settlement.amount');

        expect(Transaction::count())->toBe(0);
    });

    it('does not accept a settlement on a standard transaction', function () {
        $this->postJson(route('api.v1.transactions.store-standard'), [
            'action' => 'create',
            'transaction_type' => 'transfer',
            'config_type' => 'standard',
            'date' => '2026-11-12',
            'reconciled' => false,
            'schedule' => false,
            'config' => [
                'account_from_id' => $this->child->id,
                'account_to_id' => $this->parent->id,
                'amount_from' => '10',
                'amount_to' => '8',
            ],
            'settlement' => ['amount' => '8'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settlement');
    });

    it('keeps the transfer when the investment transaction is deleted', function () {
        $response = $this->postJson(route('api.v1.transactions.store-investment'), settlementPayload())->assertOk();
        $sale = Transaction::findOrFail($response->json('transaction.id'));
        $transferId = $response->json('settlement_transaction.id');

        $sale->loadDetails();
        expect($sale->settlementTransfer?->id)->toBe($transferId);

        $sale->delete();

        expect(Transaction::find($transferId)?->settles_transaction_id)->toBeNull();
    });
});
