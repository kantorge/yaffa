<?php

namespace Tests\Feature\API;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountMonthlySummary;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiCashflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $baseCurrency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->baseCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['USD'])
            ->create(['base' => true]);
    }

    /**
     * Create an AccountEntity of type Account for the given user and currency.
     */
    private function createAccount(Currency $currency): AccountEntity
    {
        return AccountEntity::factory()
            ->for($this->user)
            ->for(
                Account::factory()->withUser($this->user)->create([
                    'currency_id' => $currency->id,
                    'opening_balance' => 0,
                ]),
                'config'
            )
            ->create();
    }

    // ===== AUTH TESTS =====

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson(route('api.v1.reports.cashflow'));
        $this->assertUserNotAuthorized($response);
    }

    // ===== HAPPY PATH TESTS =====

    /**
     * When the account is denominated in the base currency, amounts are returned unconverted.
     */
    public function test_base_currency_account_amounts_are_not_converted(): void
    {
        Sanctum::actingAs($this->user);

        $account = $this->createAccount($this->baseCurrency);

        AccountMonthlySummary::create([
            'date' => '2025-01-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 500.00,
        ]);

        $response = $this->getJson(route('api.v1.reports.cashflow'));

        $response->assertOk();
        $chartData = $response->json('chartData');
        $this->assertNotEmpty($chartData);
        $this->assertEquals(500.00, $chartData[0]['account_balance']);
    }

    /**
     * When the account is in a foreign currency AND a rate exists for the period, the amount
     * must be multiplied by that rate to yield the base-currency value.
     *
     * This is the core conversion path. Using a large rate (150) makes an error obvious.
     */
    public function test_foreign_currency_account_amounts_are_converted_using_closest_rate(): void
    {
        Sanctum::actingAs($this->user);

        $foreignCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['HUF'])
            ->create(['base' => null]);

        // Rate: 1 HUF = 0.0025 USD (i.e. 400 HUF per USD)
        CurrencyRate::factory()
            ->betweenCurrencies($foreignCurrency, $this->baseCurrency)
            ->create(['date' => '2025-01-15', 'rate' => 0.0025]);

        $account = $this->createAccount($foreignCurrency);

        AccountMonthlySummary::create([
            'date' => '2025-01-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 400000.00, // 400,000 HUF
        ]);

        $response = $this->getJson(route('api.v1.reports.cashflow'));

        $response->assertOk();
        $chartData = $response->json('chartData');
        $this->assertNotEmpty($chartData);

        // 400,000 HUF * 0.0025 = 1,000 USD (not 400,000 USD)
        $this->assertEqualsWithDelta(400000.0 * 0.0025, $chartData[0]['account_balance'], 0.01);
    }

    /**
     * When the account is in a foreign currency but NO rate record exists at all,
     * the raw amount is used as-is (graceful degradation, 1:1 fallback).
     * This test documents the current fallback behaviour rather than marking it correct;
     * the important assertion is that no exception is thrown and the response is valid JSON.
     */
    public function test_foreign_currency_without_any_rate_falls_back_to_raw_amount(): void
    {
        Sanctum::actingAs($this->user);

        $foreignCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['EUR'])
            ->create(['base' => null]);

        // No CurrencyRate records for this currency

        $account = $this->createAccount($foreignCurrency);

        AccountMonthlySummary::create([
            'date' => '2025-03-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 1000.00,
        ]);

        $response = $this->getJson(route('api.v1.reports.cashflow'));

        $response->assertOk();
        $chartData = $response->json('chartData');
        $this->assertNotEmpty($chartData);
        // Fallback: no rate found → treated as 1:1
        $this->assertEquals(1000.00, $chartData[0]['account_balance']);
    }

    /**
     * When a rate exists but only for a month AFTER the summary date, the code must fall back
     * to the oldest available rate (rather than 1:1), so that partial rate coverage still
     * produces a reasonable conversion instead of an unconverted raw amount.
     */
    public function test_foreign_currency_with_only_future_rate_uses_oldest_available_rate(): void
    {
        Sanctum::actingAs($this->user);

        $foreignCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['EUR'])
            ->create(['base' => null]);

        // Rate exists, but only for a date AFTER the summary
        CurrencyRate::factory()
            ->betweenCurrencies($foreignCurrency, $this->baseCurrency)
            ->create(['date' => '2025-06-01', 'rate' => 1.1]);

        $account = $this->createAccount($foreignCurrency);

        AccountMonthlySummary::create([
            'date' => '2025-01-01',    // earlier than the rate date
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 1000.00,
        ]);

        $response = $this->getJson(route('api.v1.reports.cashflow'));

        $response->assertOk();
        $chartData = $response->json('chartData');
        $this->assertNotEmpty($chartData);
        // No rate on or before the summary month → fall back to oldest available rate (1.1)
        $this->assertEqualsWithDelta(1100.00, $chartData[0]['account_balance'], 0.01);
        // Currency should NOT appear as missing since a rate exists
        $this->assertEmpty($response->json('warnings.currenciesWithoutRates'));
    }

    /**
     * When multiple months exist, each month uses the most recent rate on or before that month,
     * not a rate from a different month.
     */
    public function test_correct_rate_is_used_per_month(): void
    {
        Sanctum::actingAs($this->user);

        $foreignCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['EUR'])
            ->create(['base' => null]);

        // Rate for January and March
        CurrencyRate::factory()
            ->betweenCurrencies($foreignCurrency, $this->baseCurrency)
            ->create(['date' => '2025-01-15', 'rate' => 1.1]);

        CurrencyRate::factory()
            ->betweenCurrencies($foreignCurrency, $this->baseCurrency)
            ->create(['date' => '2025-03-15', 'rate' => 1.3]);

        $account = $this->createAccount($foreignCurrency);

        // Summary for January: should use the January rate (1.1)
        AccountMonthlySummary::create([
            'date' => '2025-01-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 1000.00,
        ]);

        // Summary for February: no February rate → use January rate (1.1, the most recent before Feb)
        AccountMonthlySummary::create([
            'date' => '2025-02-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 1000.00,
        ]);

        // Summary for March: should use the March rate (1.3)
        AccountMonthlySummary::create([
            'date' => '2025-03-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 1000.00,
        ]);

        $response = $this->getJson(route('api.v1.reports.cashflow'));

        $response->assertOk();
        $chartData = collect($response->json('chartData'))->keyBy('month');

        $this->assertEqualsWithDelta(1100.0, $chartData['2025-01-01']['account_balance'], 0.01); // 1000 * 1.1
        $this->assertEqualsWithDelta(1100.0, $chartData['2025-02-01']['account_balance'], 0.01); // 1000 * 1.1 (no Feb rate)
        $this->assertEqualsWithDelta(1300.0, $chartData['2025-03-01']['account_balance'], 0.01); // 1000 * 1.3
    }

    /**
     * The running total accumulates converted account_balance values across months.
     */
    public function test_running_total_accumulates_correctly(): void
    {
        Sanctum::actingAs($this->user);

        $account = $this->createAccount($this->baseCurrency);

        AccountMonthlySummary::create([
            'date' => '2025-01-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 100.00,
        ]);

        AccountMonthlySummary::create([
            'date' => '2025-02-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 200.00,
        ]);

        $response = $this->getJson(route('api.v1.reports.cashflow'));

        $response->assertOk();
        $chartData = collect($response->json('chartData'))->keyBy('month');

        $this->assertEquals(100.0, $chartData['2025-01-01']['account_balance_running_total']);
        $this->assertEquals(300.0, $chartData['2025-02-01']['account_balance_running_total']);
    }

    /**
     * When the 'withForecast' flag is not set, only 'fact' data_type rows are returned.
     * Forecast rows must be excluded.
     */
    public function test_forecast_data_excluded_by_default(): void
    {
        Sanctum::actingAs($this->user);

        $account = $this->createAccount($this->baseCurrency);

        AccountMonthlySummary::create([
            'date' => '2025-01-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 500.00,
        ]);

        AccountMonthlySummary::create([
            'date' => '2025-02-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'forecast',
            'amount' => 9999.00,
        ]);

        $response = $this->getJson(route('api.v1.reports.cashflow'));

        $response->assertOk();
        $chartData = collect($response->json('chartData'))->keyBy('month');

        $this->assertArrayHasKey('2025-01-01', $chartData->toArray());
        $this->assertArrayNotHasKey('2025-02-01', $chartData->toArray());
    }

    /**
     * When the 'withForecast' flag is set, both 'fact' and 'forecast' rows are included.
     */
    public function test_forecast_data_included_when_flag_is_set(): void
    {
        Sanctum::actingAs($this->user);

        $account = $this->createAccount($this->baseCurrency);

        AccountMonthlySummary::create([
            'date' => '2025-01-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 500.00,
        ]);

        AccountMonthlySummary::create([
            'date' => '2025-02-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'forecast',
            'amount' => 9999.00,
        ]);

        $response = $this->getJson(route('api.v1.reports.cashflow', ['withForecast' => true]));

        $response->assertOk();
        $chartData = collect($response->json('chartData'))->keyBy('month');

        $this->assertArrayHasKey('2025-01-01', $chartData->toArray());
        $this->assertArrayHasKey('2025-02-01', $chartData->toArray());
    }

    /**
     * When filtered by a specific accountEntity, only that account's summaries are returned.
     */
    public function test_account_entity_filter_returns_only_that_accounts_data(): void
    {
        Sanctum::actingAs($this->user);

        $accountA = $this->createAccount($this->baseCurrency);
        $accountB = $this->createAccount($this->baseCurrency);

        AccountMonthlySummary::create([
            'date' => '2025-01-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $accountA->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 100.00,
        ]);

        AccountMonthlySummary::create([
            'date' => '2025-01-01',
            'user_id' => $this->user->id,
            'account_entity_id' => $accountB->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
            'amount' => 900.00,
        ]);

        $response = $this->getJson(route('api.v1.reports.cashflow', ['accountEntity' => $accountA->id]));

        $response->assertOk();
        $chartData = $response->json('chartData');
        $this->assertCount(1, $chartData);
        // Only account A's 100, not 100+900=1000
        $this->assertEquals(100.00, $chartData[0]['account_balance']);
    }
}
