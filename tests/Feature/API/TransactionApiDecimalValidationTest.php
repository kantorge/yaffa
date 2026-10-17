<?php

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
 * Decimal range and scale validation of the transaction store endpoints. Every amount rule
 * mirrors its column's DECIMAL(precision, scale): values beyond the range are rejected, and so
 * are values with more fractional digits than the scale (the `decimal:0,N` rule, precision
 * improvements specification.md FR-8), instead of reaching MoneyCast::set()'s silent HalfUp
 * rounding. Magnitude alone doesn't catch the scale cases.
 */

uses(RefreshDatabase::class);

$buyConfig = ['price' => 10, 'quantity' => 1, 'commission' => 0, 'tax' => 0];

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

function decimalValidationInvestmentPayload(User $user, string $transactionType, array $config): array
{
    $currency = Currency::factory()->for($user)->create();
    $investment = Investment::factory()->create([
        'user_id' => $user->id,
        'currency_id' => $currency->id,
        'investment_group_id' => InvestmentGroup::factory()->for($user)->create()->id,
    ]);
    $account = AccountEntity::factory()->asAccount($user, ['currency_id' => $currency->id])->create();

    return [
        'action' => 'create',
        'transaction_type' => $transactionType,
        'config_type' => 'investment',
        'date' => now()->format('Y-m-d'),
        'reconciled' => false,
        'schedule' => false,
        'budget' => false,
        'config' => ['account_id' => $account->id, 'investment_id' => $investment->id, ...$config],
    ];
}

function decimalValidationStandardPayload(User $user, mixed $amount, mixed $itemAmount): array
{
    return [
        'action' => 'create',
        'transaction_type' => 'withdrawal',
        'config_type' => 'standard',
        'date' => now()->format('Y-m-d'),
        'reconciled' => false,
        'schedule' => false,
        'budget' => false,
        'config' => [
            'account_from_id' => AccountEntity::factory()->asAccount($user)->create(['active' => true])->id,
            'account_to_id' => AccountEntity::factory()->asPayee($user)->create(['active' => true])->id,
            'amount_from' => $amount,
            'amount_to' => $amount,
        ],
        'items' => [
            ['amount' => $itemAmount, 'category_id' => Category::factory()->for($user)->create(['active' => true])->id, 'tags' => []],
        ],
    ];
}

it('rejects an investment amount outside its column range or scale', function (string $transactionType, array $config, array $errors) {
    $this->postJson(
        route('api.v1.transactions.store-investment'),
        decimalValidationInvestmentPayload($this->user, $transactionType, $config)
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    // Mirrors InvestmentPriceRequest's rule (investment_prices.price shares the column type).
    'price exceeding DECIMAL(20,10)' => ['buy', [...$buyConfig, 'price' => 10000000000], ['config.price']],
    'quantity exceeding DECIMAL(14,4)' => ['buy', [...$buyConfig, 'quantity' => 10000000000], ['config.quantity']],
    'commission exceeding DECIMAL(14,4)' => ['buy', [...$buyConfig, 'commission' => 10000000000], ['config.commission']],
    'tax exceeding DECIMAL(14,4)' => ['buy', [...$buyConfig, 'tax' => 10000000000], ['config.tax']],
    'dividend exceeding DECIMAL(12,4)' => ['dividend', ['dividend' => 100000000], ['config.dividend']],
    // Well within the DECIMAL(20,10) max, but 12 decimal digits.
    'price with excess decimal places' => ['buy', [...$buyConfig, 'price' => '1234.567890123456'], ['config.price']],
    'commission with excess decimal places' => ['buy', [...$buyConfig, 'commission' => '1.23456'], ['config.commission']],
    'dividend with excess decimal places' => ['dividend', ['dividend' => '1.23456'], ['config.dividend']],
]);

it('accepts an investment price within DECIMAL(20,10)', function () use ($buyConfig) {
    // 10 decimal places: exceeds the old DECIMAL(10,4) column's precision but fits the
    // widened DECIMAL(20,10) range and rule.
    $this->postJson(
        route('api.v1.transactions.store-investment'),
        decimalValidationInvestmentPayload($this->user, 'buy', [...$buyConfig, 'price' => '1234.5678901234'])
    )->assertOk();
});

it('rejects a standard amount outside its DECIMAL(12,4) range or scale', function (mixed $amount, mixed $itemAmount, array $errors) {
    $this->postJson(
        route('api.v1.transactions.store-standard'),
        decimalValidationStandardPayload($this->user, $amount, $itemAmount)
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'amount exceeding the range' => [100000000, 10, ['config.amount_from', 'config.amount_to']],
    'item amount exceeding the range' => [10, 100000000, ['items.0.amount']],
    'amounts with excess decimal places' => ['10.12345', '10.12345', ['config.amount_from', 'config.amount_to', 'items.0.amount']],
]);

it('accepts a standard amount at exactly the DECIMAL(12,4) scale', function () {
    $this->postJson(
        route('api.v1.transactions.store-standard'),
        decimalValidationStandardPayload($this->user, '10.1234', '10.1234')
    )->assertOk();
});
