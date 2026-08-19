<?php

namespace Tests\Feature\API;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvestmentApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_delete_own_investment(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $investment = $this->createInvestmentForUser($this->user);

        $response = $this->deleteJson(route('api.v1.investments.destroy', $investment));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'investment' => [
                'id' => $investment->id,
            ],
        ]);

        $this->assertDatabaseMissing('investments', [
            'id' => $investment->id,
        ]);
    }

    public function test_cannot_delete_other_users_investment(): void
    {
        $otherUser = User::factory()->create();
        $investment = $this->createInvestmentForUser($this->user);

        Sanctum::actingAs($otherUser, ['*']);

        $response = $this->deleteJson(route('api.v1.investments.destroy', $investment));

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('investments', [
            'id' => $investment->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_update_provider_settings_for_web_scraping_investment(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $investment = $this->createInvestmentForUser($this->user);
        $investment->update([
            'investment_price_provider' => 'web_scraping',
        ]);

        $response = $this->patchJson(
            route('api.v1.investments.provider-settings.update', $investment),
            [
                'provider_settings' => [
                    'url' => 'https://example.com/price',
                    'selector' => '.price',
                    'decimal_separator' => ',',
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('provider_settings.url', 'https://example.com/price')
            ->assertJsonPath('provider_settings.selector', '.price')
            ->assertJsonPath('provider_settings.decimal_separator', ',');

        $investment->refresh();

        $this->assertSame('https://example.com/price', $investment->provider_settings['url']);
        $this->assertSame('.price', $investment->provider_settings['selector']);
        $this->assertSame(',', $investment->provider_settings['decimal_separator']);
    }

    public function test_provider_settings_update_validates_selected_provider_schema(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $investment = $this->createInvestmentForUser($this->user);
        $investment->update([
            'investment_price_provider' => 'web_scraping',
        ]);

        $response = $this->patchJson(
            route('api.v1.investments.provider-settings.update', $investment),
            [
                'provider_settings' => [
                    'url' => 'https://example.com/price',
                ],
            ],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider_settings.selector']);
    }

    public function test_show_returns_provider_settings_payload(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $investment = $this->createInvestmentForUser($this->user);
        $investment->update([
            'investment_price_provider' => 'web_scraping',
            'provider_settings' => [
                'url' => 'https://example.com/price',
                'selector' => '.price',
                'decimal_separator' => ',',
            ],
        ]);

        $response = $this->getJson(route('api.v1.investments.show', $investment));

        $response->assertOk()
            ->assertJsonPath('provider_settings.url', 'https://example.com/price')
            ->assertJsonPath('provider_settings.selector', '.price')
            ->assertJsonPath('provider_settings.decimal_separator', ',');
    }

    public function test_timeline_resolves_closed_and_open_holding_periods_with_prices(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $investment = $this->createInvestmentForUser($this->user);
        $account = AccountEntity::factory()
            ->for($this->user)
            ->for(
                Account::factory()->create([
                    'currency_id' => $investment->currency_id,
                    'account_group_id' => AccountGroup::factory()->for($this->user)->create()->id,
                ]),
                'config'
            )
            ->create();

        InvestmentPrice::factory()->for($investment)->create(['date' => '2024-01-10', 'price' => 100.00]);
        InvestmentPrice::factory()->for($investment)->create(['date' => '2024-02-15', 'price' => 120.00]);

        // Build TransactionDetailInvestment rows directly: the factory's definition() has a
        // side effect that spins up an unrelated scratch user/account/currency on every call,
        // which isn't needed here and can collide with the currencies just created above.
        $buyConfig = fn (float $quantity) => TransactionDetailInvestment::create([
            'account_id' => $account->id,
            'investment_id' => $investment->id,
            'price' => null,
            'quantity' => $quantity,
            'commission' => null,
            'tax' => null,
            'dividend' => null,
        ]);

        // Closed holding period: bought then fully sold.
        Transaction::factory()
            ->for($this->user)
            ->for($buyConfig(10), 'config')
            ->create(['date' => '2024-01-05', 'transaction_type' => 'buy', 'schedule' => false]);

        Transaction::factory()
            ->for($this->user)
            ->for($buyConfig(10), 'config')
            ->create(['date' => '2024-01-18', 'transaction_type' => 'sell', 'schedule' => false]);

        // Open holding period: bought, never sold.
        Transaction::factory()
            ->for($this->user)
            ->for($buyConfig(5), 'config')
            ->create(['date' => '2024-02-01', 'transaction_type' => 'buy', 'schedule' => false]);

        $response = $this->getJson(route('api.v1.investments.timeline'));

        $response->assertOk();

        $positions = $response->json();
        $this->assertCount(2, $positions);

        $closedPeriod = collect($positions)->firstWhere('end', '2024-01-18');
        $this->assertNotNull($closedPeriod);
        $this->assertEquals(10, $closedPeriod['quantity']);
        $this->assertEquals(100.00, $closedPeriod['last_price']);

        $openPeriod = collect($positions)->firstWhere('end', '!=', '2024-01-18');
        $this->assertNotNull($openPeriod);
        $this->assertEquals(5, $openPeriod['quantity']);
        $this->assertEquals(120.00, $openPeriod['last_price']);
    }

    public function test_price_history_returns_prices_without_investment_relation(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $investment = $this->createInvestmentForUser($this->user);
        InvestmentPrice::factory()->for($investment)->create(['date' => '2024-01-10', 'price' => '100.5000000000']);
        InvestmentPrice::factory()->for($investment)->create(['date' => '2024-02-15', 'price' => '120.2500000000']);

        $response = $this->getJson(route('api.v1.investments.price-history', $investment));

        $response->assertOk();

        $prices = $response->json();
        $this->assertCount(2, $prices);
        $this->assertSame('2024-01-10', $prices[0]['date']);
        $this->assertSame('100.5000000000', $prices[0]['price']);
        $this->assertSame('2024-02-15', $prices[1]['date']);
        $this->assertSame('120.2500000000', $prices[1]['price']);

        foreach ($prices as $price) {
            $this->assertArrayNotHasKey('investment_id', $price);
            $this->assertArrayNotHasKey('investment', $price);
        }
    }

    public function test_display_data_returns_prices_without_investment_relation(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $investment = $this->createInvestmentForUser($this->user);
        InvestmentPrice::factory()->for($investment)->create(['date' => '2024-01-10', 'price' => '100.5000000000']);

        $response = $this->getJson(route('api.v1.investments.display-data', $investment));

        $response->assertOk()
            ->assertJsonPath('investment.id', $investment->id);

        $prices = $response->json('prices');
        $this->assertCount(1, $prices);
        $this->assertSame('2024-01-10', $prices[0]['date']);
        $this->assertSame('100.5000000000', $prices[0]['price']);
        $this->assertArrayNotHasKey('investment_id', $prices[0]);
        $this->assertArrayNotHasKey('investment', $prices[0]);
    }

    private function createInvestmentForUser(User $user): Investment
    {
        $currency = $user->currencies()->first() ?? Currency::factory()->for($user)->create();
        $investmentGroup = $user->investmentGroups()->first() ?? InvestmentGroup::factory()->for($user)->create();

        return Investment::factory()->create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);
    }

    private const BASE_INVESTMENT_NAME = 'Same investment name';
    private const BASE_API_ENDPOINT = '/api/v1/investments';

    public function test_unauthenticated_users_cannot_access_investment_list(): void
    {
        $response = $this->getJson(self::BASE_API_ENDPOINT);

        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(Response::HTTP_UNAUTHORIZED),
                $this->equalTo(Response::HTTP_FORBIDDEN)
            )
        );
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
                    'name' => self::BASE_INVESTMENT_NAME . ' - clone - ' . $i,
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

    public function test_investment_list_search_treats_zero_as_a_real_search_value(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, Currency::class);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Zero Symbol Investment',
                'symbol' => '0',
            ]);

        Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'active' => true,
                'name' => 'Unrelated Investment',
                'symbol' => 'MSFT',
            ]);

        // 'query' alias with a literal "0" must still filter, not be treated as absent
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?query=0');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.symbol', '0');

        // 'q' alias with a literal "0" must behave the same way
        $response = $this->actingAs($user)->getJson(self::BASE_API_ENDPOINT . '?q=0');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.symbol', '0');
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
        Sanctum::actingAs($user1, ['*']);
        $response = $this->getJson(self::BASE_API_ENDPOINT);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1, count($response->json()));
        $response->assertJsonPath('0.user_id', $user1->id);

        // User2 should only see their own investment
        Sanctum::actingAs($user2, ['*']);
        $response = $this->getJson(self::BASE_API_ENDPOINT);
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
