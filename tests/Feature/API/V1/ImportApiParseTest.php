<?php

namespace Tests\Feature\API\V1;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\FileImportProfile;
use App\Models\User;
use App\Services\Import\SystemFileImportProfileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ImportApiParseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_parse_import(): void
    {
        $response = $this->postJson(route('api.v1.imports.parse'), [
            'source_type' => 'qif',
            'account_id' => 1,
            'file' => UploadedFile::fake()->createWithContent('import.qif', '!Type:Bank'),
        ]);

        $this->assertUserNotAuthorized($response);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_qif_parse_valid_returns_runtime_draft_payload(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);

        $qifContent = <<<'QIF'
!Type:Bank
D2025-01-05
T-123.45
PGrocery Store
MWeekly groceries
^
QIF;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'qif',
                'account_id' => $accountEntity->id,
                'file' => UploadedFile::fake()->createWithContent('import.qif', $qifContent),
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'source_type',
                'account_id',
                'drafts' => [[
                    'draft_index',
                    'status',
                    'source_type',
                    'date',
                    'amount',
                    'transaction_type',
                    'account_id',
                    'payee',
                    'memo',
                    'source_category',
                    'reference',
                    'raw_entry',
                    'config' => [
                        'account_from_id',
                        'account_to_id',
                        'amount_from',
                        'amount_to',
                    ],
                    'warnings',
                    'duplicate_candidates',
                    'related_ai_documents',
                ]],
                'warnings',
                'summary' => [
                    'total_entries',
                    'total_drafts',
                    'warning_count',
                ],
            ])
            ->assertJsonPath('source_type', 'qif')
            ->assertJsonPath('account_id', $accountEntity->id)
            ->assertJsonPath('summary.total_entries', 1)
            ->assertJsonPath('summary.total_drafts', 1)
            ->assertJsonPath('drafts.0.status', 'pending_review')
            ->assertJsonPath('drafts.0.transaction_type', 'withdrawal')
            ->assertJsonPath('drafts.0.amount', 123.45)
            ->assertJsonPath('drafts.0.date', '2025-01-05')
            ->assertJsonPath('drafts.0.config.account_from_id', $accountEntity->id);
    }

    public function test_qif_parse_forbidden_for_non_owned_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignAccount = $this->createAccountEntity($otherUser);

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'qif',
                'account_id' => $foreignAccount->id,
                'file' => UploadedFile::fake()->createWithContent('import.qif', "!Type:Bank\nD2025-01-01\nT1\n^"),
            ]);

        $response->assertForbidden();
    }

    public function test_csv_parse_valid_with_system_profile(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $profile = $this->createSystemProfile();

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.11.;-50,25;Elektronikus forint átutalás;Ref;Vendor;Memo
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $profile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk()
            ->assertJsonPath('source_type', 'csv')
            ->assertJsonPath('drafts.0.file_import_profile_id', $profile->id)
            ->assertJsonPath('drafts.0.transaction_type', 'withdrawal')
            ->assertJsonPath('drafts.0.amount', 50.25);
    }

    public function test_csv_parse_uses_account_preferred_profile_when_none_selected(): void
    {
        $user = User::factory()->create();
        $systemProfile = $this->createSystemProfile();
        $accountEntity = $this->createAccountEntity($user);

        $accountEntity->preferred_file_import_profile_id = $systemProfile->id;
        $accountEntity->save();

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.11.;20,00;Forint átutalás;Ref;Employer;Salary
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk()
            ->assertJsonPath('drafts.0.file_import_profile_id', $systemProfile->id);
    }

    public function test_csv_parse_returns_422_when_no_profile_and_no_account_default(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);

        $csv = <<<'CSV'
Date,Amount,Payee
2025-01-01,-10.00,Test
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'CSV_PROFILE_REQUIRED');
    }

    public function test_csv_parse_valid_with_user_profile_mapping_only(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);

        $userProfile = FileImportProfile::factory()->create([
            'user_id' => $user->id,
            'type' => 'user',
            'delimiter' => ',',
            'decimal_separator' => '.',
            'thousand_separator' => ',',
            'mapping_json' => [
                'Date' => 'date',
                'Amount' => 'amount',
                'Payee' => 'payee',
                'Memo' => 'memo',
            ],
            'options_json' => [
                'parser_settings' => [
                    'trim_strings' => true,
                    'skip_empty_rows' => true,
                ],
            ],
        ]);

        $csv = <<<'CSV'
