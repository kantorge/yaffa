<?php

namespace Tests\Feature\API\V1;

use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderConfigApiV1Test extends TestCase
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

    // ===== AUTH TESTS =====

    public function test_unauthenticated_cannot_access_v1_show(): void
    {
        $response = $this->getJson(route('api.v1.ai.config.show'));
        $this->assertUserNotAuthorized($response);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_unauthenticated_cannot_access_v1_store(): void
    {
        $response = $this->postJson(route('api.v1.ai.config.store'), [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test-1234567890abcdefghij',
        ]);
        $this->assertUserNotAuthorized($response);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    // ===== HAPPY PATH TESTS =====

    public function test_v1_show_returns_404_when_no_config(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.ai.config.show'));

        $response->assertNotFound()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_v1_show_returns_config_without_api_key(): void
    {
        AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.ai.config.show'));

        $response->assertOk()
            ->assertJsonStructure(['id', 'provider', 'model', 'vision_enabled', 'created_at', 'updated_at'])
            ->assertJsonMissing(['api_key']);
    }

    public function test_v1_store_creates_config(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'provider', 'model', 'vision_enabled'])
            ->assertJsonMissing(['api_key']);

        $this->assertDatabaseHas('ai_provider_configs', [
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);
    }

    public function test_v1_update_modifies_config(): void
    {
        $config = AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ]);

        $response->assertOk()
            ->assertJsonMissing(['api_key'])
            ->assertJson(['model' => 'gpt-4o']);
    }

    public function test_v1_destroy_deletes_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.ai.config.destroy', $config));

        $response->assertNoContent();
        $this->assertDatabaseMissing('ai_provider_configs', ['id' => $config->id]);
    }

    // ===== ERROR FORMAT TESTS =====

    public function test_v1_validation_error_uses_default_validation_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.ai.config.store'), []);

        $response->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['provider', 'model', 'api_key']]);
    }

    public function test_v1_cannot_view_other_users_config(): void
    {
        // Other user has config, current user should see 404 (no config for this user)
        AiProviderConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.ai.config.show'));

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_v1_update_forbidden_for_other_users_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ]);

        $response->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_v1_secret_not_exposed_in_show(): void
    {
        AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'api_key' => 'sk-supersecret-key',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.ai.config.show'));

        $response->assertOk()
            ->assertJsonMissing(['api_key'])
            ->assertJsonMissing(['sk-supersecret-key']);
    }

    public function test_v1_test_fails_with_no_existing_config_uses_error_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.ai.config.test'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => '__existing__',
            ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'CONFIG_NOT_FOUND');
    }
}
