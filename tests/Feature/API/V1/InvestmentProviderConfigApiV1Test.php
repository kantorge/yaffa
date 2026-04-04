<?php

namespace Tests\Feature\API\V1;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\PriceProviderException;
use App\Models\InvestmentProviderConfig;
use App\Models\User;
use App\Services\InvestmentPriceProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InvestmentProviderConfigApiV1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_unauthenticated_cannot_access_provider_config_endpoints(): void
    {
        $indexResponse = $this->getJson(route('api.v1.investment-provider-configs.index'));
        $showResponse = $this->getJson(route('api.v1.investment-provider-configs.show', ['providerKey' => 'alpha_vantage']));
        $updateResponse = $this->patchJson(route('api.v1.investment-provider-configs.update', ['providerKey' => 'alpha_vantage']));
        $destroyResponse = $this->deleteJson(route('api.v1.investment-provider-configs.destroy', ['providerKey' => 'alpha_vantage']));

        $this->assertUserNotAuthorized($indexResponse);
        $this->assertUserNotAuthorized($showResponse);
        $this->assertUserNotAuthorized($updateResponse);
        $this->assertUserNotAuthorized($destroyResponse);
    }

    public function test_index_returns_only_authenticated_users_configs_without_credentials(): void
    {
        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
        ]);

        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->otherUser->id,
            'provider_key' => 'alpha_vantage',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('api.v1.investment-provider-configs.index'));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.provider_key', 'alpha_vantage');
        $response->assertJsonMissingPath('0.credentials');
    }

    public function test_show_returns_not_found_when_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.investment-provider-configs.show', ['providerKey' => 'alpha_vantage']));

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_update_creates_provider_config_and_hides_credentials_in_response(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.investment-provider-configs.update', ['providerKey' => 'alpha_vantage']), [
                'credentials' => [
                    'api_key' => 'alpha-key-12345678',
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('provider_key', 'alpha_vantage')
            ->assertJsonPath('has_credentials', true)
            ->assertJsonMissingPath('credentials');

        $this->assertDatabaseHas('investment_provider_configs', [
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
        ]);
    }

    public function test_update_allows_partial_credentials_update(): void
    {
        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'credentials' => [
                'api_key' => 'existing-alpha-key',
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.investment-provider-configs.update', ['providerKey' => 'alpha_vantage']), [
            ]);

        $response->assertOk();

        /** @var InvestmentProviderConfig $config */
        $config = InvestmentProviderConfig::query()
            ->where('user_id', $this->user->id)
            ->where('provider_key', 'alpha_vantage')
            ->firstOrFail();

        $this->assertEquals('existing-alpha-key', $config->credentials['api_key'] ?? null);
    }

    public function test_update_fails_for_unknown_provider_key(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.investment-provider-configs.update', ['providerKey' => 'unknown_provider']), [
                'credentials' => ['api_key' => 'abc123456'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider_key']);
    }

    public function test_update_validates_required_credentials_for_alpha_vantage(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.investment-provider-configs.update', ['providerKey' => 'alpha_vantage']), [
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['credentials.api_key']);
    }

    public function test_update_rejects_overrides_for_non_overrideable_provider(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.investment-provider-configs.update', ['providerKey' => 'web_scraping']), [
                'rate_limit_overrides' => [
                    'perMinute' => 10,
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rate_limit_overrides']);
    }

    public function test_test_endpoint_marks_config_as_validated(): void
    {
        $provider = Mockery::mock(InvestmentPriceProvider::class);
        $provider->shouldReceive('validateCredentials')
            ->once()
            ->withArgs(fn (array $credentials): bool => ($credentials['api_key'] ?? null) === 'existing-alpha-key')
            ->andReturnNull();

        $registry = Mockery::mock(InvestmentPriceProviderRegistry::class);
        $registry->shouldReceive('has')->with('alpha_vantage')->andReturn(true);
        $registry->shouldReceive('getMetadata')->with('alpha_vantage')->andReturn([
            'userSettingsSchema' => [
                'required' => ['api_key'],
            ],
        ]);
        $registry->shouldReceive('get')->with('alpha_vantage')->andReturn($provider);
        $this->app->instance(InvestmentPriceProviderRegistry::class, $registry);

        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'credentials' => [
                'api_key' => 'existing-alpha-key',
            ],
            'last_error' => 'Old error',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-provider-configs.test', ['providerKey' => 'alpha_vantage']), [
                'persist' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', __('Provider configuration is valid.'));

        $this->assertDatabaseHas('investment_provider_configs', [
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'last_error' => null,
        ]);
    }

    public function test_test_endpoint_returns_bad_request_and_persists_last_error_when_provider_validation_fails(): void
    {
        $provider = Mockery::mock(InvestmentPriceProvider::class);
        $provider->shouldReceive('validateCredentials')
            ->once()
            ->andThrow(new PriceProviderException('Invalid API key', 'alpha_vantage'));

        $registry = Mockery::mock(InvestmentPriceProviderRegistry::class);
        $registry->shouldReceive('has')->with('alpha_vantage')->andReturn(true);
        $registry->shouldReceive('getMetadata')->with('alpha_vantage')->andReturn([
            'userSettingsSchema' => [
                'required' => ['api_key'],
            ],
        ]);
        $registry->shouldReceive('get')->with('alpha_vantage')->andReturn($provider);
        $this->app->instance(InvestmentPriceProviderRegistry::class, $registry);

        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'credentials' => [
                'api_key' => 'existing-alpha-key',
            ],
            'last_error' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-provider-configs.test', ['providerKey' => 'alpha_vantage']), [
                'persist' => true,
            ]);

        $response->assertBadRequest()
            ->assertJsonPath('error.code', 'CREDENTIAL_VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Invalid API key');

        $this->assertDatabaseHas('investment_provider_configs', [
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'last_error' => 'Invalid API key',
        ]);
    }

    public function test_update_clears_rate_limit_overrides_when_sending_empty_overrides(): void
    {
        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'credentials' => [
                'api_key' => 'existing-alpha-key',
            ],
            'rate_limit_overrides' => [
                'perMinute' => 5,
                'perDay' => 100,
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.investment-provider-configs.update', ['providerKey' => 'alpha_vantage']), [
                'rate_limit_overrides' => [],
            ]);

        $response->assertOk();

        /** @var InvestmentProviderConfig $config */
        $config = InvestmentProviderConfig::query()
            ->where('user_id', $this->user->id)
            ->where('provider_key', 'alpha_vantage')
            ->firstOrFail();

        $this->assertEmpty($config->rate_limit_overrides);
    }

    public function test_destroy_deletes_existing_provider_config(): void
    {
        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.investment-provider-configs.destroy', ['providerKey' => 'alpha_vantage']));

        $response->assertNoContent();

        $this->assertDatabaseMissing('investment_provider_configs', [
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
        ]);
    }

    public function test_destroy_returns_no_content_when_provider_config_is_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.investment-provider-configs.destroy', ['providerKey' => 'alpha_vantage']));

        $response->assertNoContent();
    }
}
