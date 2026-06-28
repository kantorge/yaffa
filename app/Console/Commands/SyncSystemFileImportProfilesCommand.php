<?php

namespace App\Console\Commands;

use App\Models\FileImportProfile;
use App\Services\Import\SystemFileImportProfileRegistry;
use Illuminate\Console\Command;

class SyncSystemFileImportProfilesCommand extends Command
{
    protected $signature = 'app:import:sync-system-profiles';

    protected $description = 'Synchronize application-managed system file import profiles';

    public function handle(SystemFileImportProfileRegistry $registry): int
    {
        $count = 0;

        foreach ($registry->profiles() as $profile) {
            $record = FileImportProfile::query()->firstOrNew(['key' => $profile['key']]);
            $record->fill([
                'file_type' => $profile['file_type'] ?? 'csv',
                'name' => $profile['name'],
                'delimiter' => $profile['delimiter'] ?? null,
                'has_header_row' => (bool) ($profile['has_header_row'] ?? false),
                'date_format' => $profile['date_format'] ?? null,
                'decimal_separator' => $profile['decimal_separator'] ?? null,
                'thousand_separator' => $profile['thousand_separator'] ?? null,
                'sign_handling' => $profile['sign_handling'] ?? null,
                'mapping_json' => $profile['mapping_json'] ?? [],
                'options_json' => $profile['options_json'] ?? [],
                'active' => (bool) ($profile['active'] ?? true),
            ]);
            $record->key = $profile['key'];
            $record->user_id = null;
            $record->type = 'system';
            $record->save();

            $count++;
        }

        $this->info(sprintf('Synchronized %d system file import profile(s).', $count));

        return self::SUCCESS;
    }
}
