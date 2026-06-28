<?php

namespace Tests\Feature\API\V1;

use App\Models\FileImportProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileImportProfileCloneTest extends TestCase
{
    use RefreshDatabase;

    public function test_cloning_system_profile_replaces_non_canonical_mapping_values_with_ignore(): void
    {
        $user = User::factory()->create();

        $profile = FileImportProfile::query()->create([
            'user_id' => null,
            'key' => 'test-system-profile',
            'type' => 'system',
            'file_type' => 'csv',
            'name' => 'Test System Profile',
            'delimiter' => ';',
            'has_header_row' => true,
            'date_format' => null,
            'decimal_separator' => ',',
            'thousand_separator' => null,
            'sign_handling' => null,
            'mapping_json' => [
                'Értéknap' => 'value_date',
                'Összeg' => 'amount',
                'Típus' => 'entry_type',
                'Közlemény/1' => 'notice_1',
                'Közlemény/2' => 'notice_2',
                'Közlemény/3' => 'notice_3',
                'Payee' => 'payee',
            ],
            'options_json' => [],
            'active' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.file-profiles.clone', $profile));

        $response->assertCreated();

        $clone = FileImportProfile::query()->find($response->json('data.id'));
        $this->assertNotNull($clone);

        $mapping = $clone->mapping_json;
        $this->assertIsArray($mapping);

        // System-only fact names must be replaced with 'ignore'
        $this->assertSame('ignore', $mapping['Értéknap']);
        $this->assertSame('ignore', $mapping['Típus']);
        $this->assertSame('ignore', $mapping['Közlemény/1']);
        $this->assertSame('ignore', $mapping['Közlemény/2']);
        $this->assertSame('ignore', $mapping['Közlemény/3']);

        // Valid canonical fields must be preserved
        $this->assertSame('amount', $mapping['Összeg']);
        $this->assertSame('payee', $mapping['Payee']);
    }

    public function test_cloning_user_profile_preserves_all_valid_canonical_mapping_values(): void
    {
        $user = User::factory()->create();

        $profile = FileImportProfile::query()->create([
            'user_id' => $user->id,
            'key' => null,
            'type' => 'user',
            'file_type' => 'csv',
            'name' => 'My Profile',
            'delimiter' => ',',
            'has_header_row' => true,
            'date_format' => null,
            'decimal_separator' => '.',
            'thousand_separator' => null,
            'sign_handling' => null,
            'mapping_json' => [
                'Date' => 'date',
                'Amount' => 'amount',
                'Payee' => 'payee',
                'Memo' => 'comment',
                'Ref' => 'reference',
                'Category' => 'category',
                'Ignored' => 'ignore',
            ],
            'options_json' => [],
            'active' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.file-profiles.clone', $profile));

        $response->assertCreated();

        $clone = FileImportProfile::query()->find($response->json('data.id'));
        $this->assertNotNull($clone);

        $this->assertEquals($profile->mapping_json, $clone->mapping_json);
    }
}
