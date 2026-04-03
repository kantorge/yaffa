<?php

namespace Tests\Feature\API\V1;

use App\Models\InvestmentProviderConfig;
use App\Models\User;
use App\Contracts\InvestmentPriceProvider;
use App\Services\InvestmentPriceProviderContextResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InvestmentPriceProviderApiV1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_cannot_access_provider_availability_endpoint(): void
    {
        $response = $this->getJson(route('api.v1.investment-price-providers.available'));

        $this->assertUserNotAuthorized($response);
    }

    public function test_unauthenticated_cannot_access_provider_test_fetch_endpoint(): void
    {
        $response = $this->postJson(route('api.v1.investment-price-providers.test-fetch', [
            'providerKey' => 'web_scraping',
        ]), [
            'provider_settings' => [
                'url' => 'https://example.com',
                'selector' => '.price',
            ],
        ]);

        $this->assertUserNotAuthorized($response);
    }

    public function test_available_endpoint_returns_only_selectable_providers_by_default(): void
    {
        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'credentials' => [
                'api_key' => 'alpha-key-12345678',
            ],
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-price-providers.available'));

        $response->assertOk();
        $response->assertJsonCount(2);

        $providers = collect($response->json())->keyBy('key');

        $this->assertTrue($providers->has('alpha_vantage'));
        $this->assertTrue($providers->has('web_scraping'));
        $this->assertTrue($providers->get('alpha_vantage')['available']);
        $this->assertSame('Configured', $providers->get('alpha_vantage')['statusLabel']);
        $this->assertTrue($providers->get('web_scraping')['available']);
    }

    public function test_available_endpoint_can_include_unavailable_providers_with_reason_flags(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-price-providers.available', ['include_unavailable' => 1]));

        $response->assertOk();

        $providers = collect($response->json())->keyBy('key');

        $this->assertTrue($providers->has('alpha_vantage'));
        $this->assertFalse($providers->get('alpha_vantage')['available']);
        $this->assertSame('Setup required', $providers->get('alpha_vantage')['statusLabel']);
        $this->assertSame(['setup_required'], $providers->get('alpha_vantage')['reasonFlags']);
        $this->assertTrue($providers->has('web_scraping'));
        $this->assertTrue($providers->get('web_scraping')['available']);
    }

    public function test_test_fetch_requires_symbol_for_all_providers(): void
    {
        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'credentials' => [
                'api_key' => 'alpha-key-12345678',
            ],
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-price-providers.test-fetch', [
                'providerKey' => 'web_scraping',
            ]), [
                'symbol' => '',
                'provider_settings' => [
                    'url' => 'https://example.com/price',
                    'selector' => '.price',
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol']);
    }

    public function test_test_fetch_returns_latest_price_for_web_scraping_provider(): void
    {
        $olderDate = Carbon::yesterday()->subDay()->format('Y-m-d');
        $newerDate = Carbon::yesterday()->format('Y-m-d');

        $provider = Mockery::mock(InvestmentPriceProvider::class);
        $provider->shouldReceive('fetchPrices')
            ->once()
            ->andReturnUsing(fn () => [
                [
                    'date' => $newerDate,
                    'price' => 123.45,
                ],
                [
                    'date' => $olderDate,
                    'price' => 111.11,
                ],
            ]);

        $contextResolver = Mockery::mock(InvestmentPriceProviderContextResolver::class);
        $contextResolver->shouldReceive('resolve')
            ->once()
            ->andReturn([
                'provider' => $provider,
                'credentials' => [],
            ]);

        $this->app->instance(InvestmentPriceProviderContextResolver::class, $contextResolver);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-price-providers.test-fetch', [
                'providerKey' => 'web_scraping',
            ]), [
                'symbol' => 'DIS',
                'provider_settings' => [
                    'url' => 'https://example.com/price',
                    'selector' => '.price',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('provider_key', 'web_scraping')
            ->assertJsonPath('price', 123.45)
            ->assertJsonPath('date', $newerDate);
    }

    public function test_test_fetch_passes_provider_settings_to_provider_fetch(): void
    {
        $olderDate = Carbon::yesterday()->subDay()->format('Y-m-d');
        $newerDate = Carbon::yesterday()->format('Y-m-d');

        $provider = Mockery::mock(InvestmentPriceProvider::class);
        $provider->shouldReceive('fetchPrices')
            ->once()
            ->withArgs(fn ($investment): bool => is_array($investment->provider_settings)
                    && ($investment->provider_settings['url'] ?? null) === 'https://example.com/live'
                    && ($investment->provider_settings['selector'] ?? null) === '.quote')
            ->andReturnUsing(fn () => [
                [
                    'date' => $newerDate,
                    'price' => 456.78,
                ],
                [
                    'date' => $olderDate,
                    'price' => 400.00,
                ],
            ]);

        $contextResolver = Mockery::mock(InvestmentPriceProviderContextResolver::class);
        $contextResolver->shouldReceive('resolve')
            ->once()
            ->andReturn([
                'provider' => $provider,
                'credentials' => [],
            ]);

        $this->app->instance(InvestmentPriceProviderContextResolver::class, $contextResolver);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-price-providers.test-fetch', [
                'providerKey' => 'web_scraping',
            ]), [
                'symbol' => 'DIS',
                'provider_settings' => [
                    'url' => 'https://example.com/live',
                    'selector' => '.quote',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('provider_key', 'web_scraping')
            ->assertJsonPath('price', 456.78)
            ->assertJsonPath('date', $newerDate)
            ->assertJsonPath('symbol', 'DIS');
    }
}
