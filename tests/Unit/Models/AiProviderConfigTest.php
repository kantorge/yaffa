<?php

namespace Tests\Unit\Models;

use App\Models\AiProviderConfig;
use App\Models\User;
use Database\Factories\AiProviderConfigFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_is_encrypted_at_rest(): void
    {
        $plainKey = 'sk-test-super-secret-key-12345';
        $config = AiProviderConfig::factory()->create(['api_key' => $plainKey]);

        // Verify that stored value is different from plaintext (encrypted)
        $rawValue = \DB::table('ai_provider_configs')
            ->where('id', $config->id)
            ->value('api_key');

        // Laravel encryption should produce a different value
        $this->assertNotEquals($plainKey, $rawValue);
        // Encrypted values are base64 encoded, so they contain = or + or /
        $this->assertTrue(
            strpos($rawValue, '=') !== false || strpos($rawValue, '+') !== false || strpos($rawValue, '/') !== false,
            'Encrypted value should be base64 encoded'
        );
    }

    public function test_api_key_is_decrypted_when_accessed(): void
    {
        $plainKey = 'sk-test-super-secret-key-12345';
        $config = AiProviderConfig::factory()->create(['api_key' => $plainKey]);

        // When accessed through the model, it should be decrypted
        $this->assertEquals($plainKey, $config->api_key);
    }
}
