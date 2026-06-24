<?php

namespace Tests\Unit\Services\Import;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\FileImportProfile;
use App\Models\Payee;
use App\Models\User;
use App\Services\Import\CsvParserService;
use App\Services\Import\SystemFileImportProfileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvParserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_valid_csv_with_system_profile_rules_and_mapping(): void
    {
        $user = User::factory()->create();
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create(['config_type' => 'account', 'active' => true]);

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create(['config_type' => 'payee', 'active' => true, 'name' => 'Coffee Shop']);

        $profile = $this->createSystemProfile();

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.05.;-1 234,56;Elektronikus forint átutalás;Ref-1;Coffee Shop;"memo with ; delimiter"
CSV;

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $service = new CsvParserService();
        $parsed = $service->parseFile($file, $profile, $account->id, $user->id);

        $this->assertCount(1, $parsed['drafts']);
        $this->assertSame('pending_review', $parsed['drafts'][0]['status']);
        $this->assertSame('withdrawal', $parsed['drafts'][0]['transaction_type']);
        $this->assertSame('2025-01-05', $parsed['drafts'][0]['date']);
        $this->assertSame(1234.56, $parsed['drafts'][0]['amount']);
        $this->assertSame($account->id, $parsed['drafts'][0]['config']['account_from_id']);
        $this->assertSame($payee->id, $parsed['drafts'][0]['config']['account_to_id']);
        $this->assertSame('Coffee Shop', $parsed['drafts'][0]['payee']);
    }

    public function test_parse_handles_windows1252_encoding_and_multiline_values(): void
    {
        $user = User::factory()->create();
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create(['config_type' => 'account', 'active' => true]);

        $profile = $this->createSystemProfile();

        $csvUtf8 = "Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3\n"
            . "2025.01.06.;200,00;Forint átutalás;Ref-2;Déjà Vu;\"line 1\nline 2\"\n";

        $csvWindows1252 = mb_convert_encoding($csvUtf8, 'Windows-1252', 'UTF-8');
        $file = UploadedFile::fake()->createWithContent('import.csv', $csvWindows1252);

        $service = new CsvParserService();
        $parsed = $service->parseFile($file, $profile, $account->id, $user->id);

        $this->assertCount(1, $parsed['drafts']);
        $this->assertSame('deposit', $parsed['drafts'][0]['transaction_type']);
        $this->assertSame('2025-01-06', $parsed['drafts'][0]['date']);
        $this->assertNotEmpty($parsed['warnings']);
        $this->assertStringContainsString('converted to UTF-8', implode(' ', $parsed['warnings']));
    }

    public function test_parse_collects_unmatched_rows_and_does_not_abort_import(): void
    {
        $user = User::factory()->create();
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create(['config_type' => 'account', 'active' => true]);

        $profile = $this->createSystemProfile();

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.10.;-10,00;Elektronikus forint átutalás;Ref;Known;Memo
BAD_DATE;abc;Unknown;Ref;Unknown;Memo
CSV;

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $service = new CsvParserService();
        $parsed = $service->parseFile($file, $profile, $account->id, $user->id);

        $this->assertCount(2, $parsed['drafts']);
        $this->assertSame('pending_review', $parsed['drafts'][0]['status']);
        $this->assertSame('failed_validation', $parsed['drafts'][1]['status']);
        $this->assertNotEmpty($parsed['unmatched_rows']);
        $this->assertStringContainsString('No matching system rule', implode(' ', $parsed['drafts'][1]['warnings']));
    }

    private function createSystemProfile(): FileImportProfile
    {
        $definition = (new SystemFileImportProfileRegistry())->profiles()[0];

        return FileImportProfile::query()->create([
            'user_id' => null,
            'key' => $definition['key'],
            'type' => 'system',
            'name' => $definition['name'],
            'delimiter' => $definition['delimiter'],
            'has_header_row' => $definition['has_header_row'],
            'date_format' => $definition['date_format'],
            'decimal_separator' => $definition['decimal_separator'],
            'thousand_separator' => $definition['thousand_separator'],
            'sign_handling' => $definition['sign_handling'],
            'mapping_json' => $definition['mapping_json'],
            'options_json' => $definition['options_json'],
            'active' => true,
        ]);
    }
}
