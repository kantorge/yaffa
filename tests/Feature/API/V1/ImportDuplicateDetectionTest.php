<?php

namespace Tests\Feature\API\V1;

use App\Models\FileImportProfile;
use App\Models\User;
use App\Services\Import\SystemFileImportProfileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesTestTransactions;
use Tests\TestCase;

class ImportDuplicateDetectionTest extends TestCase
{
    use CreatesTestTransactions;
    use RefreshDatabase;

    public function test_csv_parse_enriches_drafts_with_duplicate_candidates_and_similarity_scores(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $profile = $this->createSystemProfile();

        $existing = $this->createStandardTransaction($user, $accountEntity->id, null, 99.99, '2025-01-12');

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.12.;-99,99;Elektronikus forint átutalás;Ref;Vendor A;Memo
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $profile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'drafts.0.duplicate_candidates');
        $response->assertJsonPath('drafts.0.duplicate_candidates.0.transaction_id', $existing->id);
        $response->assertJsonStructure([
            'drafts' => [[
                'duplicate_candidates' => [[
                    'transaction_id',
                    'confidence_score',
                    'similarity_score',
                    'matched_on',
                    'summary' => ['date', 'comment', 'amount'],
                ]],
            ]],
        ]);
    }

    public function test_csv_duplicate_candidates_are_bounded(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $profile = $this->createSystemProfile();

        for ($i = 0; $i < 15; $i++) {
            $this->createStandardTransaction($user, $accountEntity->id, null, 50.00, '2025-01-15');
        }

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.15.;-50,00;Elektronikus forint átutalás;Ref;Vendor B;Memo
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $profile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk();

        $candidates = $response->json('drafts.0.duplicate_candidates');
        $this->assertIsArray($candidates);
        $this->assertLessThanOrEqual(10, count($candidates));
    }

    public function test_qif_duplicate_candidate_labels_identified_payee_as_payee_signal(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);

        $payee = $this->createPayeeEntity($user, ['active' => true, 'name' => 'Grocery Store']);

        $existing = $this->createStandardTransaction($user, $accountEntity->id, $payee->id, 50.00, '2025-01-20');

        $qif = <<<'QIF'
!Type:Bank
D2025-01-20
T-50.00
PGrocery Store
^
QIF;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'qif',
                'account_id' => $accountEntity->id,
                'file' => UploadedFile::fake()->createWithContent('import.qif', $qif),
            ]);

        $response->assertOk();
        $response->assertJsonPath('drafts.0.duplicate_candidates.0.transaction_id', $existing->id);

        $matchedOn = $response->json('drafts.0.duplicate_candidates.0.matched_on');
        $this->assertContains('payee', $matchedOn);
        $this->assertNotContains('account_to', $matchedOn);
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
