<?php

namespace Tests\Feature;

use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response = $this->getJson('/api/ai/config');
        // Unauthenticated requests return 403 when authorization check fails
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(401),
                $this->equalTo(403)
            )
        );
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/ai/config', [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test-1234567890abcdefghij',
        ]);
        // Unauthenticated requests return 403 when authorization check fails
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(401),
                $this->equalTo(403)
            )
        );
    }

    public function test_update_requires_authentication(): void
    {
        $config = AiProviderConfig::factory()->create();
        $response = $this->patchJson("/api/ai/config/{$config->id}");
        // Unauthenticated requests return 403 when authorization check fails
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(401),
                $this->equalTo(403)
            )
        );
    }

    public function test_destroy_requires_authentication(): void
    {
        $config = AiProviderConfig::factory()->create();
        $response = $this->deleteJson("/api/ai/config/{$config->id}");
        // Unauthenticated requests return 403 when authorization check fails
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(401),
                $this->equalTo(403)
            )
        );
    }

    public function test_show_cannot_view_other_users_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/ai/config');

        $response->assertStatus(404);
    }

    public function test_update_cannot_modify_other_users_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/ai/config/{$config->id}", [
                'provider' => 'gemini',
                'model' => 'gemini-1.5-flash',
            ]);

        // Should not find the resource since it's not the user's
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    public function test_destroy_cannot_delete_other_users_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/ai/config/{$config->id}");

        // Should not find the resource since it's not the user's
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    // ===== SHOW ENDPOINT (GET /api/ai/config) =====

    public function test_show_returns_404_when_no_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/ai/config');

        $response->assertStatus(404);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_show_returns_config_without_api_key(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/ai/config');

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'provider', 'model', 'vision_enabled', 'created_at', 'updated_at']);
        $response->assertJson([
            'id' => $config->id,
            'provider' => $config->provider,
            'model' => $config->model,
            'vision_enabled' => $config->vision_enabled,
        ]);
        $response->assertJsonMissing(['api_key']);
    }

    // ===== STORE ENDPOINT (POST /api/ai/config) =====

    public function test_store_creates_new_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/config', [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-test-1234567890abcdefghij',
                'vision_enabled' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'provider', 'model', 'vision_enabled', 'message']);
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
            ->postJson('/api/ai/config', [
                'provider' => 'gemini',
                'model' => 'gemini-1.5-flash',
                'api_key' => 'test-key-1234567890abcdefghij',
            ]);

        // Should get validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider']);
    }

    public function test_store_encrypts_api_key(): void
    {
        $plainKey = 'sk-test-1234567890abcdefghij';

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/config', [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => $plainKey,
            ]);

        $response->assertStatus(201);

        // Verify encrypted value in database differs from plaintext
        $rawValue = DB::table('ai_provider_configs')
            ->where('user_id', $this->user->id)
            ->value('api_key');

        $this->assertNotEquals($plainKey, $rawValue);

        // Verify decrypted value matches through model
        $config = AiProviderConfig::where('user_id', $this->user->id)->first();
        $this->assertEquals($plainKey, $config->api_key);
    }

    // ===== UPDATE ENDPOINT (PATCH /api/ai/config/{id}) =====

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
            ->patchJson("/api/ai/config/{$config->id}", [
                'provider' => 'gemini',
                'model' => 'gemini-1.5-flash',
                'api_key' => '',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'provider' => 'gemini',
            'model' => 'gemini-1.5-flash',
        ]);

        $config->refresh();
        $this->assertEquals('gemini', $config->provider);
        $this->assertEquals('gemini-1.5-flash', $config->model);
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
            ->patchJson("/api/ai/config/{$config->id}", [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'vision_enabled' => true,
            ]);

        $response->assertStatus(200);
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
            ->patchJson("/api/ai/config/{$config->id}", [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ]);

        $response->assertStatus(200);

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
            ->patchJson("/api/ai/config/{$config->id}", [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'api_key' => '',
            ]);

        $response->assertStatus(200);

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
            ->patchJson("/api/ai/config/{$config->id}", [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'api_key' => '__existing__',
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($originalKey, $config->api_key);
    }

    public function test_update_changes_api_key_when_provided(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);
        $newKey = 'sk-new-key-1234567890abcdefghij';

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/ai/config/{$config->id}", [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => $newKey,
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($newKey, $config->api_key);
    }

    public function test_update_returns_no_api_key(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/ai/config/{$config->id}", [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ]);

        $response->assertStatus(200);
        $response->assertJsonMissing(['api_key']);
    }

    // ===== DESTROY ENDPOINT (DELETE /api/ai/config/{id}) =====

    public function test_destroy_deletes_config(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/ai/config/{$config->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('ai_provider_configs', ['id' => $config->id]);
    }

    public function test_destroy_returns_no_content(): void
    {
        $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/ai/config/{$config->id}");

        $response->assertStatus(204);
        $response->assertNoContent();
    }

    // ===== TEST CONNECTION ENDPOINT (POST /api/ai/test) =====

    public function test_test_fails_with_invalid_api_key(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/test', [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-invalid-key-12345',
            ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_test_fails_with_existing_placeholder_and_no_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/test', [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => '__existing__',
            ]);

        $response->assertStatus(400);
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
            ->postJson('/api/ai/test', [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => '__existing__',
            ]);

        // Should fail because API key is invalid, but should attempt to use the existing key
        $response->assertStatus(400);
        // Should not be the "No existing config" error code
        $response->assertJsonMissing(['error' => ['code' => 'CONFIG_NOT_FOUND']]);
    }
}
