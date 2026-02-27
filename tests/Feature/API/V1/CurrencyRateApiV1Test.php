<?php

namespace Tests\Feature\API\V1;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyRateApiV1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $fromCurrency;
    private Currency $toCurrency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->fromCurrency = Currency::factory()->for($this->user)->fromIsoCodes(['EUR'])->create();
        $this->toCurrency = Currency::factory()->for($this->user)->fromIsoCodes(['USD'])->create(['base' => true]);
    }

    // ===== AUTH TESTS =====

    public function test_unauthenticated_cannot_access_v1_index(): void
    {
        $this->getJson(route('api.v1.currency-rates.index', [
            'from' => $this->fromCurrency->id,
            'to' => $this->toCurrency->id,
        ]))->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_v1_store(): void
    {
        $this->postJson(route('api.v1.currency-rates.store'), [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2345,
        ])->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_v1_update(): void
    {
        $rate = CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create(['date' => '2024-01-15', 'rate' => 1.2345]);

        $this->putJson(route('api.v1.currency-rates.update', $rate), [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-16',
            'rate' => 1.3456,
        ])->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_v1_destroy(): void
    {
        $rate = CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create(['date' => '2024-01-15', 'rate' => 1.2345]);

        $this->deleteJson(route('api.v1.currency-rates.destroy', $rate))->assertForbidden();
    }

    // ===== HAPPY PATH TESTS =====

    public function test_v1_index_returns_all_rates(): void
    {
        CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.currency-rates.index', [
                'from' => $this->fromCurrency->id,
                'to' => $this->toCurrency->id,
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'rates' => [
                    '*' => ['id', 'from_id', 'to_id', 'date', 'rate'],
                ],
            ])
            ->assertJsonCount(3, 'rates');
    }

    public function test_v1_index_filters_by_date_range(): void
    {
        CurrencyRate::factory()->betweenCurrencies($this->fromCurrency, $this->toCurrency)->create(['date' => '2024-01-10', 'rate' => 1.1]);
        CurrencyRate::factory()->betweenCurrencies($this->fromCurrency, $this->toCurrency)->create(['date' => '2024-01-15', 'rate' => 1.2]);
        CurrencyRate::factory()->betweenCurrencies($this->fromCurrency, $this->toCurrency)->create(['date' => '2024-01-20', 'rate' => 1.3]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.currency-rates.index', [
                'from' => $this->fromCurrency->id,
                'to' => $this->toCurrency->id,
                'date_from' => '2024-01-12',
                'date_to' => '2024-01-18',
            ]));

        $response->assertOk()->assertJsonCount(1, 'rates');
    }

    public function test_v1_store_creates_rate(): void
    {
        $data = [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2345,
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.currency-rates.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'rate' => ['id', 'from_id', 'to_id', 'date', 'rate'],
                'message',
            ]);

        $this->assertDatabaseHas('currency_rates', [
            'from_id' => $data['from_id'],
            'to_id' => $data['to_id'],
            'date' => $data['date'],
        ]);
    }

    public function test_v1_update_modifies_rate(): void
    {
        $rate = CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create(['date' => '2024-01-15', 'rate' => 1.2345]);

        $updateData = [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-16',
            'rate' => 1.3456,
        ];

        $response = $this->actingAs($this->user)
            ->putJson(route('api.v1.currency-rates.update', $rate), $updateData);

        $response->assertOk()
            ->assertJsonStructure([
                'rate' => ['id', 'from_id', 'to_id', 'date', 'rate'],
                'message',
            ]);

        $this->assertDatabaseHas('currency_rates', [
            'id' => $rate->id,
            'date' => $updateData['date'],
        ]);
    }

    public function test_v1_destroy_deletes_rate(): void
    {
        $rate = CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create(['date' => '2024-01-15', 'rate' => 1.2345]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.currency-rates.destroy', $rate));

        $response->assertOk()->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('currency_rates', ['id' => $rate->id]);
    }

    // ===== ERROR FORMAT TESTS (V1 error.* contract) =====

    public function test_v1_validation_error_uses_error_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.currency-rates.store'), []);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_v1_validation_error_includes_field_details(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.currency-rates.store'), []);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['from_id', 'to_id', 'date', 'rate']]]);
    }

    public function test_v1_authorization_error_uses_error_contract(): void
    {
        $otherUser = User::factory()->create();
        $otherCurrency = Currency::factory()->for($otherUser)->create(['iso_code' => 'GBP']);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.currency-rates.index', [
                'from' => $otherCurrency->id,
                'to' => $this->toCurrency->id,
            ]));

        $response->assertForbidden()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_v1_not_found_uses_error_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.currency-rates.destroy', ['currencyRate' => 99999]));

        $response->assertNotFound()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_v1_cross_user_currency_access_is_forbidden(): void
    {
        $otherUser = User::factory()->create();
        $otherCurrency = Currency::factory()->for($otherUser)->create(['iso_code' => 'GBP']);

        $this->actingAs($this->user)
            ->getJson(route('api.v1.currency-rates.index', [
                'from' => $this->fromCurrency->id,
                'to' => $otherCurrency->id,
            ]))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
