<?php

namespace Tests\Feature\API;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyRateApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $fromCurrency;
    private Currency $toCurrency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Intentionally create different currencies for testing
        $this->fromCurrency = Currency::factory()->for($this->user)->fromIsoCodes(['EUR'])->create();
        $this->toCurrency = Currency::factory()->for($this->user)->fromIsoCodes(['USD'])->create(['base' => true]);
    }

    public function test_guest_cannot_access_api(): void
    {
        // Without authentication, try to access the API endpoints
        // Note: Due to route model binding, Laravel resolves the Currency models first,
        // then the authorization check in the controller returns 403 (Forbidden) instead of 401.
        // This is expected behavior when using Gate::authorize() with unauthenticated users.

        // Get rates - returns 403 because Gate::authorize() fails
        $this->getJson(route('api.currency-rate.index', [
            'from' => $this->fromCurrency->id,
            'to' => $this->toCurrency->id,
        ]))->assertForbidden();

        // Create rate - returns 403 because of missing authentication
        $this->postJson(route('api.currency-rate.store'), [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2345,
        ])->assertForbidden();

        // Update rate - returns 403
        $rate = CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create([
                'date' => '2024-01-15',
                'rate' => 1.2345,
            ]);
        $this->putJson(route('api.currency-rate.update', $rate), [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-16',
            'rate' => 1.3456,
        ])->assertForbidden();

        // Delete rate - returns 403
        $this->deleteJson(route('api.currency-rate.destroy', $rate))->assertForbidden();
    }

    public function test_user_cannot_access_other_users_currencies(): void
    {
        $otherUser = User::factory()->create();
        $otherCurrency = Currency::factory()->for($otherUser)->create(['iso_code' => 'GBP']);

        $this->actingAs($this->user)
            ->getJson(route('api.currency-rate.index', [
                'from' => $otherCurrency->id,
                'to' => $this->toCurrency->id,
            ]))->assertForbidden();

        $this->actingAs($this->user)
            ->getJson(route('api.currency-rate.index', [
                'from' => $this->fromCurrency->id,
                'to' => $otherCurrency->id,
            ]))->assertForbidden();
    }

    public function test_can_get_all_rates(): void
    {
        CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('api.currency-rate.index', [
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

    public function test_can_get_rates_filtered_by_date_range(): void
    {
        CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create([
                'date' => '2024-01-10',
                'rate' => 1.1,
            ]);

        CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create([
                'date' => '2024-01-15',
                'rate' => 1.2,
            ]);

        CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create([
                'date' => '2024-01-20',
                'rate' => 1.3,
            ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.currency-rate.index', [
                'from' => $this->fromCurrency->id,
                'to' => $this->toCurrency->id,
                'date_from' => '2024-01-12',
                'date_to' => '2024-01-18',
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'rates');
    }

    public function test_can_create_rate(): void
    {
        $data = [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.2345,
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('api.currency-rate.store'), $data);

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

    public function test_cannot_create_duplicate_rate(): void
    {
        CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create([
                'date' => '2024-01-15',
                'rate' => 1.2345,
            ]);

        $data = [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => 1.3456,
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('api.currency-rate.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_can_update_rate(): void
    {
        $rate = CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create([
                'date' => '2024-01-15',
                'rate' => 1.2345,
            ]);

        $updateData = [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-16',
            'rate' => 1.3456,
        ];

        $response = $this->actingAs($this->user)
            ->putJson(route('api.currency-rate.update', $rate), $updateData);

        $response->assertOk()
            ->assertJsonStructure([
                'rate' => ['id', 'from_id', 'to_id', 'date', 'rate'],
                'message',
            ]);

        $this->assertDatabaseHas('currency_rates', [
            'id' => $rate->id,
            'date' => $updateData['date'],
            'rate' => $updateData['rate'],
        ]);
    }

    public function test_can_delete_rate(): void
    {
        $rate = CurrencyRate::factory()
            ->betweenCurrencies($this->fromCurrency, $this->toCurrency)
            ->create([
                'date' => '2024-01-15',
                'rate' => 1.2345,
            ]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.currency-rate.destroy', $rate));

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('currency_rates', [
            'id' => $rate->id,
        ]);
    }

    public function test_validation_requires_positive_rate(): void
    {
        $data = [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => '2024-01-15',
            'rate' => -1.2345,
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('api.currency-rate.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rate']);
    }

    public function test_validation_requires_valid_date(): void
    {
        $data = [
            'from_id' => $this->fromCurrency->id,
            'to_id' => $this->toCurrency->id,
            'date' => 'invalid-date',
            'rate' => 1.2345,
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('api.currency-rate.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_validation_requires_all_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.currency-rate.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['from_id', 'to_id', 'date', 'rate']);
    }
}
