<?php

namespace Tests\Feature;

use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class AiProviderConfigRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    // ===== CREATE (POST /api/v1/ai/config) =====

    public function test_create_requires_provider(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider']);
    }

    public function test_create_requires_model(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'openai',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model']);
    }

    public function test_create_requires_api_key(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['api_key']);
    }

    public function test_create_rejects_invalid_provider(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'invalid-provider',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider']);
    }

    public function test_create_rejects_invalid_model_for_provider(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'openai',
                'model' => 'invalid-model',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model']);
    }

    public function test_create_rejects_unsupported_model_for_provider(): void
    {
        Config::set('ai-documents.providers.gemini.models', [
            'gemini-2.5-flash' => [
                'vision' => true,
                'supported' => true,
            ],
            'gemini-2.5-pro' => [
                'vision' => true,
                'supported' => false,
            ],
        ]);

        Sanctum::actingAs($this->user, ['*']);


        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'gemini',
                'model' => 'gemini-2.5-pro',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model']);
    }

    public function test_create_rejects_short_api_key(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'short',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['api_key']);
    }

    public function test_create_rejects_very_long_api_key(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => str_repeat('x', 501),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['api_key']);
    }

    public function test_create_rejects_invalid_vision_enabled(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-test-1234567890abcdefghij',
                'vision_enabled' => 'yes',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['vision_enabled']);
    }

    public function test_create_prevents_multiple_configs_per_user(): void
    {
        // Create first config
        AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        // Try to create second config
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'gemini',
                'model' => 'gemini-1.5-flash',
                'api_key' => 'test-key-1234567890abcdefghij',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider']);
    }

    // ===== UPDATE (PATCH /api/v1/ai/config/{id}) =====

    public function test_update_requires_provider(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user, ['*']);


        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'model' => 'gpt-4o-mini',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider']);
    }

    public function test_update_requires_model(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user, ['*']);


        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'openai',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model']);
    }

    // These four tests assert only that the request passes validation (200) for each api_key
    // shape update accepts: missing, empty, replaced, and the __existing__ placeholder. The
    // resulting persisted value for each case is asserted with DB checks in
    // AiProviderConfigApiControllerTest (test_update_preserves_api_key_when_not_provided,
    // test_update_preserves_api_key_when_empty, test_update_changes_api_key_when_provided,
    // test_update_preserves_api_key_with_existing_placeholder) - not repeated here.

    public function test_update_allows_missing_api_key(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ]);

        $response->assertStatus(200);
    }

    public function test_update_allows_empty_api_key(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'api_key' => '',
            ]);

        $response->assertStatus(200);
    }

    public function test_update_allows_new_api_key(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'api_key' => 'sk-new-key-1234567890abcdefghij',
            ]);

        $response->assertStatus(200);
    }

    public function test_update_rejects_short_api_key(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user, ['*']);


        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'api_key' => 'short',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['api_key']);
    }

    public function test_update_allows_existing_placeholder(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'api_key' => '__existing__',
            ]);

        $response->assertStatus(200);
    }

    public function test_update_rejects_invalid_vision_enabled(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user, ['*']);


        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'vision_enabled' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['vision_enabled']);
    }

    public function test_update_allows_keeping_existing_unsupported_model(): void
    {
        $config = AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'gemini',
            'model' => 'gemini-2.5-pro',
        ]);

        Sanctum::actingAs($this->user, ['*']);


        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'gemini',
                'model' => 'gemini-2.5-pro',
            ]);

        $response->assertStatus(200);
    }

    public function test_update_rejects_switching_to_unsupported_model(): void
    {
        Config::set('ai-documents.providers.gemini.models', [
            'gemini-2.5-flash' => [
                'vision' => true,
                'supported' => true,
            ],
            'gemini-2.5-pro' => [
                'vision' => true,
                'supported' => false,
            ],
        ]);

        $config = AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        Sanctum::actingAs($this->user, ['*']);


        $response = $this
            ->patchJson(route('api.v1.ai.config.update', $config), [
                'provider' => 'gemini',
                'model' => 'gemini-2.5-pro',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model']);

        $config->refresh();
        $this->assertSame('gemini-2.5-flash', $config->model);
    }

    // ===== TEST CONNECTION (POST /api/v1/ai/test) =====

    public function test_test_connection_requires_provider(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.test'), [
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider']);
    }

    public function test_test_connection_requires_model(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.test'), [
                'provider' => 'openai',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model']);
    }

    public function test_test_connection_requires_api_key(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.test'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['api_key']);
    }

    public function test_test_connection_allows_existing_placeholder(): void
    {
        // This test just validates that the request passes validation with __existing__
        // Actual connection test will fail because the API key won't be valid
        AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user, ['*']);


        $response = $this
            ->postJson(route('api.v1.ai.config.test'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => '__existing__',
            ]);

        // Will fail due to invalid API key, but validation should pass
        $this->assertNotEquals(422, $response->status());
    }

    public function test_test_connection_allows_new_api_key(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this
            ->postJson(route('api.v1.ai.config.test'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]);

        // Will fail due to invalid API key, but validation should pass
        $this->assertNotEquals(422, $response->status());
    }
}
