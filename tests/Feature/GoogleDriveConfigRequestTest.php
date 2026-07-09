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

    // ===== CREATE (POST /api/v1/google-drive/config) =====

    public function test_create_requires_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_requires_folder_id(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['folder_id']);
    }

    public function test_create_rejects_short_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => 'too short',
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_rejects_very_long_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => str_repeat('x', 5001),
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_rejects_invalid_json_format(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
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
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => $invalidJson,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_rejects_untrusted_token_uri(): void
    {
        $maliciousJson = '{"type":"service_account","project_id":"test-project","private_key_id":"key123","private_key":"-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----","client_email":"test@test-project.iam.gserviceaccount.com","client_id":"123456789","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"http://169.254.169.254/latest/meta-data/"}';

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => $maliciousJson,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_rejects_untrusted_auth_uri(): void
    {
        $maliciousJson = '{"type":"service_account","project_id":"test-project","private_key_id":"key123","private_key":"-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----","client_email":"test@test-project.iam.gserviceaccount.com","client_id":"123456789","auth_uri":"http://internal.attacker.example/oauth","token_uri":"https://oauth2.googleapis.com/token"}';

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => $maliciousJson,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_create_accepts_valid_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(201);
    }

    public function test_create_prevents_multiple_configs_per_user(): void
    {
        GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'another-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['folder_id']);
    }

    public function test_create_accepts_post_import_actions_array(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
                'post_import_actions' => ['delete', 'trash'],
            ]);

        $response->assertStatus(201);
    }

    public function test_create_accepts_enabled_boolean(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
                'enabled' => false,
            ]);

        $response->assertStatus(201);
    }

    // ===== UPDATE (PATCH /api/v1/google-drive/config/{id}) =====

    public function test_update_allows_missing_folder_id(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'original-folder-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
                'enabled' => true,
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
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
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
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
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
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
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
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
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
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
                'folder_id' => 'new-folder-id',
                'service_account_json' => '__existing__',
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($originalJson, $config->service_account_json);
    }

    public function test_update_allows_changing_post_import_actions(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'post_import_actions' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
                'folder_id' => $config->folder_id,
                'post_import_actions' => ['delete'],
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals(['delete'], $config->post_import_actions);
    }

    public function test_update_allows_changing_enabled(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
                'folder_id' => $config->folder_id,
                'enabled' => false,
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertFalse($config->enabled);
    }

    // ===== TEST CONNECTION (POST /api/v1/google-drive/config/test) =====

    public function test_test_connection_requires_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_test_connection_requires_folder_id(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['folder_id']);
    }

    public function test_test_connection_allows_existing_placeholder(): void
    {
        GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => '__existing__',
                'folder_id' => 'test-folder-id',
            ]);

        // Will fail due to invalid credentials, but validation should pass
        $this->assertNotEquals(422, $response->status());
    }

    public function test_test_connection_allows_new_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        // Will fail due to invalid credentials, but validation should pass
        $this->assertNotEquals(422, $response->status());
    }

    public function test_test_connection_rejects_invalid_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
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
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => $invalidJson,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    // ===== PROCESSED FOLDER VALIDATION =====

    public function test_update_rejects_processed_folder_id_equal_to_folder_id(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'same-folder-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'same-folder-id',
                'post_import_actions' => ['move_to_processed'],
                'processed_folder_id' => 'same-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['processed_folder_id']);
    }

    public function test_update_accepts_processed_folder_id_different_from_folder_id(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'import-folder-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'import-folder-id',
                'post_import_actions' => ['move_to_processed'],
                'processed_folder_id' => 'processed-folder-id',
            ]);

        $response->assertStatus(200);
    }

    public function test_update_rejects_processed_folder_id_equal_to_existing_folder_id_when_folder_id_omitted(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'import-folder-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'post_import_actions' => ['move_to_processed'],
                'processed_folder_id' => 'import-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['processed_folder_id']);
    }

    public function test_update_requires_processed_folder_id_when_move_to_processed_selected(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'import-folder-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'post_import_actions' => ['move_to_processed'],
                'processed_folder_id' => null,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['processed_folder_id']);
    }

    public function test_create_rejects_processed_folder_id_equal_to_folder_id(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'same-folder-id',
                'post_import_actions' => ['move_to_processed'],
                'processed_folder_id' => 'same-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['processed_folder_id']);
    }
}
