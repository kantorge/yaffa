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
        Sanctum::actingAs($this->user);

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

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson(route('api.v1.investments.destroy', $investment));

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('investments', [
            'id' => $investment->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_update_provider_settings_for_web_scraping_investment(): void
    {
        Sanctum::actingAs($this->user);

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
        Sanctum::actingAs($this->user);

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
        Sanctum::actingAs($this->user);

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
        Sanctum::actingAs($this->user);

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
}
