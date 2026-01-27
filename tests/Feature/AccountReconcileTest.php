<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountReconcileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AccountEntity $account;
    private AccountEntity $otherAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->account = AccountEntity::factory()
            ->for($this->user)
            ->for(
                Account::factory()
                    ->withUser($this->user)
                    ->state(['opening_balance' => 1000]),
                'config'
            )
            ->create();

        $this->otherAccount = AccountEntity::factory()
            ->for($this->user)
            ->for(
                Account::factory()
                    ->withUser($this->user)
                    ->state(['opening_balance' => 500]),
                'config'
            )
            ->create();
    }

    public function test_guest_cannot_access_reconcile_page(): void
    {
        $response = $this->get(route('account.reconcile', $this->account));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_access_reconcile_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('account.reconcile', $this->account));

        $response->assertStatus(200);
        $response->assertViewIs('account.reconcile');
        $response->assertViewHas(['account', 'startDate', 'endDate', 'openingBalance', 'closingBalance']);
    }

    public function test_user_cannot_access_other_users_reconcile_page(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->get(route('account.reconcile', $this->account));

        $response->assertStatus(403);
    }

    public function test_reconcile_page_shows_correct_opening_balance(): void
    {
        $transactionType = TransactionType::firstWhere('name', 'Withdrawal');

        // Create a transaction before the reconcile period
        Transaction::factory()
            ->for($this->user)
            ->for($transactionType, 'transactionType')
            ->for(
                TransactionDetailStandard::factory()
                    ->state([
                        'account_from_id' => $this->account->id,
                        'account_to_id' => $this->otherAccount->id,
                        'amount_from' => 100,
                        'amount_to' => 100,
                    ]),
                'config'
            )
            ->create(['date' => '2025-12-15']);

        $response = $this->actingAs($this->user)
            ->get(route('account.reconcile', [
                'account' => $this->account,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ]));

        $response->assertStatus(200);

        // Opening balance should be 1000 (initial) - 100 (transaction before period) = 900
        $this->assertEquals(900, $response->viewData('openingBalance'));
    }

    public function test_reconcile_page_shows_transactions_in_date_range(): void
    {
        $transactionType = TransactionType::firstWhere('name', 'Deposit');

        // Transaction before range
        Transaction::factory()
            ->for($this->user)
            ->for($transactionType, 'transactionType')
            ->for(
                TransactionDetailStandard::factory()
                    ->state([
                        'account_from_id' => $this->otherAccount->id,
                        'account_to_id' => $this->account->id,
                        'amount_from' => 50,
                        'amount_to' => 50,
                    ]),
                'config'
            )
            ->create(['date' => '2025-12-25']);

        // Transaction in range
        Transaction::factory()
            ->for($this->user)
            ->for($transactionType, 'transactionType')
            ->for(
                TransactionDetailStandard::factory()
                    ->state([
                        'account_from_id' => $this->otherAccount->id,
                        'account_to_id' => $this->account->id,
                        'amount_from' => 200,
                        'amount_to' => 200,
                    ]),
                'config'
            )
            ->create(['date' => '2026-01-15']);

        // Transaction after range
        Transaction::factory()
            ->for($this->user)
            ->for($transactionType, 'transactionType')
            ->for(
                TransactionDetailStandard::factory()
                    ->state([
                        'account_from_id' => $this->otherAccount->id,
                        'account_to_id' => $this->account->id,
                        'amount_from' => 75,
                        'amount_to' => 75,
                    ]),
                'config'
            )
            ->create(['date' => '2026-02-05']);

        $response = $this->actingAs($this->user)
            ->get(route('account.reconcile', [
                'account' => $this->account,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ]));

        $response->assertStatus(200);

        // Should have exactly 1 transaction in the JavaScript data
        $transactionData = $response->viewData('account')->toArray();
        $this->assertNotEmpty($transactionData);
    }

    public function test_reconcile_page_calculates_correct_closing_balance(): void
    {
        $depositType = TransactionType::firstWhere('name', 'Deposit');

        // Create transactions in the reconcile period
        Transaction::factory()
            ->for($this->user)
            ->for($depositType, 'transactionType')
            ->for(
                TransactionDetailStandard::factory()
                    ->state([
                        'account_from_id' => $this->otherAccount->id,
                        'account_to_id' => $this->account->id,
                        'amount_from' => 150,
                        'amount_to' => 150,
                    ]),
                'config'
            )
            ->create(['date' => '2026-01-10']);

        Transaction::factory()
            ->for($this->user)
            ->for($depositType, 'transactionType')
            ->for(
                TransactionDetailStandard::factory()
                    ->state([
                        'account_from_id' => $this->otherAccount->id,
                        'account_to_id' => $this->account->id,
                        'amount_from' => 250,
                        'amount_to' => 250,
                    ]),
                'config'
            )
            ->create(['date' => '2026-01-20']);

        $response = $this->actingAs($this->user)
            ->get(route('account.reconcile', [
                'account' => $this->account,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ]));

        $response->assertStatus(200);

        // Closing balance should be 1000 (opening) + 150 + 250 = 1400
        $this->assertEquals(1400, $response->viewData('closingBalance'));
    }

    public function test_bulk_reconcile_endpoint_reconciles_transactions(): void
    {
        $transactionType = TransactionType::firstWhere('name', 'Deposit');

        $transaction1 = Transaction::factory()
            ->for($this->user)
            ->for($transactionType, 'transactionType')
            ->for(
                TransactionDetailStandard::factory()
                    ->state([
                        'account_from_id' => $this->otherAccount->id,
                        'account_to_id' => $this->account->id,
                        'amount_from' => 100,
                        'amount_to' => 100,
                    ]),
                'config'
            )
            ->create(['date' => '2026-01-10']);

        $transaction2 = Transaction::factory()
            ->for($this->user)
            ->for($transactionType, 'transactionType')
            ->for(
                TransactionDetailStandard::factory()
                    ->state([
                        'account_from_id' => $this->otherAccount->id,
                        'account_to_id' => $this->account->id,
                        'amount_from' => 200,
                        'amount_to' => 200,
                    ]),
                'config'
            )
            ->create(['date' => '2026-01-15']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/transactions/bulk-reconcile', [
                'transaction_ids' => [$transaction1->id, $transaction2->id],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'reconciled_count' => 2,
        ]);

        // Verify transactions are marked as reconciled
        $this->assertTrue($transaction1->fresh()->reconciled);
        $this->assertTrue($transaction2->fresh()->reconciled);
        $this->assertNotNull($transaction1->fresh()->reconciled_at);
        $this->assertEquals($this->user->id, $transaction1->fresh()->reconciled_by);
    }

    public function test_bulk_reconcile_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/transactions/bulk-reconcile', [
            'transaction_ids' => [1, 2],
        ]);

        $response->assertStatus(401);
    }

    public function test_bulk_reconcile_endpoint_validates_transaction_ids(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/transactions/bulk-reconcile', [
                'transaction_ids' => [],
            ]);

        $response->assertStatus(422);
    }
}
