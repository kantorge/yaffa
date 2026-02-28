<?php

namespace Tests\Feature;

use App\Jobs\ProcessGoogleDriveConfigJob;
use App\Models\GoogleDriveConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use DB;

class GoogleDriveConfigApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    private const VALID_SERVICE_ACCOUNT_JSON = '{"type":"service_account","project_id":"test-project","private_key_id":"key123","private_key":"-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----","client_email":"test@test-project.iam.gserviceaccount.com","client_id":"123456789","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token"}';

    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    // ===== AUTHORIZATION =====

    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson(route('api.v1.google-drive.config.show'));
        $this->assertUserNotAuthorized($response);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson(route('api.v1.google-drive.config.store'), [
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            'folder_id' => 'test-folder-id',
        ]);
        $this->assertUserNotAuthorized($response);
    }

    public function test_update_requires_authentication(): void
    {
        $config = GoogleDriveConfig::factory()->create();
        $response = $this->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]));
        $this->assertUserNotAuthorized($response);
    }

    public function test_destroy_requires_authentication(): void
    {
        $config = GoogleDriveConfig::factory()->create();
        $response = $this->deleteJson(route('api.v1.google-drive.config.destroy', ['googleDriveConfig' => $config->id]));
        $this->assertUserNotAuthorized($response);
    }

    public function test_show_cannot_view_other_users_config(): void
    {
        GoogleDriveConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.show'));

        $response->assertStatus(404);
    }

    public function test_update_cannot_modify_other_users_config(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'new-folder-id',
            ]);

        $this->assertUserNotAuthorized($response);
    }

    public function test_destroy_cannot_delete_other_users_config(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('api.v1.google-drive.config.destroy', ['googleDriveConfig' => $config->id]));

        $this->assertUserNotAuthorized($response);
    }

    // ===== SHOW ENDPOINT (GET /api/v1/google-drive/config) =====

    public function test_show_returns_404_when_no_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.show'));

        $response->assertStatus(404);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_show_returns_config_without_service_account_json(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.show'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'service_account_email',
            'folder_id',
            'delete_after_import',
            'enabled',
            'last_sync_at',
            'last_error',
            'error_count',
            'created_at',
            'updated_at',
        ]);
        $response->assertJson([
            'id' => $config->id,
            'folder_id' => $config->folder_id,
            'delete_after_import' => $config->delete_after_import,
            'enabled' => $config->enabled,
        ]);
        $response->assertJsonMissing(['service_account_json']);
    }

    // ===== STORE ENDPOINT (POST /api/v1/google-drive/config) =====

    public function test_store_creates_new_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id-123',
                'delete_after_import' => true,
                'enabled' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'service_account_email',
            'folder_id',
            'delete_after_import',
            'enabled',
            'message',
        ]);
        $response->assertJson([
            'folder_id' => 'test-folder-id-123',
            'delete_after_import' => true,
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('google_drive_configs', [
            'user_id' => $this->user->id,
            'folder_id' => 'test-folder-id-123',
            'delete_after_import' => true,
            'enabled' => true,
        ]);
    }

    public function test_store_extracts_service_account_email_from_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'service_account_email' => 'test@test-project.iam.gserviceaccount.com',
        ]);

        $this->assertDatabaseHas('google_drive_configs', [
            'user_id' => $this->user->id,
            'service_account_email' => 'test@test-project.iam.gserviceaccount.com',
        ]);
    }

    public function test_store_prevents_multiple_configs(): void
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

    public function test_store_encrypts_service_account_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(201);

        // Verify encrypted value in database differs from plaintext
        $rawValue = DB::table('google_drive_configs')
            ->where('user_id', $this->user->id)
            ->value('service_account_json');

        $this->assertNotEquals(self::VALID_SERVICE_ACCOUNT_JSON, $rawValue);

        // Verify decrypted value matches through model
        $config = GoogleDriveConfig::where('user_id', $this->user->id)->first();
        $this->assertEquals(self::VALID_SERVICE_ACCOUNT_JSON, $config->service_account_json);
    }

    public function test_store_defaults_delete_after_import_to_false(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(201);
        $response->assertJson(['delete_after_import' => false]);
    }

    public function test_store_defaults_enabled_to_true(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(201);
        $response->assertJson(['enabled' => true]);
    }

    // ===== UPDATE ENDPOINT (PATCH /api/v1/google-drive/config/{id}) =====

    public function test_update_changes_folder_id(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'old-folder-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'new-folder-id',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['folder_id' => 'new-folder-id']);

        $config->refresh();
        $this->assertEquals('new-folder-id', $config->folder_id);
    }

    public function test_update_changes_delete_after_import(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'delete_after_import' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => $config->folder_id,
                'delete_after_import' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['delete_after_import' => true]);

        $config->refresh();
        $this->assertTrue($config->delete_after_import);
    }

    public function test_update_changes_enabled_status(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => $config->folder_id,
                'enabled' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['enabled' => false]);

        $config->refresh();
        $this->assertFalse($config->enabled);
    }

    public function test_update_preserves_service_account_json_when_not_provided(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
        ]);

        $originalJson = $config->service_account_json;

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'new-folder-id',
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($originalJson, $config->service_account_json);
    }

    public function test_update_preserves_service_account_json_when_empty(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
        ]);

        $originalJson = $config->service_account_json;

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'new-folder-id',
                'service_account_json' => '',
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($originalJson, $config->service_account_json);
    }

    public function test_update_preserves_service_account_json_with_existing_placeholder(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
        ]);

        $originalJson = $config->service_account_json;

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'new-folder-id',
                'service_account_json' => '__existing__',
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($originalJson, $config->service_account_json);
    }

    public function test_update_changes_service_account_json_when_provided(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);
        $newJson = '{"type":"service_account","project_id":"new-project","private_key_id":"newkey","private_key":"-----BEGIN PRIVATE KEY-----\nnewtest\n-----END PRIVATE KEY-----","client_email":"new@new-project.iam.gserviceaccount.com","client_id":"987654321","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token"}';

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => $config->folder_id,
                'service_account_json' => $newJson,
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertEquals($newJson, $config->service_account_json);
        $this->assertEquals('new@new-project.iam.gserviceaccount.com', $config->service_account_email);
    }

    public function test_update_returns_no_service_account_json(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'new-folder-id',
            ]);

        $response->assertStatus(200);
        $response->assertJsonMissing(['service_account_json']);
    }

    // ===== DESTROY ENDPOINT (DELETE /api/v1/google-drive/config/{id}) =====

    public function test_destroy_deletes_config(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('api.v1.google-drive.config.destroy', ['googleDriveConfig' => $config->id]));

        $response->assertStatus(204);

        $this->assertDatabaseMissing('google_drive_configs', ['id' => $config->id]);
    }

    public function test_destroy_returns_no_content(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('api.v1.google-drive.config.destroy', ['googleDriveConfig' => $config->id]));

        $response->assertStatus(204);
        $response->assertNoContent();
    }

    // ===== TEST CONNECTION ENDPOINT (POST /api/v1/google-drive/test) =====

    public function test_test_fails_with_invalid_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => 'not valid json',
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_test_fails_with_existing_placeholder_and_no_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => '__existing__',
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => [
                'code' => 'CONFIG_NOT_FOUND',
                'message' => __('No existing Google Drive configuration found'),
            ],
        ]);
    }

    public function test_test_uses_existing_placeholder_to_fetch_json(): void
    {
        GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            'folder_id' => 'test-folder-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => '__existing__',
                'folder_id' => 'test-folder-id',
            ]);

        // Should attempt connection (will fail because credentials are fake)
        // But should not return "No existing config" error code
        $response->assertJsonMissing(['error' => ['code' => 'CONFIG_NOT_FOUND']]);
    }

    // ===== SYNC ENDPOINT (POST /api/v1/google-drive/sync/{id}) =====

    public function test_sync_queues_job_successfully(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        Queue::fake();

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.sync', ['googleDriveConfig' => $config->id]));

        $response->assertStatus(202);
        $response->assertJsonStructure(['message']);
        $response->assertJson(['message' => __('Google Drive sync has been queued')]);

        Queue::assertPushed(ProcessGoogleDriveConfigJob::class);
    }

    public function test_sync_cannot_trigger_disabled_config(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id, 'enabled' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.sync', ['googleDriveConfig' => $config->id]));

        $response->assertStatus(400);
        $response->assertJson([
            'error' => [
                'code' => 'CONFIG_DISABLED',
                'message' => __('Cannot sync disabled Google Drive configuration'),
            ],
        ]);
    }

    public function test_sync_cannot_trigger_for_other_users_config(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.sync', ['googleDriveConfig' => $config->id]));

        $this->assertUserNotAuthorized($response);
    }

    // ===== FEATURE FLAG DISABLED =====

    public function test_show_returns_forbidden_when_google_drive_feature_disabled(): void
    {
        config(['ai-documents.google_drive.enabled' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.show'));

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'FEATURE_DISABLED',
                'message' => __('Google Drive integration is disabled in configuration'),
            ],
        ]);
    }

    public function test_store_returns_forbidden_when_google_drive_feature_disabled(): void
    {
        config(['ai-documents.google_drive.enabled' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
                'delete_after_import' => false,
                'enabled' => true,
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'FEATURE_DISABLED',
                'message' => __('Google Drive integration is disabled in configuration'),
            ],
        ]);
    }

    public function test_update_returns_forbidden_when_google_drive_feature_disabled(): void
    {
        config(['ai-documents.google_drive.enabled' => false]);

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'updated-folder-id',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'FEATURE_DISABLED',
                'message' => __('Google Drive integration is disabled in configuration'),
            ],
        ]);
    }

    public function test_destroy_returns_forbidden_when_google_drive_feature_disabled(): void
    {
        config(['ai-documents.google_drive.enabled' => false]);

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('api.v1.google-drive.config.destroy', ['googleDriveConfig' => $config->id]));

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'FEATURE_DISABLED',
                'message' => __('Google Drive integration is disabled in configuration'),
            ],
        ]);
    }

    public function test_test_returns_forbidden_when_google_drive_feature_disabled(): void
    {
        config(['ai-documents.google_drive.enabled' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'FEATURE_DISABLED',
                'message' => __('Google Drive integration is disabled in configuration'),
            ],
        ]);
    }

    public function test_sync_returns_forbidden_when_google_drive_feature_disabled(): void
    {
        config(['ai-documents.google_drive.enabled' => false]);

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.sync', ['googleDriveConfig' => $config->id]));

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'FEATURE_DISABLED',
                'message' => __('Google Drive integration is disabled in configuration'),
            ],
        ]);
    }
}
