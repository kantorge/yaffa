<?php

namespace Tests\Feature\API\V1;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentPriceApiV1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Investment $investment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->createForUser($this->user, Currency::class);
        $this->investment = Investment::factory()
            ->for($this->user)
            ->withUser($this->user)
            ->create();
    }

    // ===== AUTH TESTS =====

    public function test_unauthenticated_cannot_access_v1_index(): void
    {
        $response = $this->getJson(route('api.v1.investment-prices.index', ['investment' => $this->investment->id]));
        $this->assertUserNotAuthorized($response);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_unauthenticated_cannot_access_v1_store(): void
    {
        $response = $this->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $this->investment->id,
            'date' => '2024-01-15',
            'price' => 100.00,
        ]);
        $this->assertUserNotAuthorized($response);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_unauthenticated_cannot_access_v1_check(): void
    {
        $response = $this->getJson(route('api.v1.investment-prices.check', ['investment' => $this->investment->id]) . '?date=2024-01-15');
        $this->assertUserNotAuthorized($response);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    // ===== HAPPY PATH TESTS =====

    public function test_v1_index_returns_prices(): void
    {
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-01', 'price' => 100]);
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-02', 'price' => 110]);
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-03', 'price' => 120]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.index', ['investment' => $this->investment->id]));

        $response->assertOk()
            ->assertJsonStructure([
                'prices' => [
                    '*' => ['id', 'investment_id', 'date', 'price'],
                ],
            ])
            ->assertJsonCount(3, 'prices');

        // Ordered by date.
        $json = $response->json();
        $this->assertSame('2024-01-01', $json['prices'][0]['date']);
        $this->assertSame('2024-01-02', $json['prices'][1]['date']);
        $this->assertSame('2024-01-03', $json['prices'][2]['date']);
    }

    public function test_v1_index_filters_by_date_from(): void
    {
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-01', 'price' => 100]);
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-15', 'price' => 110]);
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-30', 'price' => 120]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.index', [
                'investment' => $this->investment->id,
                'date_from' => '2024-01-15',
            ]));

        $response->assertOk()->assertJsonCount(2, 'prices');
        $json = $response->json();
        $this->assertSame('2024-01-15', $json['prices'][0]['date']);
        $this->assertSame('2024-01-30', $json['prices'][1]['date']);
    }

    public function test_v1_index_filters_by_date_to(): void
    {
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-01', 'price' => 100]);
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-15', 'price' => 110]);
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-30', 'price' => 120]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.index', [
                'investment' => $this->investment->id,
                'date_to' => '2024-01-15',
            ]));

        $response->assertOk()->assertJsonCount(2, 'prices');
        $json = $response->json();
        $this->assertSame('2024-01-01', $json['prices'][0]['date']);
        $this->assertSame('2024-01-15', $json['prices'][1]['date']);
    }

    public function test_v1_index_filters_by_date_range(): void
    {
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-10', 'price' => 100]);
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-15', 'price' => 110]);
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-20', 'price' => 120]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.index', [
                'investment' => $this->investment->id,
                'date_from' => '2024-01-12',
                'date_to' => '2024-01-18',
            ]));

        $response->assertOk()->assertJsonCount(1, 'prices');
    }

    public function test_v1_store_creates_price(): void
    {
        $data = [
            'investment_id' => $this->investment->id,
            'date' => '2024-01-15',
            'price' => 100.50,
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'price' => ['id', 'investment_id', 'date', 'price', 'created_at', 'updated_at', 'investment'],
                'message',
            ])
            ->assertJsonPath('price.date', '2024-01-15')
            ->assertJsonPath('price.price', '100.5000000000')
            ->assertJsonPath('price.investment_id', $this->investment->id);

        $this->assertDatabaseHas('investment_prices', [
            'investment_id' => $data['investment_id'],
            'date' => $data['date'],
        ]);
    }

    public function test_v1_store_requires_investment_id(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), [
                'date' => '2024-01-15',
                'price' => 150.50,
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['investment_id']);
    }

    public function test_v1_store_requires_date(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), [
                'investment_id' => $this->investment->id,
                'price' => 150.50,
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date']);
    }

    public function test_v1_store_requires_price(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), [
                'investment_id' => $this->investment->id,
                'date' => '2024-01-15',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['price']);
    }

    public function test_v1_store_prevents_duplicate_prices_for_same_date(): void
    {
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-15', 'price' => 100]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), [
                'investment_id' => $this->investment->id,
                'date' => '2024-01-15',
                'price' => 200,
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date']);
    }

    public function test_v1_price_must_be_numeric(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), [
                'investment_id' => $this->investment->id,
                'date' => '2024-01-15',
                'price' => 'not-a-number',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['price']);
    }

    public function test_v1_price_must_be_greater_than_zero(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), [
                'investment_id' => $this->investment->id,
                'date' => '2024-01-15',
                'price' => 0,
            ]);
        $response->assertUnprocessable()->assertJsonValidationErrors(['price']);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), [
                'investment_id' => $this->investment->id,
                'date' => '2024-01-15',
                'price' => -100,
            ]);
        $response->assertUnprocessable()->assertJsonValidationErrors(['price']);
    }

    /**
     * price rejects a value with more fractional digits than investment_prices.price's
     * DECIMAL(20,10) scale allows - the new `decimal:0,10` rule (specification.md FR-8).
     * Magnitude alone doesn't catch this - the value is well within the column's max.
     */
    public function test_v1_price_rejects_excess_decimal_places(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), [
                'investment_id' => $this->investment->id,
                'date' => '2024-01-15',
                'price' => '1234.567890123456',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['price']);
    }

    /**
     * price accepts a value at exactly investment_prices.price's DECIMAL(20,10) scale - the
     * new rule must not reject a value the column can genuinely hold in full.
     */
    public function test_v1_price_accepts_value_at_exact_decimal_scale(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), [
                'investment_id' => $this->investment->id,
                'date' => '2024-01-15',
                'price' => '1234.5678901234',
            ]);

        $response->assertCreated();
    }

    public function test_v1_update_modifies_price(): void
    {
        $price = InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-15', 'price' => 100.00]);

        $updateData = [
            'investment_id' => $this->investment->id,
            'date' => '2024-01-16',
            'price' => 110.00,
        ];

        $response = $this->actingAs($this->user)
            ->putJson(route('api.v1.investment-prices.update', ['investmentPrice' => $price->id]), $updateData);

        $response->assertOk()
            ->assertJsonStructure([
                'price' => ['id', 'investment_id', 'date', 'price'],
                'message',
            ])
            ->assertJsonPath('price.date', '2024-01-16')
            ->assertJsonPath('price.price', '110.0000000000')
            ->assertJsonPath('message', __('Investment price updated'));

        $this->assertDatabaseHas('investment_prices', [
            'id' => $price->id,
            'price' => 110.00,
        ]);
    }

    public function test_v1_update_cross_user_investment_id_fails_validation(): void
    {
        // A PUT with an investment_id the acting user doesn't own fails validation (422), not
        // authorization (403) - investment_id ownership is enforced by the request's validation
        // rule, not a policy check, so this is a deliberately different status than the
        // GET/DELETE cross-user cases below.
        $otherUser = User::factory()->create();
        $this->createForUser($otherUser, Currency::class);
        $otherInvestment = Investment::factory()->for($otherUser)->withUser($otherUser)->create();
        $price = InvestmentPrice::factory()->for($otherInvestment)->create(['date' => '2024-01-01', 'price' => 100]);

        $response = $this->actingAs($this->user)
            ->putJson(route('api.v1.investment-prices.update', ['investmentPrice' => $price->id]), [
                'investment_id' => $otherInvestment->id,
                'date' => '2024-01-02',
                'price' => 200,
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['investment_id']);
    }

    public function test_v1_destroy_cross_user_price_is_forbidden(): void
    {
        $otherUser = User::factory()->create();
        $this->createForUser($otherUser, Currency::class);
        $otherInvestment = Investment::factory()->for($otherUser)->withUser($otherUser)->create();
        $price = InvestmentPrice::factory()->for($otherInvestment)->create(['date' => '2024-01-01', 'price' => 100]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.investment-prices.destroy', ['investmentPrice' => $price->id]));

        $response->assertForbidden();
    }

    public function test_v1_destroy_deletes_price(): void
    {
        $price = InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-15', 'price' => 100.00]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.investment-prices.destroy', ['investmentPrice' => $price->id]));

        $response->assertOk()
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', __('Investment price deleted'));

        $this->assertDatabaseMissing('investment_prices', ['id' => $price->id]);
    }

    public function test_v1_check_price_requires_a_date(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.check', ['investment' => $this->investment->id]));
        $response->assertUnprocessable()->assertJsonValidationErrors(['date']);
    }

    public function test_v1_check_price_rejects_invalid_date_formats(): void
    {
        foreach (['01/15/2024', '2024-13-01', 'not-a-date'] as $invalidDate) {
            $response = $this->actingAs($this->user)->getJson(
                route('api.v1.investment-prices.check', ['investment' => $this->investment->id, 'date' => $invalidDate])
            );
            $response->assertUnprocessable()->assertJsonValidationErrors(['date']);
        }
    }

    public function test_v1_check_price_returns_exists_false_when_no_price(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.check', ['investment' => $this->investment->id]) . '?date=2024-01-15');

        $response->assertOk()
            ->assertJson(['exists' => false, 'price' => null]);
    }

    public function test_v1_check_price_returns_exists_true_when_price_found(): void
    {
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-15', 'price' => 99.99]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.check', ['investment' => $this->investment->id]) . '?date=2024-01-15');

        $response->assertOk()
            ->assertJson(['exists' => true])
            ->assertJsonPath('price', '99.9900000000');
    }

    public function test_v1_check_price_only_checks_specific_investment(): void
    {
        $otherInvestment = Investment::factory()->for($this->user)->withUser($this->user)->create();
        InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-15', 'price' => 123.45]);

        $response = $this->actingAs($this->user)->getJson(
            route('api.v1.investment-prices.check', ['investment' => $this->investment->id]) . '?date=2024-01-15'
        );
        $response->assertOk()->assertJson(['exists' => true]);

        $response = $this->actingAs($this->user)->getJson(
            route('api.v1.investment-prices.check', ['investment' => $otherInvestment->id]) . '?date=2024-01-15'
        );
        $response->assertOk()->assertJson(['exists' => false]);
    }

    // ===== ERROR FORMAT TESTS =====

    public function test_v1_validation_error_uses_default_validation_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), []);

        $response->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['investment_id', 'date', 'price']])
            ->assertJsonValidationErrors(['investment_id', 'date', 'price']);
    }

    public function test_v1_authorization_error_uses_error_contract(): void
    {
        $otherUser = User::factory()->create();
        $this->createForUser($otherUser, Currency::class);
        $otherInvestment = Investment::factory()->for($otherUser)->withUser($otherUser)->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.index', ['investment' => $otherInvestment->id]));

        $response->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_v1_not_found_uses_error_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.investment-prices.destroy', ['investmentPrice' => 99999]));

        $response->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
