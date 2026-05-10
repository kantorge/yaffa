<?php

namespace Tests\Feature;

use App\Jobs\ProcessGoogleDriveConfigJob;
use App\Models\GoogleDriveConfig;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use DB;
use Exception;

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
            'post_import_actions',
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
            'post_import_actions' => $config->post_import_actions,
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
                'post_import_actions' => ['delete'],
                'enabled' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'service_account_email',
            'folder_id',
            'post_import_actions',
            'enabled',
        ]);
        $response->assertJsonPath('folder_id', 'test-folder-id-123');
        $response->assertJsonPath('post_import_actions', ['delete']);
        $response->assertJsonPath('enabled', true);

        $this->assertDatabaseHas('google_drive_configs', [
            'user_id' => $this->user->id,
            'folder_id' => 'test-folder-id-123',
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
        $response->assertJsonPath('service_account_email', 'test@test-project.iam.gserviceaccount.com');

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

    public function test_store_defaults_post_import_actions_to_null(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('post_import_actions', null);
    }

    public function test_store_defaults_enabled_to_true(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('enabled', true);
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

    public function test_update_changes_post_import_actions(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'post_import_actions' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => $config->folder_id,
                'post_import_actions' => ['delete'],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['post_import_actions' => ['delete']]);

        $config->refresh();
        $this->assertEquals(['delete'], $config->post_import_actions);
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

    public function test_update_preserves_enabled_status_when_not_provided(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'old-folder-id',
            'enabled' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', ['googleDriveConfig' => $config->id]), [
                'folder_id' => 'new-folder-id',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'folder_id' => 'new-folder-id',
            'enabled' => false,
        ]);

        $config->refresh();
        $this->assertSame('new-folder-id', $config->folder_id);
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

    // ===== FOLDER NAME ENDPOINT (GET /api/v1/google-drive/config/{id}/folder-name) =====

    public function test_folder_name_requires_authentication(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.google-drive.config.folder-name', $config->id));

        $this->assertUserNotAuthorized($response);
    }

    public function test_folder_name_cannot_access_other_users_config(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folder-name', $config->id));

        $this->assertUserNotAuthorized($response);
    }

    public function test_folder_name_returns_name_from_service(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'real-folder-id',
        ]);

        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('getFolderName')->willReturn('My Import Folder');
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folder-name', $config->id));

        $response->assertStatus(200)
            ->assertJson(['folder_name' => 'My Import Folder']);
    }

    public function test_folder_name_returns_null_when_service_throws(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'bad-folder-id',
        ]);

        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('getFolderName')->willThrowException(new Exception('Drive API error'));
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folder-name', $config->id));

        $response->assertStatus(200)
            ->assertJson(['folder_name' => null]);
    }

    public function test_folder_name_returns_400_when_no_folder_id_configured(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => '',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folder-name', $config->id));

        $response->assertStatus(400)
            ->assertJson([
                'error' => ['code' => 'MISSING_FOLDER_ID'],
            ]);
    }

    public function test_folder_name_uses_query_param_folder_id_when_provided(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'default-folder-id',
        ]);

        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('getFolderName')
            ->with('custom-folder-id', $this->anything())
            ->willReturn('Custom Folder Name');
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folder-name', $config->id) . '?folder_id=custom-folder-id');

        $response->assertStatus(200)
            ->assertJson(['folder_name' => 'Custom Folder Name']);
    }

    public function test_folder_name_by_credentials_requires_authentication(): void
    {
        $response = $this->postJson(route('api.v1.google-drive.config.folder-name-by-credentials'), [
            'folder_id' => 'folder-id',
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
        ]);

        $this->assertUserNotAuthorized($response);
    }

    public function test_folder_name_by_credentials_returns_name_from_service(): void
    {
        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('getFolderName')->willReturn('My Import Folder');
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.folder-name-by-credentials'), [
                'folder_id' => 'real-folder-id',
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            ]);

        $response->assertStatus(200)
            ->assertJson(['folder_name' => 'My Import Folder']);
    }

    // ===== FOLDER BROWSER ENDPOINT (GET /api/v1/google-drive/config/{id}/folders) =====

    public function test_folders_requires_authentication(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.google-drive.config.folders', $config->id));

        $this->assertUserNotAuthorized($response);
    }

    public function test_folders_cannot_access_other_users_config(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folders', $config->id));

        $this->assertUserNotAuthorized($response);
    }

    public function test_folders_returns_folder_list_from_service(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $folders = [
            ['id' => 'folder-1', 'name' => 'Receipts'],
            ['id' => 'folder-2', 'name' => 'Invoices'],
        ];

        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('listFolders')->willReturn([
            'folders' => $folders,
            'truncated' => false,
            'page_size' => 100,
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folders', $config->id));

        $response->assertStatus(200)
            ->assertJson([
                'folders' => $folders,
                'folders_truncated' => false,
            ])
            ->assertJsonMissingPath('notice');
    }

    public function test_folders_returns_notice_when_folder_list_is_truncated(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $folders = [
            ['id' => 'folder-1', 'name' => 'Receipts'],
        ];

        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('listFolders')->willReturn([
            'folders' => $folders,
            'truncated' => true,
            'page_size' => 100,
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folders', $config->id));

        $response->assertStatus(200)
            ->assertJsonPath('folders_truncated', true)
            ->assertJsonPath('folders.0.id', 'folder-1')
            ->assertJsonPath('folders.0.name', 'Receipts')
            ->assertJsonPath('notice', __('Folder list is truncated to the first page of Google Drive results. Open a parent folder to narrow results.'));
    }

    public function test_folders_returns_403_on_google_auth_error(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $googleException = new \Google\Service\Exception('Forbidden', 403);

        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('listFolders')->willThrowException($googleException);
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folders', $config->id));

        $response->assertStatus(403)
            ->assertJson(['error' => ['code' => 'PERMISSION_DENIED']]);
    }

    public function test_folders_returns_parent_id_from_query_param(): void
    {
        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $capturedParentId = null;
        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('listFolders')
            ->willReturnCallback(function ($cfg, $parentId) use (&$capturedParentId) {
                $capturedParentId = $parentId;

                return [
                    'folders' => [],
                    'truncated' => false,
                    'page_size' => 10,
                ];
            });
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.folders', $config->id) . '?parent_id=parent-folder-id');

        $response->assertStatus(200);
        $this->assertSame('parent-folder-id', $capturedParentId);
    }

    public function test_folders_by_credentials_returns_folder_list_from_service(): void
    {
        $folders = [
            ['id' => 'folder-1', 'name' => 'Receipts'],
            ['id' => 'folder-2', 'name' => 'Invoices'],
        ];

        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('listFoldersByCredentials')->willReturn([
            'folders' => $folders,
            'truncated' => false,
            'page_size' => 10,
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.folders-by-credentials'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'folders' => $folders,
                'folders_truncated' => false,
            ]);
    }

    public function test_folders_by_credentials_returns_400_with_existing_placeholder_and_no_config(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.folders-by-credentials'), [
                'service_account_json' => '__existing__',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'code' => 'CONFIG_NOT_FOUND',
                    'message' => __('No existing Google Drive configuration found'),
                ],
            ]);
    }

    // ===== FOLDER NAME IN CONFIG RESPONSES =====

    public function test_update_saves_and_returns_folder_name(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_id' => 'test-folder-id',
            'folder_name' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson(route('api.v1.google-drive.config.update', $config->id), [
                'folder_name' => 'My Receipts Folder',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('google_drive_configs', [
            'id' => $config->id,
            'folder_name' => 'My Receipts Folder',
        ]);

        $response->assertJsonPath('folder_name', 'My Receipts Folder');
    }

    public function test_show_includes_folder_name_in_response(): void
    {
        GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'folder_name' => 'Import Inbox',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.google-drive.config.show'));

        $response->assertStatus(200)
            ->assertJsonPath('folder_name', 'Import Inbox');
    }

    // ===== FOLDER NAME IN TEST-CONNECTION RESPONSE =====

    public function test_test_connection_response_includes_folder_name(): void
    {
        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('testConnection')->willReturn([
            'success' => true,
            'message' => 'Connection successful',
            'file_count' => 5,
            'folder_name' => 'YAFFA Import',
            'test_file_found' => false,
            'capabilities_source' => 'estimated',
            'capabilities' => ['delete' => true, 'trash' => true, 'move_to_processed' => null, 'rename_processed' => true],
            'recommended_actions' => ['delete'],
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('folder_name', 'YAFFA Import');
    }

    public function test_test_connection_response_includes_capabilities(): void
    {
        $mock = $this->createMock(GoogleDriveService::class);
        $mock->method('testConnection')->willReturn([
            'success' => true,
            'message' => 'Connection successful',
            'file_count' => 2,
            'folder_name' => null,
            'test_file_found' => true,
            'capabilities_source' => 'real_file',
            'capabilities' => [
                'delete' => false,
                'trash' => false,
                'move_to_processed' => true,
                'rename_processed' => true,
            ],
            'recommended_actions' => ['move_to_processed', 'rename_processed'],
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'test_file_found' => true,
                'capabilities_source' => 'real_file',
                'capabilities' => [
                    'delete' => false,
                    'trash' => false,
                    'move_to_processed' => true,
                    'rename_processed' => true,
                ],
                'recommended_actions' => ['move_to_processed', 'rename_processed'],
            ]);
    }
}
