<?php

namespace Tests\Feature;

use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;
use DB;

class AiProviderConfigApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    // ===== AUTHORIZATION =====

    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson(
            route('api.v1.ai.config.show')
        );
        // Unauthenticated requests return 403 when authorization check fails
        $this->assertUserNotAuthorized($response);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson(
            route('api.v1.ai.config.store'),
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-test-1234567890abcdefghij',
            ]
        );
        // Unauthenticated requests return 403 when authorization check fails
        $this->assertUserNotAuthorized($response);

    }

    public function test_update_requires_authentication(): void
    {
        $config = AiProviderConfig::factory()->create();
        $response = $this->patchJson(route('api.v1.ai.config.update', ['aiProviderConfig' => $config->id]));
        // Unauthenticated requests return 403 when authorization check fails
        $this->assertUserNotAuthorized($response);
    }

    public function test_destroy_requires_authentication(): void
    {
        $config = AiProviderConfig::factory()->create();
        $response = $this->deleteJson(route('api.v1.ai.config.destroy', ['aiProviderConfig' => $config->id]));
        // Unauthenticated requests return 403 when authorization check fails
        $this->assertUserNotAuthorized($response);
    }

    public function test_show_cannot_view_other_users_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.ai.config.show'));

        $response->assertStatus(404);
    }

    public function test_update_cannot_modify_other_users_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.ai.config.update', ['aiProviderConfig' => $config->id]), [
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash',
            ]);

        // Should not find the resource since it's not the user's
        $this->assertUserNotAuthorized($response);
    }

    public function test_destroy_cannot_delete_other_users_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('api.v1.ai.config.destroy', ['aiProviderConfig' => $config->id]));

        // Should not find the resource since it's not the user's
        $this->assertUserNotAuthorized($response);
    }

    // ===== SHOW ENDPOINT (GET /api/v1/ai/config) =====

    public function test_show_returns_404_when_no_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.ai.config.show'));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_show_returns_config_without_api_key(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.ai.config.show'));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['id', 'provider', 'model', 'vision_enabled', 'created_at', 'updated_at']);
        $response->assertJson([
            'id' => $config->id,
            'provider' => $config->provider,
            'model' => $config->model,
            'vision_enabled' => $config->vision_enabled,
        ]);
        $response->assertJsonMissing(['api_key']);
    }

    // ===== STORE ENDPOINT (POST /api/v1/ai/config) =====

    public function test_store_creates_new_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-test-1234567890abcdefghij',
                'vision_enabled' => true,
            ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure(['id', 'provider', 'model', 'vision_enabled']);
        $response->assertJson([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'vision_enabled' => true,
        ]);

        $this->assertDatabaseHas('ai_provider_configs', [
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'vision_enabled' => true,
        ]);
    }

    public function test_store_prevents_multiple_configs(): void
    {
        // Create initial config
        AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        // Try to create second config
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash',
                'api_key' => 'test-key-1234567890abcdefghij',
            ]);

        // Should get validation error
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['provider']);
    }

    public function test_store_encrypts_api_key(): void
    {
        $plainKey = 'sk-test-1234567890abcdefghij';

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.ai.config.store'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => $plainKey,
            ]);

        $response->assertStatus(Response::HTTP_CREATED);

        // Verify encrypted value in database differs from plaintext
        $rawValue = DB::table('ai_provider_configs')
            ->where('user_id', $this->user->id)
            ->value('api_key');

        $this->assertNotEquals($plainKey, $rawValue);

        // Verify decrypted value matches through model
        $config = AiProviderConfig::where('user_id', $this->user->id)->first();
        $this->assertEquals($plainKey, $config->api_key);
    }

    // ===== UPDATE ENDPOINT (PATCH /api/v1/ai/config/{id}) =====

    public function test_update_changes_provider_and_model(): void
    {
        $config = AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);

        // Verify config was created with correct user_id
        $this->assertNotNull($config->user_id);
        $this->assertEquals($this->user->id, $config->user_id);
        $this->assertNotNull($config->id);

        // Verify config exists in database
        $this->assertDatabaseHas('ai_provider_configs', [
            'id' => $config->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.ai.config.update', ['aiProviderConfig' => $config->id]), [
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash',
                'api_key' => '',
            ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $config->refresh();
        $this->assertEquals('gemini', $config->provider);
        $this->assertEquals('gemini-2.5-flash', $config->model);
    }

    public function test_update_changes_vision_enabled(): void
    {
        $config = AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'vision_enabled' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.ai.config.update', ['aiProviderConfig' => $config->id]), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'vision_enabled' => true,
            ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'vision_enabled' => true,
        ]);

        $config->refresh();
        $this->assertTrue($config->vision_enabled);
    }

    public function test_update_preserves_api_key_when_not_provided(): void
    {
        $originalKey = 'sk-original-1234567890abcdefghij';
        $config = AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'api_key' => $originalKey,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.ai.config.update', ['aiProviderConfig' => $config->id]), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ]);

        $response->assertStatus(Response::HTTP_OK);

        $config->refresh();
        $this->assertEquals($originalKey, $config->api_key);
    }

    public function test_update_preserves_api_key_when_empty(): void
    {
        $originalKey = 'sk-original-1234567890abcdefghij';
        $config = AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'api_key' => $originalKey,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.ai.config.update', ['aiProviderConfig' => $config->id]), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'api_key' => '',
            ]);

        $response->assertStatus(Response::HTTP_OK);

        $config->refresh();
        $this->assertEquals($originalKey, $config->api_key);
    }

    public function test_update_preserves_api_key_with_existing_placeholder(): void
    {
        $originalKey = 'sk-original-1234567890abcdefghij';
        $config = AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'api_key' => $originalKey,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.ai.config.update', ['aiProviderConfig' => $config->id]), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'api_key' => '__existing__',
            ]);

        $response->assertStatus(Response::HTTP_OK);

        $config->refresh();
        $this->assertEquals($originalKey, $config->api_key);
    }

    public function test_update_changes_api_key_when_provided(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);
        $newKey = 'sk-new-key-1234567890abcdefghij';

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.ai.config.update', ['aiProviderConfig' => $config->id]), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => $newKey,
            ]);

        $response->assertStatus(Response::HTTP_OK);

        $config->refresh();
        $this->assertEquals($newKey, $config->api_key);
    }

    public function test_update_returns_no_api_key(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.ai.config.update', ['aiProviderConfig' => $config->id]), [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonMissing(['api_key']);
    }

    // ===== DESTROY ENDPOINT (DELETE /api/v1/ai/config/{id}) =====

    public function test_destroy_deletes_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('api.v1.ai.config.destroy', ['aiProviderConfig' => $config->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('ai_provider_configs', ['id' => $config->id]);
    }

    public function test_destroy_returns_no_content(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('api.v1.ai.config.destroy', ['aiProviderConfig' => $config->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $response->assertNoContent();
    }

    // ===== TEST CONNECTION ENDPOINT (POST /api/v1/ai/test) =====

    public function test_test_fails_with_invalid_api_key(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.ai.config.test'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-invalid-key-12345',
            ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_test_fails_with_existing_placeholder_and_no_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.ai.config.test'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => '__existing__',
            ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson([
            'error' => [
                'code' => 'CONFIG_NOT_FOUND',
                'message' => __('No existing AI provider configuration found'),
            ],
        ]);
    }

    public function test_test_uses_existing_placeholder_to_fetch_key(): void
    {
        // Create a config with a fake key
        AiProviderConfig::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-invalid-but-existing-key',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.ai.config.test'), [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => '__existing__',
            ]);

        // Should fail because API key is invalid, but should attempt to use the existing key
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        // Should not be the "No existing config" error code
        $response->assertJsonMissing(['error' => ['code' => 'CONFIG_NOT_FOUND']]);
    }
}
