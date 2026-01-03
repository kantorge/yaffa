<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class InvestmentApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_INVESTMENT_NAME = 'Same investment name';
    private const BASE_API_ENDPOINT = '/api/assets/investment';

    public function test_unauthenticated_users_cannot_access_investment_list(): void
    {
        $response = $this->getJson(self::BASE_API_ENDPOINT);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_investment_list_with_query_applies_all_provided_filters(): void
    {
        // Primary user with test data covering various use cases
        /** @var User $user */
        $user = User::factory()->create();

        // Multiple (2) currencies
        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('USD'));
        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('EUR'));
        $currencies = $user->currencies()->get();

        // One investment with base name
        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => self::BASE_INVESTMENT_NAME,
                'symbol' => 'BASE',
                'isin' => 'US0000000001',
                'currency_id' => $currencies->first()->id,
            ]);

        // Other user with dummy data and same investment name
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->createForUser($user2, Currency::class);

        Investment::factory()
            ->for($user2)
            ->withUser($user2)
            ->create([
                'active' => true,
                'name' => self::BASE_INVESTMENT_NAME,
            ]);

        // Query string is applied
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?query=' . self::BASE_INVESTMENT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals(1, count($json));
        $response->assertJsonPath('0.name', self::BASE_INVESTMENT_NAME);

        // Only items of the specific user are returned even if criteria matches items of other user(s)
        $response->assertJsonPath('0.user_id', $user->id);

        // Only active items are returned by default
        // We create a new item for primary user that is inactive, other currency
        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => false,
                'name' => 'Inactive USD Investment',
                'currency_id' => $currencies->last()->id,
            ]);

        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?query=' . self::BASE_INVESTMENT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals(1, count($json));
        $response->assertJsonPath('0.name', self::BASE_INVESTMENT_NAME);

        // Inactive items can be requested
        $response = $this->actingAs($user)
            ->getJson(self::BASE_API_ENDPOINT . '?active=0&query=Inactive');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals(1, count($json));
        $response->assertJsonPath('0.name', 'Inactive USD Investment');

        // Currency can be specified
        $response = $this->actingAs($user)
            ->getJson(self::BASE_API_ENDPOINT . '?currency_id=' . $currencies->first()->id . '&query=' . self::BASE_INVESTMENT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.name', self::BASE_INVESTMENT_NAME);
        $response->assertJsonPath('0.currency_id', $currencies->first()->id);

        // Default limit is applied for number of results
        for ($i = 1; $i <= 20; $i++) {
            Investment::factory()
                ->for($user)
                ->withUser($user)
                ->create([
                    'active' => true,
                    'name' => self::BASE_INVESTMENT_NAME . " - clone - " . $i,
                ]);
        }

        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?query=clone');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(10, count($response->json()));

        // Custom limit is applied for number of results
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?query=clone&limit=15');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(15, count($response->json()));
    }

    public function test_investment_list_can_search_by_symbol(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, Currency::class);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Apple Inc.',
                'symbol' => 'AAPL',
            ]);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Microsoft Corporation',
                'symbol' => 'MSFT',
            ]);

        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?query=AAPL');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.symbol', 'AAPL');

        // Case-insensitive search
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?query=aapl');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.symbol', 'AAPL');
    }

    public function test_investment_list_can_search_by_isin(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, Currency::class);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Apple Inc.',
                'isin' => 'US0378331005',
            ]);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Microsoft Corporation',
                'isin' => 'US5949181045',
            ]);

        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?query=US0378331005');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.isin', 'US0378331005');

        // Partial ISIN search
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?query=037833');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.isin', 'US0378331005');
    }

    public function test_investment_list_filters_by_active_status(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, Currency::class);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Active Investment',
            ]);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => false,
                'name' => 'Inactive Investment',
            ]);

        // Filter for active only
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?active=1');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.active', true);

        // Filter for inactive only
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?active=0');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.active', false);
    }

    public function test_investment_list_only_returns_users_own_investments(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        $this->createForUser($user1, Currency::class);

        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->createForUser($user2, Currency::class);

        // Create investment for user1
        Investment::factory()
            ->for($user1)
            ->withUser($user1)
            ->create([
                'active' => true,
                'name' => 'User1 Investment',
            ]);

        // Create investment for user2
        Investment::factory()
            ->for($user2)
            ->withUser($user2)
            ->create([
                'active' => true,
                'name' => 'User2 Investment',
            ]);

        // User1 should only see their own investment
        $response = $this->actingAs($user1)->getJson(self::BASE_API_ENDPOINT);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.user_id', $user1->id);

        // User2 should only see their own investment
        $response = $this->actingAs($user2)->getJson(self::BASE_API_ENDPOINT);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.user_id', $user2->id);
    }

    public function test_investment_list_filters_by_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('USD'));
        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('EUR'));
        $currencies = $user->currencies()->get();

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'USD Investment',
                'currency_id' => $currencies->first()->id,
            ]);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'EUR Investment',
                'currency_id' => $currencies->last()->id,
            ]);

        // Filter by first currency
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?currency_id=' . $currencies->first()->id);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.currency_id', $currencies->first()->id);

        // Filter by second currency
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?currency_id=' . $currencies->last()->id);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.currency_id', $currencies->last()->id);
    }

    public function test_investment_list_respects_limit_parameter(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, Currency::class);

        // Create 25 investments
        for ($i = 1; $i <= 25; $i++) {
            Investment::factory()
                ->for($user)
                ->withUser($user)
                ->create([
                    'active' => true,
                    'name' => 'Investment ' . $i,
                ]);
        }

        // Default limit of 10
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(10, count($response->json()));

        // Custom limit of 5
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?limit=5');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(5, count($response->json()));

        // Custom limit of 20
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?limit=20');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(20, count($response->json()));
    }

    public function test_investment_list_combines_multiple_filters(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('USD'));
        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('EUR'));
        $currencies = $user->currencies()->get();

        // Create active USD investment
        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Active USD Investment',
                'currency_id' => $currencies->first()->id,
            ]);

        // Create active EUR investment
        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Active EUR Investment',
                'currency_id' => $currencies->last()->id,
            ]);

        // Create inactive USD investment
        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => false,
                'name' => 'Inactive USD Investment',
                'currency_id' => $currencies->first()->id,
            ]);

        // Filter by active and USD currency
        $response = $this->actingAs($user)->getJson(
            self::BASE_API_ENDPOINT . '?active=1&currency_id=' . $currencies->first()->id
        );
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.name', 'Active USD Investment');

        // Filter by active, USD currency, and query string
        $response = $this->actingAs($user)->getJson(
            self::BASE_API_ENDPOINT . '?active=1&currency_id=' . $currencies->first()->id . '&query=Active'
        );
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.name', 'Active USD Investment');
    }

    public function test_investment_list_supports_sorting_with_validation(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, Currency::class);

        // Create investments with different values for sorting
        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Zebra Corp',
                'symbol' => 'ZZZ',
                'isin' => 'US9999999999',
            ]);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => false,
                'name' => 'Apple Inc',
                'symbol' => 'AAA',
                'isin' => 'US1111111111',
            ]);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Microsoft Corp',
                'symbol' => 'MMM',
                'isin' => 'US5555555555',
            ]);

        // Default sorting: by name, ascending
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT);
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals('Apple Inc', $json[0]['name']);
        $this->assertEquals('Microsoft Corp', $json[1]['name']);
        $this->assertEquals('Zebra Corp', $json[2]['name']);

        // Sort by name descending
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=name&sort_order=desc');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals('Zebra Corp', $json[0]['name']);
        $this->assertEquals('Microsoft Corp', $json[1]['name']);
        $this->assertEquals('Apple Inc', $json[2]['name']);

        // Sort by symbol ascending
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=symbol&sort_order=asc');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals('AAA', $json[0]['symbol']);
        $this->assertEquals('MMM', $json[1]['symbol']);
        $this->assertEquals('ZZZ', $json[2]['symbol']);

        // Sort by symbol descending
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=symbol&sort_order=desc');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals('ZZZ', $json[0]['symbol']);
        $this->assertEquals('MMM', $json[1]['symbol']);
        $this->assertEquals('AAA', $json[2]['symbol']);

        // Sort by ISIN ascending
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=isin&sort_order=asc');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals('US1111111111', $json[0]['isin']);
        $this->assertEquals('US5555555555', $json[1]['isin']);
        $this->assertEquals('US9999999999', $json[2]['isin']);

        // Sort by active status ascending (false first, then true)
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=active&sort_order=asc');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals(false, $json[0]['active']);
        $this->assertEquals(true, $json[1]['active']);
        $this->assertEquals(true, $json[2]['active']);

        // Sort by active status descending (true first, then false)
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=active&sort_order=desc');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals(true, $json[0]['active']);
        $this->assertEquals(true, $json[1]['active']);
        $this->assertEquals(false, $json[2]['active']);

        // Invalid sort_by falls back to 'name'
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=invalid_column&sort_order=asc');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals('Apple Inc', $json[0]['name']);
        $this->assertEquals('Microsoft Corp', $json[1]['name']);
        $this->assertEquals('Zebra Corp', $json[2]['name']);

        // Invalid sort_order falls back to 'asc'
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=name&sort_order=invalid');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals('Apple Inc', $json[0]['name']);
        $this->assertEquals('Microsoft Corp', $json[1]['name']);
        $this->assertEquals('Zebra Corp', $json[2]['name']);

        // Case-insensitive sort_order (DESC)
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=name&sort_order=DESC');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals('Zebra Corp', $json[0]['name']);
        $this->assertEquals('Microsoft Corp', $json[1]['name']);
        $this->assertEquals('Apple Inc', $json[2]['name']);

        // SQL injection attempt in sort_by should fall back to default
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?sort_by=name;DROP TABLE investments--');
        $response->assertStatus(Response::HTTP_OK);
        $json = $response->json();
        $this->assertEquals(3, count($json));
        $this->assertEquals('Apple Inc', $json[0]['name']);
    }
}
