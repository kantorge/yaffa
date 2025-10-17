<?php

namespace Tests\Unit\Services;

use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations to seed transaction types
        Artisan::call('migrate:fresh');
        
        $this->service = new TransactionService();
    }

    public function test_getAccountStandardTransactions_returnsStandardTransactions(): void
    {
        // Create a user and account
        $user = User::factory()->create();
        $account = AccountEntity::factory()->for($user)->create();

        // Get existing transaction type
        $transactionType = TransactionType::where('name', 'withdrawal')->first();

        // Create a standard transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->for($transactionType, 'transactionType')
            ->create([
                'schedule' => false,
                'budget' => false,
                'config_type' => 'standard',
            ]);

        // Create transaction detail linking to the account
        TransactionDetailStandard::factory()
            ->for($transaction, 'transaction')
            ->create([
                'account_from_id' => $account->id,
                'account_to_id' => AccountEntity::factory()->for($user)->create()->id,
            ]);

        // Update the transaction to link to the config
        $transaction->update(['config_id' => $transaction->fresh()->config->id]);

        // Get transactions
        $transactions = $this->service->getAccountStandardTransactions($account, $user->id);

        // Assert
        $this->assertCount(1, $transactions);
        $this->assertEquals($transaction->id, $transactions->first()->id);
    }

    public function test_getAccountInvestmentTransactions_returnsInvestmentTransactions(): void
    {
        // Create a user and account
        $user = User::factory()->create();
        $account = AccountEntity::factory()->for($user)->create();

        // Get existing transaction type
        $transactionType = TransactionType::where('name', 'Buy')->where('type', 'investment')->first();

        // Create an investment transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->for($transactionType, 'transactionType')
            ->create([
                'schedule' => false,
                'budget' => false,
                'config_type' => 'investment',
            ]);

        // Create transaction detail linking to the account
        TransactionDetailInvestment::factory()
            ->for($transaction, 'transaction')
            ->create([
                'account_id' => $account->id,
            ]);

        // Update the transaction to link to the config
        $transaction->update(['config_id' => $transaction->fresh()->config->id]);

        // Get transactions
        $transactions = $this->service->getAccountInvestmentTransactions($account, $user->id);

        // Assert
        $this->assertCount(1, $transactions);
        $this->assertEquals($transaction->id, $transactions->first()->id);
    }

    public function test_enrichTransactionForDisplay_enrichesStandardTransaction(): void
    {
        // Create a user and accounts
        $user = User::factory()->create();
        $accountFrom = AccountEntity::factory()->for($user)->create(['name' => 'Account From']);
        $accountTo = AccountEntity::factory()->for($user)->create(['name' => 'Account To']);

        // Get existing transaction type
        $transactionType = TransactionType::where('name', 'withdrawal')->first();

        // Create a standard transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->for($transactionType, 'transactionType')
            ->create([
                'schedule' => false,
                'budget' => false,
                'config_type' => 'standard',
            ]);

        // Create transaction detail
        TransactionDetailStandard::factory()
            ->for($transaction, 'transaction')
            ->create([
                'account_from_id' => $accountFrom->id,
                'account_to_id' => $accountTo->id,
                'amount_from' => 100.00,
                'amount_to' => 100.00,
            ]);

        // Update the transaction to link to the config
        $transaction->update(['config_id' => $transaction->fresh()->config->id]);

        // Reload the transaction with relationships
        $transaction = $transaction->fresh(['config', 'transactionType', 'transactionItems']);

        // Prepare account list
        $allAccounts = [
            $accountFrom->id => $accountFrom->name,
            $accountTo->id => $accountTo->name,
        ];

        // Enrich transaction
        $enrichedTransaction = $this->service->enrichTransactionForDisplay(
            $transaction,
            $accountFrom,
            $allAccounts
        );

        // Assert
        $this->assertEquals('history', $enrichedTransaction->transactionGroup);
        $this->assertEquals(-1, $enrichedTransaction->transactionOperator);
        $this->assertEquals('Account From', $enrichedTransaction->account_from_name);
        $this->assertEquals('Account To', $enrichedTransaction->account_to_name);
        $this->assertEquals(100.00, $enrichedTransaction->amount_from);
        $this->assertEquals(100.00, $enrichedTransaction->amount_to);
    }

    public function test_enrichTransactionForDisplay_enrichesInvestmentTransaction(): void
    {
        // Create a user and account with currency
        $user = User::factory()->create();
        $account = AccountEntity::factory()->for($user)->create(['name' => 'Investment Account']);

        // Get existing transaction type
        $transactionType = TransactionType::where('name', 'Buy')->where('type', 'investment')->first();

        // Create an investment transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->for($transactionType, 'transactionType')
            ->create([
                'schedule' => false,
                'budget' => false,
                'config_type' => 'investment',
                'cashflow_value' => -500.00,
            ]);

        // Create transaction detail
        TransactionDetailInvestment::factory()
            ->for($transaction, 'transaction')
            ->create([
                'account_id' => $account->id,
                'quantity' => 10,
                'price' => 50.00,
            ]);

        // Update the transaction to link to the config
        $transaction->update(['config_id' => $transaction->fresh()->config->id]);

        // Reload the transaction with relationships
        $transaction = $transaction->fresh(['config', 'config.investment', 'transactionType']);

        // Prepare account list
        $allAccounts = [
            $account->id => $account->name,
        ];

        // Enrich transaction
        $enrichedTransaction = $this->service->enrichTransactionForDisplay(
            $transaction,
            $account,
            $allAccounts
        );

        // Assert
        $this->assertEquals('history', $enrichedTransaction->transactionGroup);
        $this->assertEquals(-1, $enrichedTransaction->transactionOperator);
        $this->assertEquals('Investment Account', $enrichedTransaction->account_from_name);
        $this->assertEquals(500.00, $enrichedTransaction->amount_from);
        $this->assertNull($enrichedTransaction->amount_to);
        $this->assertEquals(10, $enrichedTransaction->quantity);
        $this->assertEquals(50.00, $enrichedTransaction->price);
    }

    public function test_enrichTransactionForDisplay_setsScheduleGroup(): void
    {
        // Create a user and account
        $user = User::factory()->create();
        $account = AccountEntity::factory()->for($user)->create();

        // Get existing transaction type
        $transactionType = TransactionType::where('name', 'withdrawal')->first();

        // Create a scheduled transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->for($transactionType, 'transactionType')
            ->create([
                'schedule' => true,
                'budget' => false,
                'config_type' => 'standard',
            ]);

        // Create transaction detail
        TransactionDetailStandard::factory()
            ->for($transaction, 'transaction')
            ->create([
                'account_from_id' => $account->id,
                'account_to_id' => AccountEntity::factory()->for($user)->create()->id,
            ]);

        // Update the transaction to link to the config
        $transaction->update(['config_id' => $transaction->fresh()->config->id]);

        // Reload the transaction
        $transaction = $transaction->fresh(['config', 'transactionType', 'transactionItems', 'transactionSchedule']);

        // Prepare account list
        $allAccounts = [$account->id => $account->name];

        // Enrich transaction
        $enrichedTransaction = $this->service->enrichTransactionForDisplay(
            $transaction,
            $account,
            $allAccounts
        );

        // Assert
        $this->assertEquals('schedule', $enrichedTransaction->transactionGroup);
    }
}
