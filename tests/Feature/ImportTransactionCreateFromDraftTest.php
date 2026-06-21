<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\Category;
use App\Models\FileImportProfile;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Import\SystemFileImportProfileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportTransactionCreateFromDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalize_csv_draft_creates_exactly_one_transaction_with_normalized_values(): void
    {
        $user = User::factory()->create();
        $account = $this->createAccountEntity($user);
        $payee = $this->createPayeeEntity($user, 'Vendor');
        $category = Category::factory()->for($user)->create(['active' => true]);
        $profile = $this->createSystemProfile();

        $draft = $this->parseCsvDraft($user, $account, $profile, 'Vendor');
        $payload = $this->buildFinalizePayload($draft, $category->id, $payee->id);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.transactions.store-standard'), $payload);

        $response->assertOk();

        $transactionId = $response->json('transaction.id');
        $this->assertNotNull($transactionId);
        $this->assertDatabaseCount('transactions', 1);

        $transaction = Transaction::query()->with(['config', 'transactionItems'])->findOrFail($transactionId);
        $this->assertSame('2025-01-11', $transaction->date?->format('Y-m-d'));
        $this->assertSame('withdrawal', $transaction->transaction_type->value);
        $this->assertSame($account->id, $transaction->config->account_from_id);
        $this->assertSame($payee->id, $transaction->config->account_to_id);
        $this->assertSame(50.25, (float) $transaction->config->amount_from);
        $this->assertCount(1, $transaction->transactionItems);
        $this->assertSame(50.25, (float) $transaction->transactionItems[0]->amount);

        $updatePayload = $payload;
        $updatePayload['action'] = 'edit';
        $updatePayload['comment'] = 'Updated after import';

        $this->actingAs($user, 'sanctum')
            ->patchJson(route('api.v1.transactions.update-standard', $transaction), $updatePayload)
            ->assertOk()
            ->assertJsonPath('transaction.comment', 'Updated after import');
    }

    public function test_draft_with_warnings_can_still_finalize(): void
    {
        $user = User::factory()->create();
        $account = $this->createAccountEntity($user);
        $payee = $this->createPayeeEntity($user, 'Grocery Store');
        $category = Category::factory()->for($user)->create(['active' => true]);

        $qifContent = <<<'QIF'
!Type:Bank
D01/02/2025
T-12.50
PGrocery Store
MWarning-friendly import
^
QIF;

        $parseResponse = $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'qif',
                'account_id' => $account->id,
                'file' => UploadedFile::fake()->createWithContent('import.qif', $qifContent),
            ]);

        $parseResponse->assertOk();
        $draft = $parseResponse->json('drafts.0');

        $this->assertContains(
            'Ambiguous date format "01/02/2025" was parsed using day/month interpretation.',
            $draft['warnings'],
        );

        $payload = $this->buildFinalizePayload($draft, $category->id, $payee->id);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.transactions.store-standard'), $payload)
            ->assertOk();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_failed_finalization_does_not_create_transaction_and_requires_explicit_retry(): void
    {
        $user = User::factory()->create();
        $account = $this->createAccountEntity($user);
        $payee = $this->createPayeeEntity($user, 'Retry Vendor');
        $category = Category::factory()->for($user)->create(['active' => true]);
        $profile = $this->createSystemProfile();

        $draft = $this->parseCsvDraft($user, $account, $profile, 'Retry Vendor');
        $payload = $this->buildFinalizePayload($draft, $category->id, $payee->id);

        $invalidPayload = $payload;
        $invalidPayload['items'][0]['category_id'] = 999999;

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.transactions.store-standard'), $invalidPayload)
            ->assertUnprocessable();

        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.transactions.store-standard'), $payload)
            ->assertOk();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_repeated_finalize_with_same_ai_document_does_not_create_duplicate_transaction(): void
    {
        $user = User::factory()->create();
        $account = $this->createAccountEntity($user);
        $payee = $this->createPayeeEntity($user, 'AI Vendor');
        $category = Category::factory()->for($user)->create(['active' => true]);
        $profile = $this->createSystemProfile();
        $aiDocument = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_transaction_data' => [
                'date' => '2025-01-11',
                'payee' => 'AI Vendor',
                'amount' => 50.25,
            ],
        ]);

        $draft = $this->parseCsvDraft($user, $account, $profile, 'AI Vendor');
        $payload = $this->buildFinalizePayload($draft, $category->id, $payee->id, $aiDocument->id);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.transactions.store-standard'), $payload)
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.transactions.store-standard'), $payload)
            ->assertUnprocessable();

        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('finalized', $aiDocument->fresh()->status);
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

    private function createPayeeEntity(User $user, string $name): AccountEntity
    {
        return AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => $name,
            ]);
    }

    private function createSystemProfile(): FileImportProfile
    {
        $definitions = (new SystemFileImportProfileRegistry())->profiles();
        $definition = collect($definitions)->firstWhere('file_type', 'csv')
            ?? $definitions[0];

        return FileImportProfile::query()->updateOrCreate(
            ['key' => $definition['key']],
            [
                'user_id' => null,
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
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCsvDraft(User $user, AccountEntity $account, FileImportProfile $profile, string $payeeName): array
    {
        $csv = <<<CSV
Értéknap;Összeg;Típus;Közlemény/1;Közlemény/2;Közlemény/3
2025.01.11.;-50,25;Elektronikus forint átutalás;Ref;{$payeeName};Memo
CSV;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.parse'), [
                'source_type' => 'csv',
                'account_id' => $account->id,
                'file_import_profile_id' => $profile->id,
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk();

        return $response->json('drafts.0');
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function buildFinalizePayload(array $draft, int $categoryId, int $payeeId, ?int $aiDocumentId = null): array
    {
        $payload = [
            'action' => 'finalize',
            'transaction_type' => $draft['transaction_type'],
            'config_type' => 'standard',
            'date' => $draft['date'],
            'comment' => $draft['memo'] ?? null,
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_from_id' => data_get($draft, 'config.account_from_id'),
                'account_to_id' => $payeeId,
                'amount_from' => $draft['amount'],
                'amount_to' => $draft['amount'],
            ],
            'items' => [[
                'amount' => $draft['amount'],
                'category_id' => $categoryId,
                'comment' => $draft['memo'] ?? null,
                'tags' => [],
            ]],
        ];

        if ($draft['transaction_type'] === 'deposit') {
            $payload['config']['account_from_id'] = $payeeId;
            $payload['config']['account_to_id'] = data_get($draft, 'config.account_to_id');
        }

        if ($aiDocumentId !== null) {
            $payload['ai_document_id'] = $aiDocumentId;
        }

        return $payload;
    }
}
