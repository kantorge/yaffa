<?php

namespace Tests\Feature\API\V1;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\FileImportProfile;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\Import\SystemFileImportProfileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportDuplicateDetectionTest extends TestCase
{
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

    private function createStandardTransaction(
        User $user,
        int $accountFromId,
        ?int $accountToId,
        float $amount,
        string $date,
    ): Transaction {
        $detail = TransactionDetailStandard::query()->create([
            'account_from_id' => $accountFromId,
            'account_to_id' => $accountToId,
            'amount_from' => $amount,
            'amount_to' => $amount,
        ]);

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'date' => $date,
            'transaction_type' => TransactionType::WITHDRAWAL->value,
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'comment' => null,
            'config_type' => 'standard',
            'config_id' => $detail->id,
        ]);

        $category = Category::factory()->for($user)->create(['active' => true]);

        TransactionItem::query()->create([
            'transaction_id' => $transaction->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'comment' => 'Imported duplicate candidate',
        ]);

        return $transaction;
    }
}
