<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class InvestmentPriceApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a series of investment prices for a given investment.
     * Each entry should be an array with keys 'date' (Y-m-d) and 'price' (numeric).
     */
    protected function createPricesForInvestment(Investment $investment, array $entries): void
    {
        foreach ($entries as $entry) {
            InvestmentPrice::factory()
                ->for($investment)
                ->create($entry);
        }
    }

    protected function createUserCurrencyAndInvestment(array $investmentAttributes = []): array
    {
        /** @var User $user */
        $user = User::factory()->create();

        Currency::factory()->for($user)->create();

        /** @var Investment $investment */
        $investment = Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create($investmentAttributes);

        return [$user, $investment];
    }

    public function test_unauthenticated_users_cannot_access_investment_price_endpoints(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        // Test index endpoint
        $response = $this->getJson(
            route('api.v1.investment-prices.index', ['investment' => $investment->id])
        );
        $this->assertUserNotAuthorized($response);

        // Test store endpoint
        $response = $this->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $investment->id,
            'date' => '2024-01-01',
            'price' => 100,
        ]);
        $this->assertUserNotAuthorized($response);

        // Test checkPrice endpoint
        $response = $this->getJson(route('api.v1.investment-prices.check', ['investment' => $investment->id, 'date' => '2024-01-01']));
        $this->assertUserNotAuthorized($response);
    }

    public function test_users_cannot_access_other_users_investment_prices(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        Currency::factory()->for($user1)->create();

        /** @var User $user2 */
        $user2 = User::factory()->create();
        Currency::factory()->for($user2)->create();

        $investment = Investment::factory()
            ->for($user1)
            ->withUser($user1)
            ->create();

        $price = InvestmentPrice::factory()
            ->for($investment)
            ->create([
                'date' => '2024-01-01',
                'price' => 100,
            ]);

        // User2 tries to access user1's investment prices
        $response = $this->actingAs($user2)->getJson(route('api.v1.investment-prices.index', ['investment' => $investment->id]));
        $this->assertUserNotAuthorized($response);

        // User2 tries to update user1's investment price
        // Note: This will fail validation because investment_id must belong to authenticated user
        $response = $this->actingAs($user2)->putJson(route('api.v1.investment-prices.update', ['investmentPrice' => $price->id]), [
            'investment_id' => $investment->id,
            'date' => '2024-01-02',
            'price' => 200,
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['investment_id']);

        // User2 tries to delete user1's investment price
        $response = $this->actingAs($user2)->deleteJson(route('api.v1.investment-prices.destroy', ['investmentPrice' => $price->id]));
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_index_returns_all_prices_for_investment(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        // Create multiple prices
        $this->createPricesForInvestment($investment, [
            ['date' => '2024-01-01', 'price' => 100],
            ['date' => '2024-01-02', 'price' => 110],
            ['date' => '2024-01-03', 'price' => 120],
        ]);

        $response = $this->actingAs($user)->getJson(route('api.v1.investment-prices.index', ['investment' => $investment->id]));
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertEquals(3, count($json['prices']));
        // Should be ordered by date
        $this->assertEquals('2024-01-01', $json['prices'][0]['date']);
        $this->assertEquals('2024-01-02', $json['prices'][1]['date']);
        $this->assertEquals('2024-01-03', $json['prices'][2]['date']);
    }

    public function test_index_filters_by_date_from(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $this->createPricesForInvestment($investment, [
            ['date' => '2024-01-01', 'price' => 100],
            ['date' => '2024-01-15', 'price' => 110],
            ['date' => '2024-01-30', 'price' => 120],
        ]);

        $response = $this->actingAs($user)->getJson(route('api.v1.investment-prices.index', ['investment' => $investment->id, 'date_from' => '2024-01-15']));
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertEquals(2, count($json['prices']));
        $this->assertEquals('2024-01-15', $json['prices'][0]['date']);
        $this->assertEquals('2024-01-30', $json['prices'][1]['date']);
    }

    public function test_index_filters_by_date_to(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $this->createPricesForInvestment($investment, [
            ['date' => '2024-01-01', 'price' => 100],
            ['date' => '2024-01-15', 'price' => 110],
            ['date' => '2024-01-30', 'price' => 120],
        ]);

        $response = $this->actingAs($user)->getJson(route('api.v1.investment-prices.index', ['investment' => $investment->id, 'date_to' => '2024-01-15']));
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertEquals(2, count($json['prices']));
        $this->assertEquals('2024-01-01', $json['prices'][0]['date']);
        $this->assertEquals('2024-01-15', $json['prices'][1]['date']);
    }

    public function test_index_filters_by_date_range(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $this->createPricesForInvestment($investment, [
            ['date' => '2024-01-01', 'price' => 100],
            ['date' => '2024-01-15', 'price' => 110],
            ['date' => '2024-01-30', 'price' => 120],
        ]);

        $response = $this->actingAs($user)->getJson(route('api.v1.investment-prices.index', ['investment' => $investment->id, 'date_from' => '2024-01-10', 'date_to' => '2024-01-20']));
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();

        $this->assertEquals(1, count($json['prices']));
        $this->assertEquals('2024-01-15', $json['prices'][0]['date']);
    }

    public function test_store_creates_new_investment_price(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $response = $this->actingAs($user)->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $investment->id,
            'date' => '2024-01-15',
            'price' => 150.50,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonPath('price.date', '2024-01-15');
        $response->assertJsonPath('price.price', 150.50);
        $response->assertJsonPath('price.investment_id', $investment->id);
        $response->assertJsonStructure([
            'price' => [
                'id',
                'investment_id',
                'date',
                'price',
                'created_at',
                'updated_at',
                'investment',
            ],
            'message',
        ]);

        // Verify in database
        $this->assertDatabaseHas('investment_prices', [
            'investment_id' => $investment->id,
            'date' => '2024-01-15',
            'price' => 150.50,
        ]);
    }

    public function test_store_requires_investment_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.investment-prices.store'), [
            'date' => '2024-01-15',
            'price' => 150.50,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['investment_id']);
    }

    public function test_store_requires_date(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $response = $this->actingAs($user)->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $investment->id,
            'price' => 150.50,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['date']);
    }

    public function test_store_requires_price(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $response = $this->actingAs($user)->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $investment->id,
            'date' => '2024-01-15',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['price']);
    }

    public function test_update_modifies_existing_investment_price(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $price = InvestmentPrice::factory()
            ->for($investment)
            ->create([
                'date' => '2024-01-01',
                'price' => 100,
            ]);

        $response = $this->actingAs($user)->putJson(route('api.v1.investment-prices.update', ['investmentPrice' => $price->id]), [
            'investment_id' => $investment->id,
            'date' => '2024-01-02',
            'price' => 200.75,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('price.date', '2024-01-02');
        $response->assertJsonPath('price.price', 200.75);
        $response->assertJsonPath('message', __('Investment price updated'));

        // Verify in database
        $this->assertDatabaseHas('investment_prices', [
            'id' => $price->id,
            'investment_id' => $investment->id,
            'date' => '2024-01-02',
            'price' => 200.75,
        ]);
    }

    public function test_destroy_deletes_investment_price(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $price = InvestmentPrice::factory()
            ->for($investment)
            ->create([
                'date' => '2024-01-01',
                'price' => 100,
            ]);

        $response = $this->actingAs($user)->deleteJson(route('api.v1.investment-prices.destroy', ['investmentPrice' => $price->id]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('message', __('Investment price deleted'));

        // Verify deletion in database
        $this->assertDatabaseMissing('investment_prices', [
            'id' => $price->id,
        ]);
    }

    public function test_check_price_validates_date_format(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        // Missing date parameter
        $response = $this->actingAs($user)->getJson(route('api.v1.investment-prices.check', ['investment' => $investment->id]));
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['date']);

        // Invalid date format
        $response = $this->actingAs($user)->getJson(route('api.v1.investment-prices.check', ['investment' => $investment->id, 'date' => '01/15/2024']));
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['date']);

        // Invalid date format
        $response = $this->actingAs($user)->getJson(route('api.v1.investment-prices.check', ['investment' => $investment->id, 'date' => '2024-13-01']));
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['date']);

        // Invalid date string
        $response = $this->actingAs($user)->getJson(route('api.v1.investment-prices.check', ['investment' => $investment->id, 'date' => 'not-a-date']));
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['date']);
    }

    public function test_check_price_returns_true_when_price_exists(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        InvestmentPrice::factory()
            ->for($investment)
            ->create([
                'date' => '2024-01-15',
                'price' => 123.45,
            ]);

        $response = $this->actingAs($user)->getJson(
            route('api.v1.investment-prices.check', ['investment' => $investment->id, 'date' => '2024-01-15'])
        );
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('exists', true);
        $response->assertJsonPath('price', 123.45);
    }

    public function test_check_price_returns_false_when_price_does_not_exist(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $response = $this->actingAs($user)->getJson(
            route('api.v1.investment-prices.check', ['investment' => $investment->id, 'date' => '2024-01-15'])
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('exists', false);
        $response->assertJsonPath('price', null);
    }

    public function test_check_price_only_checks_specific_investment(): void
    {
        [$user, $investment1] = $this->createUserCurrencyAndInvestment();

        $investment2 = Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create();

        // Create price for investment1 only
        InvestmentPrice::factory()
            ->for($investment1)
            ->create([
                'date' => '2024-01-15',
                'price' => 123.45,
            ]);

        // Check investment1 - should exist
        $response = $this->actingAs($user)->getJson(
            route('api.v1.investment-prices.check', ['investment' => $investment1->id, 'date' => '2024-01-15'])
        );
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('exists', true);

        // Check investment2 - should not exist
        $response = $this->actingAs($user)->getJson(
            route('api.v1.investment-prices.check', ['investment' => $investment2->id, 'date' => '2024-01-15'])
        );
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('exists', false);
    }

    public function test_store_prevents_duplicate_prices_for_same_date(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        // Create first price
        InvestmentPrice::factory()
            ->for($investment)
            ->create([
                'date' => '2024-01-15',
                'price' => 100,
            ]);

        // Try to create duplicate
        $response = $this->actingAs($user)->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $investment->id,
            'date' => '2024-01-15',
            'price' => 200,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['date']);
    }

    public function test_price_must_be_numeric(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $response = $this->actingAs($user)->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $investment->id,
            'date' => '2024-01-15',
            'price' => 'not-a-number',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['price']);
    }

    public function test_price_must_be_greater_than_zero(): void
    {
        [$user, $investment] = $this->createUserCurrencyAndInvestment();

        $response = $this->actingAs($user)->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $investment->id,
            'date' => '2024-01-15',
            'price' => 0,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['price']);

        $response = $this->actingAs($user)->postJson(route('api.v1.investment-prices.store'), [
            'investment_id' => $investment->id,
            'date' => '2024-01-15',
            'price' => -100,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['price']);
    }
}
