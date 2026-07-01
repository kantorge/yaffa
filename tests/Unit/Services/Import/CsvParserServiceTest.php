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
use Illuminate\Support\Facades\DB;
use League\Csv\Exception as CsvException;
use ReflectionMethod;
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

    public function test_resolve_payee_by_name_or_alias_makes_single_db_query_for_multi_row_csv(): void
    {
        $user = User::factory()->create();
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create(['config_type' => 'account', 'active' => true]);

        AccountEntity::factory()->for($user)->for(Payee::factory()->withUser($user), 'config')
            ->create(['config_type' => 'payee', 'name' => 'Vendor A']);
        AccountEntity::factory()->for($user)->for(Payee::factory()->withUser($user), 'config')
            ->create(['config_type' => 'payee', 'name' => 'Vendor B']);
        AccountEntity::factory()->for($user)->for(Payee::factory()->withUser($user), 'config')
            ->create(['config_type' => 'payee', 'name' => 'Vendor C']);

        $profile = $this->createSystemProfile();

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.05.;-100,00;Elektronikus forint átutalás;Ref-1;Vendor A;Memo A
2025.01.06.;-200,00;Elektronikus forint átutalás;Ref-2;Vendor B;Memo B
2025.01.07.;-300,00;Elektronikus forint átutalás;Ref-3;Vendor C;Memo C
CSV;

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);
        $service = new CsvParserService();

        DB::enableQueryLog();
        $service->parseFile($file, $profile, $account->id, $user->id);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $payeeQueries = array_filter(
            $queries,
            fn (array $q) => str_contains($q['query'], 'account_entities')
                && in_array('payee', $q['bindings'], true),
        );

        $this->assertCount(1, $payeeQueries, 'Expected exactly one payee lookup query regardless of row count.');
    }

    public function test_humanize_csv_exception_fallback_returns_static_string_without_raw_message(): void
    {
        $service = new CsvParserService();
        $method = new ReflectionMethod(CsvParserService::class, 'humanizeCsvException');
        $method->setAccessible(true);

        $rawMessage = 'internal state: byte offset 127, parser state 0x3F';
        $exception = new CsvException($rawMessage);

        $result = $method->invoke($service, $exception);

        $this->assertStringNotContainsString($rawMessage, $result);
        $this->assertStringNotContainsString('byte offset', $result);
        $this->assertStringContainsString('Please check that the file format', $result);
    }

    private function createSystemProfile(): FileImportProfile
    {
        $definition = (new SystemFileImportProfileRegistry())->profiles()[0];

        $record = FileImportProfile::query()->firstOrNew(['key' => $definition['key']]);
        $record->fill([
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
        $record->key = $definition['key'];
        $record->user_id = null;
        $record->type = 'system';
        $record->save();

        return $record;
    }
}
