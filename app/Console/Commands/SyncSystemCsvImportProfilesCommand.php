<?php

namespace App\Console\Commands;

use App\Models\CsvImportProfile;
use App\Services\Import\SystemCsvImportProfileRegistry;
use Illuminate\Console\Command;

class SyncSystemCsvImportProfilesCommand extends Command
{
    protected $signature = 'app:import:sync-system-profiles';

    protected $description = 'Synchronize application-managed system CSV import profiles';

    public function handle(SystemCsvImportProfileRegistry $registry): int
    {
        $count = 0;

        foreach ($registry->profiles() as $profile) {
            CsvImportProfile::query()->updateOrCreate(
                ['key' => $profile['key']],
                [
                    'user_id' => null,
                    'type' => 'system',
                    'name' => $profile['name'],
                    'delimiter' => $profile['delimiter'] ?? ',',
                    'has_header_row' => (bool) ($profile['has_header_row'] ?? true),
                    'date_format' => $profile['date_format'] ?? null,
                    'decimal_separator' => $profile['decimal_separator'] ?? null,
                    'thousand_separator' => $profile['thousand_separator'] ?? null,
                    'sign_handling' => $profile['sign_handling'] ?? null,
                    'mapping_json' => $profile['mapping_json'] ?? [],
                    'options_json' => $profile['options_json'] ?? [],
                    'active' => (bool) ($profile['active'] ?? true),
                ],
            );

            $count++;
        }

        $this->info(sprintf('Synchronized %d system CSV import profile(s).', $count));

        return self::SUCCESS;
    }
}