Date,Amount,Payee,Memo
2025-01-03,-12.50,Coffee,Morning coffee
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $userProfile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk()
            ->assertJsonPath('drafts.0.file_import_profile_id', $userProfile->id)
            ->assertJsonPath('drafts.0.transaction_type', 'withdrawal')
            ->assertJsonPath('drafts.0.amount', 12.5);
    }

    public function test_csv_parse_returns_partial_success_payload_for_mixed_valid_and_invalid_rows(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $profile = $this->createSystemProfile();

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.11.;-50,25;Elektronikus forint átutalás;Ref;Vendor;Memo
BAD_DATE;abc;Elektronikus forint átutalás;Ref;Broken;Memo
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $profile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.total_drafts', 2)
            ->assertJsonPath('drafts.0.status', 'pending_review')
            ->assertJsonPath('drafts.1.status', 'failed_validation');

        $this->assertNotEmpty($response->json('drafts.1.warnings'));
    }

    public function test_csv_parse_returns_structured_422_for_structural_errors(): void
    {
        Config::set('yaffa.import_max_rows', 1);

        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $profile = $this->createSystemProfile();

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.11.;-50,25;Elektronikus forint átutalás;Ref;Vendor;Memo
2025.01.12.;-20,00;Elektronikus forint átutalás;Ref;Vendor;Memo
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $profile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'IMPORT_PARSE_FAILED');
    }

    public function test_csv_parse_returns_422_for_foreign_owned_profile_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);

        $foreignProfile = FileImportProfile::factory()->create([
            'user_id' => $otherUser->id,
            'type' => 'user',
            'file_type' => 'csv',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $foreignProfile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', "Date,Amount\n2025-01-01,-10.00"),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonMissingPath('error');
    }

    public function test_csv_parse_returns_422_for_nonexistent_profile_id(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => 999999,
                'file' => UploadedFile::fake()->createWithContent('import.csv', "Date,Amount\n2025-01-01,-10.00"),
            ]);

        $response->assertUnprocessable();
    }

    public function test_profile_store_rejects_multi_char_delimiter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.file-profiles.store'), [
                'name' => 'Bad Delimiter Profile',
                'delimiter' => '--',
                'has_header_row' => true,
                'mapping_json' => ['Date' => 'date', 'Amount' => 'amount'],
            ]);

        $response->assertUnprocessable();
    }

    public function test_profile_update_rejects_multi_char_delimiter(): void
    {
        $user = User::factory()->create();

        $profile = FileImportProfile::factory()->create([
            'user_id' => $user->id,
            'type' => 'user',
            'delimiter' => ',',
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('api.v1.imports.file-profiles.update', $profile), [
                'delimiter' => ';;',
            ]);

        $response->assertUnprocessable();
    }

    public function test_profile_create_and_update_reject_forbidden_executable_keys(): void
    {
        $user = User::factory()->create();

        $createResponse = $this->actingAs($user)
            ->postJson(route('api.v1.imports.file-profiles.store'), [
                'name' => 'Unsafe profile',
                'delimiter' => ',',
                'has_header_row' => true,
                'mapping_json' => ['Date' => 'date', 'Amount' => 'amount'],
                'options_json' => [
                    'matching_rules' => [
                        ['conditions' => ['all' => []], 'actions' => []],
                    ],
                ],
            ]);

        $createResponse->assertUnprocessable();

        $safeProfile = FileImportProfile::factory()->create([
            'user_id' => $user->id,
            'type' => 'user',
        ]);

        $updateResponse = $this->actingAs($user)
            ->patchJson(route('api.v1.imports.file-profiles.update', ['profile' => $safeProfile->id]), [
                'options_json' => [
                    'actions' => [['type' => 'set', 'target' => 'transaction_type', 'value' => 'withdrawal']],
                ],
            ]);

        $updateResponse->assertUnprocessable();
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

    private function createAccountEntity(User $user): AccountEntity
    {
        return AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
            ]);
    }
}
