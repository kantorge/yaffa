<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\AccountMonthlySummary;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountMonthlySummaryTest extends TestCase
{
    use RefreshDatabase;

    private function createBasicAssetsAndReturnUser(): User
    {
        // Create a user which will own the assets
        /** @var User $user */
        $user = User::factory()->create();

        // Create: account group, 2 currencies, 2 accounts with different currencies, payee
        AccountGroup::factory()
            ->for($user)
            ->create();

        Currency::factory()
            ->for($user)
            ->fromIsoCodes(['USD'])
            ->create(['base' => true]);

        Currency::factory()
            ->for($user)
            ->fromIsoCodes(['EUR'])
            ->create(['base' => null]);

        AccountEntity::factory()
            ->for($user)
            ->for(
                Account::factory()
                    ->withUser($user)
                    ->create(['currency_id' => $user->currencies()->base()->first()->id]),
                'config'
            )
            ->create();

        AccountEntity::factory()
            ->for($user)
            ->for(
                Account::factory()
                    ->withUser($user)
                    ->create(['currency_id' => $user->currencies()->notBase()->first()->id]),
                'config'
            )
            ->create();

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        // Also create an investment group and an investment
        InvestmentGroup::factory()
            ->for($user)
            ->create();

        Investment::factory()
            ->for($user)
            ->create(['currency_id' => $user->currencies()->base()->first()->id]);

        Investment::factory()
            ->for($user)
            ->create(['currency_id' => $user->currencies()->base()->first()->id]);

        return $user;
    }

    /** @test */
    public function test_standard_fact_is_calculated_correctly()
    {
        $user = $this->createBasicAssetsAndReturnUser();
        $account1 = $user->accounts()->first();
        $account2 = $user->accounts()->get()->last();
        $payee = $user->payees()->first();
        $investment = $user->investments()->first();

        // Create the first transaction: withdraw from account 1
        // Set a static date for the first day of the month
        $date = now()->startOfMonth();

        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 10,
                    'amount_to' => 10,
                    'account_from_id' => $account1->id,
                    'account_to_id' => $payee->id,
                ]),
                'config'
            )
            ->make([
                'date' => $date,
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,

            ])
            ->save();

        // Check the partial result
        $this->assertEquals(
            -10,
            AccountMonthlySummary::calculateAccountBalanceFact($account1, $date)
        );

        // Create the second transaction: deposit to account 1
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 20,
                    'amount_to' => 20,
                    'account_from_id' => $payee->id,
                    'account_to_id' => $account1->id,
                ]),
                'config'
            )
            ->make([
                'date' => $date,
                'transaction_type_id' => TransactionType::where('name', 'deposit')->first()->id,
            ])
            ->save();

        // Check the partial result
        $this->assertEquals(
            10,
            AccountMonthlySummary::calculateAccountBalanceFact($account1, $date)
        );

        // Create the third transaction: transfer from account 1 to account 2
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 30,
                    'amount_to' => 300,
                    'account_from_id' => $account1->id,
                    'account_to_id' => $account2->id,
                ]),
                'config'
            )
            ->make([
                'date' => $date,
                'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
            ])
            ->save();

        // Check the partial result
        $this->assertEquals(
            -20,
            AccountMonthlySummary::calculateAccountBalanceFact($account1, $date)
        );

        // Create the fourth transaction: transfer from account 2 to account 1
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 400,
                    'amount_to' => 40,
                    'account_from_id' => $account2->id,
                    'account_to_id' => $account1->id,
                ]),
                'config'
            )
            ->make([
                'date' => $date,
                'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
            ])
            ->save();

        // Check the partial result
        $this->assertEquals(
            20,
            AccountMonthlySummary::calculateAccountBalanceFact($account1, $date)
        );

        // Create the fifth transaction: buy investment
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account1->id,
                    'investment_id' => $investment->id,
                    'quantity' => 5,
                    'price' => 10,
                    'tax' => 10,
                    'commission' => 10,
                ]),
                'config'
            )
            ->make([
                'date' => $date,
                'transaction_type_id' => TransactionType::where('name', 'Buy')->first()->id,
                // By default this would be calculated by the ProcessTransactionCreated listener
                'cashflow_value' => -70,
            ])
            ->save();

        // Check the partial result -> investment transaction CF is -70
        $this->assertEquals(
            -50,
            AccountMonthlySummary::calculateAccountBalanceFact($account1, $date)
        );

        // Create an irrelevant transaction
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 100,
                    'amount_to' => 100,
                    'account_from_id' => $account2->id,
                    'account_to_id' => $payee->id,
                ]),
                'config'
            )
            ->make([
                'date' => $date,
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
            ])
            ->save();

        // Check the partial result - should be the same as before
        $this->assertEquals(
            -50,
            AccountMonthlySummary::calculateAccountBalanceFact($account1, $date)
        );

        // Create a transaction with a different date
        $dateNextMonth = now()->addMonth();
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 100,
                    'amount_to' => 100,
                    'account_from_id' => $account1->id,
                    'account_to_id' => $payee->id,
                ]),
                'config'
            )
            ->make([
                'date' => $dateNextMonth,
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
            ])
            ->save();

        // Check the partial result - should be the same as before
        $this->assertEquals(
            -50,
            AccountMonthlySummary::calculateAccountBalanceFact($account1, $date)
        );
    }

    /** @test */
    public function test_investment_value_is_calculated_correctly()
    {
        $user = $this->createBasicAssetsAndReturnUser();
        $account = $user->accounts->first();
        $investment1 = $user->investments->first();
        $investment2 = $user->investments->last();

        // Set a static date for the first day of the month
        $date = now()->startOfMonth();

        // Create the first transaction: buy investment 1
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account->id,
                    'investment_id' => $investment1->id,
                    'quantity' => 5,
                    'price' => 10,
                    'tax' => 10,
                    'commission' => 10,
                ]),
                'config'
            )
            ->make([
                'date' => $date,
                'transaction_type_id' => TransactionType::where('name', 'Buy')->first()->id,
            ])
            ->save();

        // Check the partial result: investment value is 50
        $this->assertEquals(
            50,
            AccountMonthlySummary::calculateInvestmentValueFact($account, $date)
        );

        // Create the second transaction: buy investment 2
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account->id,
                    'investment_id' => $investment2->id,
                    'quantity' => 10,
                    'price' => 10,
                    'tax' => 20,
                    'commission' => 20,
                ]),
                'config'
            )
            ->make([
                'date' => $date,
                'transaction_type_id' => TransactionType::where('name', 'Buy')->first()->id,
            ])
            ->save();

        // Check the partial result: investment value is +100, total of 150
        $this->assertEquals(
            150,
            AccountMonthlySummary::calculateInvestmentValueFact($account, $date)
        );

        // Create the third transaction: partially sell investment 1
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account->id,
                    'investment_id' => $investment1->id,
                    'quantity' => 2,
                    'price' => 10,
                    'tax' => 10,
                    'commission' => 10,
                ]),
                'config'
            )
            ->make([
                'date' => $date,
                'transaction_type_id' => TransactionType::where('name', 'Sell')->first()->id,
            ])
            ->save();

        // Check the partial result: investment value is -20, total of 130
        $this->assertEquals(
            130,
            AccountMonthlySummary::calculateInvestmentValueFact($account, $date)
        );

        // Create an irrelevant transaction, in next month
        $dateNextMonth = now()->addMonth();
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account->id,
                    'investment_id' => $investment1->id,
                    'quantity' => 2,
                    'price' => 10,
                    'tax' => 10,
                    'commission' => 10,
                ]),
                'config'
            )
            ->make([
                'date' => $dateNextMonth,
                'transaction_type_id' => TransactionType::where('name', 'Sell')->first()->id,
            ])
            ->save();

        // Check the partial result: investment value is -20, total remains 130
        $this->assertEquals(
            130,
            AccountMonthlySummary::calculateInvestmentValueFact($account, $date)
        );
    }
}
