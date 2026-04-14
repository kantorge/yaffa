<?php

namespace Tests\Feature\Console;

use App\Models\CsvImportProfile;
use App\Services\Import\SystemCsvImportProfileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncSystemCsvImportProfilesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_contains_expected_system_profile_structure(): void
    {
        $registry = new SystemCsvImportProfileRegistry();
        $profiles = $registry->profiles();

        $this->assertNotEmpty($profiles);
        $this->assertSame('hun_raiffeisen_v1', $profiles[0]['key']);
        $this->assertSame('system', $profiles[0]['type']);
        $this->assertIsArray($profiles[0]['mapping_json']);
        $this->assertIsArray(data_get($profiles[0], 'options_json.matching_rules'));
        $this->assertCount(4, data_get($profiles[0], 'options_json.matching_rules'));
        $this->assertSame('Card payment entries', data_get($profiles[0], 'options_json.matching_rules.0.name'));
    }

    public function test_sync_command_is_idempotent_and_loads_profiles(): void
    {
        $this->artisan('import:sync-system-profiles')->assertSuccessful();
        $this->artisan('import:sync-system-profiles')->assertSuccessful();

        $this->assertDatabaseHas('csv_import_profiles', [
            'key' => 'hun_raiffeisen_v1',
            'type' => 'system',
            'user_id' => null,
        ]);

        $count = CsvImportProfile::query()->where('key', 'hun_raiffeisen_v1')->count();
        $this->assertSame(1, $count);

        $profile = CsvImportProfile::query()->where('key', 'hun_raiffeisen_v1')->firstOrFail();
        $this->assertSame('Raiffeisen Hungary v1', $profile->name);
        $this->assertSame('value_date', $profile->mapping_json['Értéknap'] ?? null);
        $this->assertSame('Cash withdrawal and internal transfer entries', data_get($profile->options_json, 'matching_rules.3.name'));
    }
}
