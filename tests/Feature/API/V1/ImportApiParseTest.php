<?php

namespace Tests\Feature\API\V1;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                    'category',
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
