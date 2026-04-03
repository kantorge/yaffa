<?php

namespace Tests\Feature\API\V1;

use App\Models\InvestmentProviderConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'enabled' => true,
                'plan' => 'free',
            ]);

        $response->assertCreated()
            ->assertJsonPath('provider_key', 'alpha_vantage')
            ->assertJsonPath('has_credentials', true)
            ->assertJsonMissingPath('credentials');

        $this->assertDatabaseHas('investment_provider_configs', [
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'enabled' => true,
            'plan' => 'free',
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
            'plan' => 'free',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.investment-provider-configs.update', ['providerKey' => 'alpha_vantage']), [
                'enabled' => false,
                'plan' => 'pro',
            ]);

        $response->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('plan', 'pro');

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
                'enabled' => true,
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

    public function test_update_rejects_out_of_bounds_rate_limit_override(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.investment-provider-configs.update', ['providerKey' => 'alpha_vantage']), [
                'credentials' => [
                    'api_key' => 'alpha-key-12345678',
                ],
                'rate_limit_overrides' => [
                    'perMinute' => 1000,
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rate_limit_overrides.perMinute']);
    }

    public function test_test_endpoint_marks_config_as_validated(): void
    {
        InvestmentProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'credentials' => [
                'api_key' => 'existing-alpha-key',
            ],
            'last_error' => 'Old error',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.investment-provider-configs.test', ['providerKey' => 'alpha_vantage']), []);

        $response->assertOk()
            ->assertJsonPath('message', __('Provider configuration is valid.'));

        $this->assertDatabaseHas('investment_provider_configs', [
            'user_id' => $this->user->id,
            'provider_key' => 'alpha_vantage',
            'last_error' => null,
        ]);
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
