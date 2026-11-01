<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['language' => 'en']);
    Currency::factory()->for($this->user)->fromIsoCodes(['USD'])->create(['base' => true]);
    Sanctum::actingAs($this->user, ['*']);

    $account = AccountEntity::factory()
        ->for($this->user)
        ->for(Account::factory()->withUser($this->user)->create(['opening_balance' => 0]), 'config')
        ->create();
    $investment = Investment::factory()->withUser($this->user)->create();

    foreach ([
        [TransactionType::BUY, '-1000'],
        [TransactionType::SELL, '1500'],
        [TransactionType::DIVIDEND, '20'],
    ] as [$type, $cashflow]) {
        Transaction::factory()
            ->for($this->user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account->id,
                    'investment_id' => $investment->id,
                ]),
                'config'
            )
            ->make([
                'date' => '2025-01-10',
                'schedule' => false,
                'config_type' => 'investment',
                'transaction_type' => $type->value,
                'cashflow_value' => $cashflow,
            ])
            ->save();
    }

    $this->fetch = fn (array $query) => collect(
        $this->getJson(route('api.v1.reports.waterfall', [
            'transactionType' => 'all',
            'dataType' => 'result',
            'year' => 2025,
            'month' => 1,
        ] + $query))->assertOk()->json('chartData')
    )->pluck('value', 'category');
});

it('returns separate purchase and sale buckets for the trades group', function () {
    $data = ($this->fetch)(['include' => ['trades']]);

    expect($data->all())->toEqual(['Investment purchases' => -1000.0, 'Investment sales' => 1500.0]);
});

it('combines groups and excludes trades from the legacy all type', function () {
    expect(($this->fetch)(['include' => ['income', 'trades']])->keys()->sort()->values()->all())
        ->toEqual(['Investment income', 'Investment purchases', 'Investment sales']);

    expect(($this->fetch)([])->keys()->all())->toEqual(['Investment income']);
});

it('rejects an non-array or unknown include selection', function () {
    foreach (['', ['bogus']] as $include) {
        $this->getJson(route('api.v1.reports.waterfall', [
            'transactionType' => 'all', 'dataType' => 'result', 'year' => 2025, 'include' => $include,
        ]))->assertUnprocessable();
    }
});
