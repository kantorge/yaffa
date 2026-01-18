<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountBalanceCheckpoint;
use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use App\Services\BalanceCheckpointService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceCheckpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected AccountEntity $accountEntity;
    protected Account $account;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create currency
        $this->currency = Currency::factory()->create([
            'iso_code' => 'GBP',
        ]);

        // Create account entity and account
        $this->accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->account = Account::factory()->create([
            'account_entity_id' => $this->accountEntity->id,
            'currency_id' => $this->currency->id,
            'opening_balance' => 1000.00,
        ]);

        // Associate account with entity
        $this->accountEntity->accountable_id = $this->account->id;
        $this->accountEntity->accountable_type = Account::class;
        $this->accountEntity->save();

        $this->actingAs($this->user);
    }

    public function test_can_create_balance_checkpoint(): void
    {
        $response = $this->postJson('/api/balance-checkpoints', [
            'account_entity_id' => $this->accountEntity->id,
            'checkpoint_date' => '2025-12-31',
            'balance' => 5000.00,
            'note' => 'Year end balance',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'user_id',
                'account_entity_id',
                'checkpoint_date',
                'balance',
                'note',
                'active',
            ]);

        $this->assertDatabaseHas('account_balance_checkpoints', [
            'account_entity_id' => $this->accountEntity->id,
            'checkpoint_date' => '2025-12-31',
            'balance' => 5000.00,
            'active' => true,
        ]);
    }

    public function test_can_list_checkpoints_for_account(): void
    {
        // Create some checkpoints
        AccountBalanceCheckpoint::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'account_entity_id' => $this->accountEntity->id,
        ]);

        $response = $this->getJson("/api/balance-checkpoints?account_entity_id={$this->accountEntity->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_can_update_checkpoint(): void
    {
        $checkpoint = AccountBalanceCheckpoint::factory()->create([
            'user_id' => $this->user->id,
            'account_entity_id' => $this->accountEntity->id,
            'balance' => 5000.00,
        ]);

        $response = $this->putJson("/api/balance-checkpoints/{$checkpoint->id}", [
            'balance' => 5500.00,
            'note' => 'Updated balance',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('account_balance_checkpoints', [
            'id' => $checkpoint->id,
            'balance' => 5500.00,
            'note' => 'Updated balance',
        ]);
    }

    public function test_can_deactivate_checkpoint(): void
    {
        $checkpoint = AccountBalanceCheckpoint::factory()->create([
            'user_id' => $this->user->id,
            'account_entity_id' => $this->accountEntity->id,
            'active' => true,
        ]);

        $response = $this->deleteJson("/api/balance-checkpoints/{$checkpoint->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('account_balance_checkpoints', [
            'id' => $checkpoint->id,
            'active' => false,
        ]);
    }

    public function test_can_reconcile_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'reconciled' => false,
        ]);

        $response = $this->postJson('/api/balance-checkpoints/toggle-reconciliation', [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'reconciled' => true,
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'reconciled' => true,
            'reconciled_by' => $this->user->id,
        ]);
    }

    public function test_can_unreconcile_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'reconciled' => true,
            'reconciled_by' => $this->user->id,
            'reconciled_at' => now(),
        ]);

        $response = $this->postJson('/api/balance-checkpoints/toggle-reconciliation', [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'reconciled' => false,
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'reconciled' => false,
            'reconciled_by' => null,
            'reconciled_at' => null,
        ]);
    }

    public function test_balance_checkpoint_service_calculates_balance_correctly(): void
    {
        $service = new BalanceCheckpointService();

        // Create transactions
        $transaction1 = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'date' => Carbon::parse('2025-01-15'),
            'schedule' => false,
            'budget' => false,
        ]);

        TransactionDetailStandard::factory()->create([
            'transaction_id' => $transaction1->id,
            'account_to_id' => $this->accountEntity->id,
            'amount_to' => 500.00,
        ]);

        $balance = $service->calculateBalanceAtDate(
            $this->accountEntity->id,
            Carbon::parse('2025-01-31')
        );

        // Opening balance (1000) + transaction (500) = 1500
        $this->assertEquals(1500.00, $balance);
    }

    public function test_checkpoint_prevents_violating_transaction(): void
    {
        // We need to disable config caching for this test
        config(['yaffa.balance_checkpoint_enabled' => true]);

        // Create a checkpoint
        $checkpoint = AccountBalanceCheckpoint::factory()->create([
            'user_id' => $this->user->id,
            'account_entity_id' => $this->accountEntity->id,
            'checkpoint_date' => Carbon::parse('2025-12-31'),
            'balance' => 1000.00, // Same as opening balance (no transactions)
            'active' => true,
        ]);

        // Try to create a transaction before the checkpoint date
        // This should be rejected because it would change the balance at the checkpoint
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $transaction = Transaction::factory()->make([
            'user_id' => $this->user->id,
            'date' => Carbon::parse('2025-12-15'),
            'schedule' => false,
            'budget' => false,
        ]);

        $detail = TransactionDetailStandard::factory()->make([
            'account_to_id' => $this->accountEntity->id,
            'amount_to' => 500.00,
        ]);

        $transaction->save();
        $detail->transaction_id = $transaction->id;
        $detail->save();
    }

    public function test_reconciled_transaction_cannot_be_modified(): void
    {
        config(['yaffa.balance_checkpoint_enabled' => true]);

        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'reconciled' => true,
            'reconciled_by' => $this->user->id,
            'reconciled_at' => now(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $transaction->date = Carbon::now()->addDay();
        $transaction->save();
    }

    public function test_feature_can_be_disabled_via_config(): void
    {
        config(['yaffa.balance_checkpoint_enabled' => false]);

        $service = new BalanceCheckpointService();

        $this->assertFalse($service->isEnabled());
    }
}
