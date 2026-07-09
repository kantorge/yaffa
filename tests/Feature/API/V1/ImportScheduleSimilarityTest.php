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
use App\Models\TransactionSchedule;
use App\Models\User;
use App\Services\Import\SystemFileImportProfileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportScheduleSimilarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_parse_enriches_drafts_with_schedule_candidates_and_similarity_scores(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $profile = $this->createSystemProfile();

        $scheduled = $this->createScheduledStandardTransaction($user, $accountEntity->id, null, 99.99, '2025-01-12');

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
        $response->assertJsonCount(1, 'drafts.0.schedule_candidates');
        $response->assertJsonPath('drafts.0.schedule_candidates.0.transaction_id', $scheduled->id);
        $response->assertJsonPath('drafts.0.schedule_candidates.0.summary.next_date', '2025-01-12');
        $response->assertJsonPath('drafts.0.schedule_candidates.0.summary.frequency', 'MONTHLY');
        $response->assertJsonStructure([
            'drafts' => [[
                'schedule_candidates' => [[
                    'transaction_id',
                    'confidence_score',
                    'similarity_score',
                    'matched_on',
                    'summary' => ['next_date', 'comment', 'amount', 'frequency'],
                ]],
            ]],
        ]);
    }

    public function test_csv_schedule_candidates_are_bounded(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $profile = $this->createSystemProfile();

        for ($i = 0; $i < 15; $i++) {
            $this->createScheduledStandardTransaction($user, $accountEntity->id, null, 50.00, '2025-01-15');
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

        $candidates = $response->json('drafts.0.schedule_candidates');
        $this->assertIsArray($candidates);
        $this->assertLessThanOrEqual(10, count($candidates));
    }

    public function test_inactive_schedule_is_excluded_from_candidates(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $profile = $this->createSystemProfile();

        $scheduled = $this->createScheduledStandardTransaction($user, $accountEntity->id, null, 75.00, '2025-01-20');

        // Force the schedule inactive directly in the DB, bypassing the model's
        // creating/updating hooks that would otherwise recompute `active` from `next_date`.
        DB::table('transaction_schedules')
            ->where('transaction_id', $scheduled->id)
            ->update(['active' => false]);

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.20.;-75,00;Elektronikus forint átutalás;Ref;Vendor C;Memo
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $profile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'drafts.0.schedule_candidates');
    }

    public function test_non_schedule_transaction_does_not_appear_as_schedule_candidate(): void
    {
        $user = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $profile = $this->createSystemProfile();

        $this->createStandardTransaction($user, $accountEntity->id, null, 60.00, '2025-01-18');

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.18.;-60,00;Elektronikus forint átutalás;Ref;Vendor D;Memo
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $profile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'drafts.0.schedule_candidates');
        // Sanity check: it is still correctly picked up as a regular duplicate candidate.
        $response->assertJsonCount(1, 'drafts.0.duplicate_candidates');
    }

    public function test_schedule_candidates_are_isolated_per_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $otherAccountEntity = $this->createAccountEntity($otherUser);
        $profile = $this->createSystemProfile();

        $this->createScheduledStandardTransaction($otherUser, $otherAccountEntity->id, null, 42.00, '2025-01-22');

        $csv = <<<'CSV'
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.22.;-42,00;Elektronikus forint átutalás;Ref;Vendor E;Memo
CSV;

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $accountEntity->id,
                'file_import_profile_id' => $profile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'drafts.0.schedule_candidates');
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
            'comment' => 'Non-schedule candidate test item',
        ]);

        return $transaction;
    }

    private function createScheduledStandardTransaction(
        User $user,
        int $accountFromId,
        ?int $accountToId,
        float $amount,
        string $nextDate,
    ): Transaction {
        $detail = TransactionDetailStandard::query()->create([
            'account_from_id' => $accountFromId,
            'account_to_id' => $accountToId,
            'amount_from' => $amount,
            'amount_to' => $amount,
        ]);

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'date' => $nextDate,
            'transaction_type' => TransactionType::WITHDRAWAL->value,
            'reconciled' => false,
            'schedule' => true,
            'budget' => false,
            'comment' => 'Scheduled candidate test transaction',
            'config_type' => 'standard',
            'config_id' => $detail->id,
        ]);

        $category = Category::factory()->for($user)->create(['active' => true]);

        TransactionItem::query()->create([
            'transaction_id' => $transaction->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'comment' => null,
        ]);

        TransactionSchedule::query()->create([
            'transaction_id' => $transaction->id,
            'start_date' => $nextDate,
            'next_date' => $nextDate,
            'end_date' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'count' => null,
            'automatic_recording' => false,
        ]);

        return $transaction;
    }
}
