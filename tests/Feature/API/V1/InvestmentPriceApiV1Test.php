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
        $this->assertThat(
            $response->status(),
            $this->logicalOr($this->equalTo(401), $this->equalTo(403))
        );
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_unauthenticated_cannot_access_v1_store(): void
    {
        $response = $this->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $this->investment->id,
            'date' => '2024-01-15',
            'price' => 100.00,
        ]);
        $this->assertThat(
            $response->status(),
            $this->logicalOr($this->equalTo(401), $this->equalTo(403))
        );
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_unauthenticated_cannot_access_v1_check(): void
    {
        $response = $this->getJson(route('api.v1.investment-prices.check', ['investment' => $this->investment->id]) . '?date=2024-01-15');
        $this->assertThat(
            $response->status(),
            $this->logicalOr($this->equalTo(401), $this->equalTo(403))
        );
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    // ===== HAPPY PATH TESTS =====

    public function test_v1_index_returns_prices(): void
    {
        InvestmentPrice::factory()->for($this->investment)->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.index', ['investment' => $this->investment->id]));

        $response->assertOk()
            ->assertJsonStructure([
                'prices' => [
                    '*' => ['id', 'investment_id', 'date', 'price'],
                ],
            ])
            ->assertJsonCount(3, 'prices');
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
                'price' => ['id', 'investment_id', 'date', 'price'],
                'message',
            ]);

        $this->assertDatabaseHas('investment_prices', [
            'investment_id' => $data['investment_id'],
            'date' => $data['date'],
        ]);
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
            ]);

        $this->assertDatabaseHas('investment_prices', [
            'id' => $price->id,
            'price' => 110.00,
        ]);
    }

    public function test_v1_destroy_deletes_price(): void
    {
        $price = InvestmentPrice::factory()->for($this->investment)->create(['date' => '2024-01-15', 'price' => 100.00]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.investment-prices.destroy', ['investmentPrice' => $price->id]));

        $response->assertOk()->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('investment_prices', ['id' => $price->id]);
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
            ->assertJson(['exists' => true]);
    }

    // ===== ERROR FORMAT TESTS (V1 error.* contract) =====

    public function test_v1_validation_error_uses_error_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), []);

        $response->assertUnprocessable()
            ->assertJsonStructure(['error' => ['code', 'message', 'details']])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_v1_validation_error_includes_field_details(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-prices.store'), []);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['investment_id', 'date', 'price']]]);
    }

    public function test_v1_authorization_error_uses_error_contract(): void
    {
        $otherUser = User::factory()->create();
        $this->createForUser($otherUser, Currency::class);
        $otherInvestment = Investment::factory()->for($otherUser)->withUser($otherUser)->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-prices.index', ['investment' => $otherInvestment->id]));

        $response->assertForbidden()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_v1_not_found_uses_error_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.investment-prices.destroy', ['investmentPrice' => 99999]));

        $response->assertNotFound()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
