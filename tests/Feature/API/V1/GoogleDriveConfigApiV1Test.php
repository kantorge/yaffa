<?php

namespace Tests\Feature\API\V1;

use App\Jobs\ProcessGoogleDriveConfigJob;
use App\Models\GoogleDriveConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleDriveConfigApiV1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    private const VALID_SERVICE_ACCOUNT_JSON = '{"type":"service_account","project_id":"test-project","private_key_id":"key123","private_key":"-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----","client_email":"test@test-project.iam.gserviceaccount.com","client_id":"123456789","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token"}';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    // ===== AUTH TESTS =====

    public function test_unauthenticated_cannot_access_v1_show(): void
    {
        $response = $this->getJson(route('api.v1.google-drive.config.show'));
        $this->assertThat(
            $response->status(),
            $this->logicalOr($this->equalTo(401), $this->equalTo(403))
        );
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_unauthenticated_cannot_access_v1_store(): void
    {
        $response = $this->postJson(route('api.v1.google-drive.config.store'), [
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            'folder_id' => 'test-folder-id',
        ]);
        $this->assertThat(
            $response->status(),
            $this->logicalOr($this->equalTo(401), $this->equalTo(403))
        );
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    // ===== HAPPY PATH TESTS =====

    public function test_v1_show_returns_404_when_no_config(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.google-drive.config.show'));

        $response->assertNotFound()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_v1_show_returns_config_without_service_account_json(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.google-drive.config.show'));

        $response->assertOk()
            ->assertJsonStructure(['id', 'service_account_email', 'folder_id', 'delete_after_import', 'enabled'])
            ->assertJsonMissing(['service_account_json']);
    }

    public function test_v1_store_creates_config(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.google-drive.config.store'), [
                'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
                'folder_id' => 'test-folder-id',
                'delete_after_import' => false,
                'enabled' => true,
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'service_account_email', 'folder_id', 'delete_after_import', 'enabled', 'message'])
            ->assertJsonMissing(['service_account_json']);

        $this->assertDatabaseHas('google_drive_configs', [
            'user_id' => $this->user->id,
            'folder_id' => 'test-folder-id',
        ]);
    }

    public function test_v1_update_modifies_config(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
                'folder_id' => 'new-folder-id',
            ]);

        $response->assertOk()
            ->assertJson(['folder_id' => 'new-folder-id'])
            ->assertJsonMissing(['service_account_json']);
    }

    public function test_v1_destroy_deletes_config(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('api.v1.google-drive.config.destroy', $config));

        $response->assertNoContent();
        $this->assertDatabaseMissing('google_drive_configs', ['id' => $config->id]);
    }

    public function test_v1_sync_queues_job(): void
    {
        Queue::fake();
        config(['ai-documents.google_drive.enabled' => true]);

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.google-drive.config.sync', $config));

        $response->assertStatus(202)->assertJsonStructure(['message']);
        Queue::assertPushed(ProcessGoogleDriveConfigJob::class);
    }

    // ===== ERROR FORMAT TESTS (V1 error.* contract) =====

    public function test_v1_validation_error_uses_error_contract(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.google-drive.config.store'), []);

        $response->assertUnprocessable()
            ->assertJsonStructure(['error' => ['code', 'message', 'details']])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_v1_feature_disabled_uses_error_contract(): void
    {
        config(['ai-documents.google_drive.enabled' => false]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.google-drive.config.show'));

        $response->assertForbidden()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');
    }

    public function test_v1_sync_disabled_config_uses_error_contract(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->user->id, 'enabled' => false]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.google-drive.config.sync', $config));

        $response->assertStatus(400)
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'CONFIG_DISABLED');
    }

    public function test_v1_authorization_error_uses_error_contract(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        $config = GoogleDriveConfig::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.google-drive.config.update', $config), [
                'folder_id' => 'new-folder',
            ]);

        $response->assertForbidden()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_v1_secret_not_exposed_in_show(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        GoogleDriveConfig::factory()->create([
            'user_id' => $this->user->id,
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.google-drive.config.show'));

        $response->assertOk()
            ->assertJsonMissing(['service_account_json']);
    }

    public function test_v1_test_no_existing_config_uses_error_contract(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.v1.google-drive.config.test'), [
                'service_account_json' => '__existing__',
                'folder_id' => 'test-folder-id',
            ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'CONFIG_NOT_FOUND');
    }
}
