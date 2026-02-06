<?php

namespace Tests\Feature;

use App\Models\GoogleDriveConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDriveConfigRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    private const VALID_SERVICE_ACCOUNT_JSON = '{"type":"service_account","project_id":"test-project","private_key_id":"key123","private_key":"-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----","client_email":"test@test-project.iam.gserviceaccount.com","client_id":"123456789","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token"}';

    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    // ===== CREATE (POST /api/google-drive/config) =====

    public function test_create_requires_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_requires_folder_id(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['folder_id']);
    }

    public function test_create_rejects_short_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'service_account_json' => 'too short',
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_rejects_very_long_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'service_account_json' => str_repeat('x', 5001),
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_rejects_invalid_json_format(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'service_account_json' => '{"invalid": "json", missing bracket',
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_rejects_json_missing_required_keys(): void
    {
        $invalidJson = '{"type":"service_account","project_id":"test"}'; // Missing most required keys

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'service_account_json' => $invalidJson,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_accepts_valid_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(201);
    }

    public function test_create_prevents_multiple_configs_per_user(): void
    {
        GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'another-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['folder_id']);
    }

    public function test_create_accepts_delete_after_import_boolean(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
                'delete_after_import' => true,
            ]);

        $response->assertStatus(201);
    }

    public function test_create_accepts_enabled_boolean(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/config', [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
                'enabled' => false,
            ]);

        $response->assertStatus(201);
    }

    // ===== UPDATE (PATCH /api/google-drive/config/{id}) =====

    public function test_update_allows_missing_folder_id(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'original-folder-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/google-drive/config/{$config->id}", [
                'delete_after_import' => true,
            ]);

        $response->assertStatus(200);

        // Verify folder_id was not changed
        $config->refresh();
        $this->assertEquals('original-folder-id', $config->folder_id);
    }

    public function test_update_allows_missing_service_account_json(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);
        $originalJson = $config->service_account_json;

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/google-drive/config/{$config->id}", [
                'folder_id' => 'new-folder-id',
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($originalJson, $config->service_account_json);
    }

    public function test_update_allows_empty_service_account_json(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);
        $originalJson = $config->service_account_json;

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/google-drive/config/{$config->id}", [
                'folder_id' => 'new-folder-id',
                'service_account_json' => '',
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($originalJson, $config->service_account_json);
    }

    public function test_update_allows_new_service_account_json(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);
        $newJson = '{"type":"service_account","project_id":"new-project","private_key_id":"newkey","private_key":"-----BEGIN PRIVATE KEY-----\nnewtest\n-----END PRIVATE KEY-----","client_email":"new@new-project.iam.gserviceaccount.com","client_id":"987654321","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token"}';

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/google-drive/config/{$config->id}", [
                'folder_id' => 'new-folder-id',
                'service_account_json' => $newJson,
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($newJson, $config->service_account_json);
    }

    public function test_update_rejects_invalid_service_account_json(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/google-drive/config/{$config->id}", [
                'folder_id' => 'new-folder-id',
                'service_account_json' => '{"invalid": json}',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_update_allows_existing_placeholder(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);
        $originalJson = $config->service_account_json;

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/google-drive/config/{$config->id}", [
                'folder_id' => 'new-folder-id',
                'service_account_json' => '__existing__',
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($originalJson, $config->service_account_json);
    }

    public function test_update_allows_changing_delete_after_import(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'delete_after_import' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/google-drive/config/{$config->id}", [
                'folder_id' => $config->folder_id,
                'delete_after_import' => true,
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertTrue($config->delete_after_import);
    }

    public function test_update_allows_changing_enabled(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/google-drive/config/{$config->id}", [
                'folder_id' => $config->folder_id,
                'enabled' => false,
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertFalse($config->enabled);
    }

    // ===== TEST CONNECTION (POST /api/google-drive/test) =====

    public function test_test_connection_requires_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/test', [
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_test_connection_requires_folder_id(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/test', [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['folder_id']);
    }

    public function test_test_connection_allows_existing_placeholder(): void
    {
        GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/test', [
                'service_account_json' => '__existing__',
                'folder_id' => 'test-folder-id',
            ]);

        // Will fail due to invalid credentials, but validation should pass
        $this->assertNotEquals(422, $response->status());
    }

    public function test_test_connection_allows_new_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/test', [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        // Will fail due to invalid credentials, but validation should pass
        $this->assertNotEquals(422, $response->status());
    }

    public function test_test_connection_rejects_invalid_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/test', [
                'service_account_json' => 'not valid json',
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_test_connection_rejects_json_missing_required_keys(): void
    {
        $invalidJson = '{"type":"service_account","project_id":"test"}';

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/google-drive/test', [
                'service_account_json' => $invalidJson,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }
}
