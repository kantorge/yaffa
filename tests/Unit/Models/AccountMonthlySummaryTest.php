<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\AccountGroup;
use App\Models\AccountMonthlySummary;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountMonthlySummaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * calculateAccountBalanceFact() and calculateInvestmentValueFact() both return
     * BigDecimal (FR-7); compare the exact value rather than relying on PHP's loose float
     * equality.
     *
     * Builds the expected string via BigDecimal rather than number_format(): at
     * calculateInvestmentValueFact()'s scale of 14 (quantity's scale 4 + price's scale 10,
     * added by BigDecimal multiplication), number_format() itself round-trips through a
     * double and can render an exact value like 50 as "50.00000000000001" - reintroducing
     * the float-precision bug class this whole assertion exists to catch.
     */
    private function assertBalanceFactEquals(float $expected, BigDecimal $actual): void
    {
        $this->assertSame(
            (string) BigDecimal::of((string) $expected)->toScale($actual->getScale()),
            (string) $actual
        );
    }

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

        AccountEntity::factory()->asPayee($user)->create();

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

    public function test_standard_fact_is_calculated_correctly(): void
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
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,

            ])
            ->save();

        // Check the partial result
        $this->assertBalanceFactEquals(-10, AccountMonthlySummary::calculateAccountBalanceFact($account1, $date));

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
                'transaction_type' => TransactionTypeEnum::DEPOSIT->value,
            ])
            ->save();

        // Check the partial result
        $this->assertBalanceFactEquals(10, AccountMonthlySummary::calculateAccountBalanceFact($account1, $date));

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
                'transaction_type' => TransactionTypeEnum::TRANSFER->value,
            ])
            ->save();

        // Check the partial result
        $this->assertBalanceFactEquals(-20, AccountMonthlySummary::calculateAccountBalanceFact($account1, $date));

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
                'transaction_type' => TransactionTypeEnum::TRANSFER->value,
            ])
            ->save();

        // Check the partial result
        $this->assertBalanceFactEquals(20, AccountMonthlySummary::calculateAccountBalanceFact($account1, $date));

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
                'transaction_type' => TransactionTypeEnum::BUY->value,
                // By default this would be calculated by the ProcessTransactionCreated listener
                'cashflow_value' => -70,
            ])
            ->save();

        // Check the partial result -> investment transaction CF is -70
        $this->assertBalanceFactEquals(-50, AccountMonthlySummary::calculateAccountBalanceFact($account1, $date));

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
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            ])
            ->save();

        // Check the partial result - should be the same as before
        $this->assertBalanceFactEquals(-50, AccountMonthlySummary::calculateAccountBalanceFact($account1, $date));

        // Create a transaction with a different date
        $dateNextMonth = now()->addMonthNoOverflow();
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
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            ])
            ->save();

        // Check the partial result - should be the same as before
        $this->assertBalanceFactEquals(-50, AccountMonthlySummary::calculateAccountBalanceFact($account1, $date));
    }

    public function test_investment_value_is_calculated_correctly(): void
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
                'transaction_type' => TransactionTypeEnum::BUY->value,
            ])
            ->save();

        // Check the partial result: investment value is 50
        $this->assertBalanceFactEquals(
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
                'transaction_type' => TransactionTypeEnum::BUY->value,
            ])
            ->save();

        // Check the partial result: investment value is +100, total of 150
        $this->assertBalanceFactEquals(
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
                'transaction_type' => TransactionTypeEnum::SELL->value,
            ])
            ->save();

        // Check the partial result: investment value is -20, total of 130
        $this->assertBalanceFactEquals(
            130,
            AccountMonthlySummary::calculateInvestmentValueFact($account, $date)
        );

        // Create an irrelevant transaction, in next month
        $dateNextMonth = now()->addMonthNoOverflow();
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
                'transaction_type' => TransactionTypeEnum::SELL->value,
            ])
            ->save();

        // Check the partial result: investment value is -20, total remains 130
        $this->assertBalanceFactEquals(
            130,
            AccountMonthlySummary::calculateInvestmentValueFact($account, $date)
        );
    }
}
