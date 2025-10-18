<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new TransactionService();
    }

    public function test_getAccountStandardTransactions_returnsStandardTransactions(): void
    {
        // Create a user and account
        $user = User::factory()->create();
        $account = AccountEntity::factory()
            ->for(Account::factory()->withUser($user), 'config')
            ->for($user)
            ->create();

        // Create a payee
        $payee = AccountEntity::factory()
            ->for(Payee::factory()->withUser($user), 'config')
            ->for($user)
            ->create();

        // Create a standard transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 10,
                    'amount_to' => 10,
                    'account_from_id' => $account->id,
                    'account_to_id' => $payee->id,
                ]),
                'config'
            )
            ->create([
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                'config_type' => 'standard',
            ]);

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
        $account = AccountEntity::factory()
            ->for(Account::factory()->withUser($user), 'config')
            ->for($user)
            ->create();

        // Create investment group and investment
        InvestmentGroup::factory()->for($user)->create();
        $investment = Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'currency_id' => $account->config->currency_id,
            ]);

        // Create an investment transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->buy($user, [
                'investment_id' => $investment->id,
                'account_id' => $account->id,
            ])
            ->create([
                'schedule' => false,
                'budget' => false,
            ]);

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
        $accountFrom = AccountEntity::factory()
            ->for(Account::factory()->withUser($user), 'config')
            ->for($user)
            ->create(['name' => 'Account From']);
        
        $accountTo = AccountEntity::factory()
            ->for(Payee::factory()->withUser($user), 'config')
            ->for($user)
            ->create(['name' => 'Account To']);

        // Create a standard transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 100.00,
                    'amount_to' => 100.00,
                    'account_from_id' => $accountFrom->id,
                    'account_to_id' => $accountTo->id,
                ]),
                'config'
            )
            ->create([
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                'config_type' => 'standard',
            ]);

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
        $account = AccountEntity::factory()
            ->for(Account::factory()->withUser($user), 'config')
            ->for($user)
            ->create(['name' => 'Investment Account']);

        // Create investment group and investment
        InvestmentGroup::factory()->for($user)->create();
        $investment = Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'currency_id' => $account->config->currency_id,
            ]);

        // Create an investment transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->buy($user, [
                'investment_id' => $investment->id,
                'account_id' => $account->id,
                'quantity' => 10,
                'price' => 50.00,
            ])
            ->create([
                'schedule' => false,
                'budget' => false,
            ]);

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
        $this->assertNotNull($enrichedTransaction->amount_from);
        $this->assertNull($enrichedTransaction->amount_to);
        $this->assertEquals(10, $enrichedTransaction->quantity);
        $this->assertEquals(50.00, $enrichedTransaction->price);
    }

    public function test_enrichTransactionForDisplay_setsScheduleGroup(): void
    {
        // Create a user and account
        $user = User::factory()->create();
        $account = AccountEntity::factory()
            ->for(Account::factory()->withUser($user), 'config')
            ->for($user)
            ->create();

        // Create a payee
        $payee = AccountEntity::factory()
            ->for(Payee::factory()->withUser($user), 'config')
            ->for($user)
            ->create();

        // Create a scheduled transaction
        $transaction = Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 10,
                    'amount_to' => 10,
                    'account_from_id' => $account->id,
                    'account_to_id' => $payee->id,
                ]),
                'config'
            )
            ->create([
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                'config_type' => 'standard',
                'schedule' => true,
                'budget' => false,
            ]);

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
