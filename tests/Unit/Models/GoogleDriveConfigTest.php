<?php

namespace Tests\Unit\Models;

use App\Models\GoogleDriveConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use DB;

class GoogleDriveConfigTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_SERVICE_ACCOUNT_JSON = '{"type":"service_account","project_id":"test-project","private_key_id":"key123","private_key":"-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----","client_email":"test@test-project.iam.gserviceaccount.com","client_id":"123456789","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token"}';

    public function test_service_account_json_is_encrypted_at_rest(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
        ]);

        $rawValue = DB::table('google_drive_configs')
            ->where('id', $config->id)
            ->value('service_account_json');

        $this->assertNotEquals(self::VALID_SERVICE_ACCOUNT_JSON, $rawValue);
    }

    public function test_service_account_json_is_decrypted_when_accessed(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
        ]);

        $this->assertEquals(self::VALID_SERVICE_ACCOUNT_JSON, $config->service_account_json);
    }

    public function test_service_account_json_is_hidden_from_serialized_output(): void
    {
        $config = GoogleDriveConfig::factory()->create([
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
        ]);

        $serialized = $config->toArray();

        $this->assertArrayNotHasKey('service_account_json', $serialized);
    }
}
