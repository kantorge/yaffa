<?php

namespace Tests\Unit\Models;

use App\Models\InvestmentProviderConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use DB;

class InvestmentProviderConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $plainCredentials = [
            'api_key' => 'alpha-test-super-secret-key',
        ];

        $config = InvestmentProviderConfig::factory()->create([
            'credentials' => $plainCredentials,
        ]);

        $rawValue = DB::table('investment_provider_configs')
            ->where('id', $config->id)
            ->value('credentials');

        $this->assertNotEquals(json_encode($plainCredentials), $rawValue);
    }

    public function test_credentials_are_decrypted_when_accessed(): void
    {
        $plainCredentials = [
            'api_key' => 'alpha-test-super-secret-key',
        ];

        $config = InvestmentProviderConfig::factory()->create([
            'credentials' => $plainCredentials,
        ]);

        $this->assertEquals($plainCredentials, $config->credentials);
    }

    public function test_credentials_are_hidden_from_serialized_output(): void
    {
        $config = InvestmentProviderConfig::factory()->create();

        $serialized = $config->toArray();

        $this->assertArrayNotHasKey('credentials', $serialized);
    }
}
