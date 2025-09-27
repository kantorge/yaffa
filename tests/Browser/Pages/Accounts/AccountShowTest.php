<?php

namespace Tests\Browser\Pages\Accounts;

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AccountShowTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    public function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }
    }

    public function test_account_history_uses_correct_currency_for_standard_transactions()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Get the EUR and USD cash accounts of the user, and also the Wallet (EUR) account.
        $accountEUR = $user->accounts()->where('name', 'Cash account EUR')->first();
        $accountUSD = $user->accounts()->where('name', 'Cash account USD')->first();
        $accountWallet = $user->accounts()->where('name', 'Wallet')->first();

        // Get the first payee of the user.
        $payee = $user->payees()->first();

        // Set the date for the transactions far to the future, which is not covered by the factory
        $date = now()->addYears(10);

        // Create various transactions for this user
        $baseData = [
            'date' => $date->format('Y-m-d'),
            'config_type' => 'standard',
            'comment' => null
        ];

        // We will use make and save instead of create, to avoid the afterCreating callback

        // Withdrawing 1 EUR from the EUR cash account to the payee
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 1.11,
                    'amount_to' => 1.11,
                    'account_from_id' => $accountEUR->id,
                    'account_to_id' => $payee->id,
                ]),
                'config'
            )
            ->make($baseData + [
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
            ])
            ->save();

        // Depositing 2 EUR from the payee to the EUR cash account
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 2,
                    'amount_to' => 2,
                    'account_from_id' => $payee->id,
                    'account_to_id' => $accountEUR->id,
                ]),
                'config'
            )
            ->make($baseData + [
                'transaction_type_id' => TransactionType::where('name', 'deposit')->first()->id,
            ])
            ->save();

        // Transferring 3 EUR from the Wallet to the EUR cash account
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 3,
                    'amount_to' => 3,
                    'account_from_id' => $accountWallet->id,
                    'account_to_id' => $accountEUR->id,
                ]),
                'config'
            )
            ->make($baseData + [
                'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
            ])
            ->save();

        // Transferring 4 USD from the USD cash account as 4 EUR to the EUR cash account
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 4,
                    'amount_to' => 5,
                    'account_from_id' => $accountUSD->id,
                    'account_to_id' => $accountEUR->id,
                ]),
                'config'
            )
            ->make($baseData + [
                'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
            ])
            ->save();

        // Run the tests
        $this->browse(function (Browser $browser) use ($user, $accountEUR, $date) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the account show page for the EUR cash account and pass the date range parameters
                ->visitRoute('account-entity.show', [
                    'account_entity' => $accountEUR->id,
                    'date_from' => $date->format('Y-m-d'),
                    'date_to' => $date->format('Y-m-d'),
                ])
                // Wait for the page to load, including the table content
                ->waitFor('#historyTable')
                ->waitUsing(5, 75, fn () => $this->getTableRowCount($browser, '#historyTable') === 4)
                // Verify the currency and amount in the table for each transaction
                ->assertSeeIn('#historyTable tbody', '€1.11')
                ->assertSeeIn('#historyTable tbody', '€2')
                ->assertSeeIn('#historyTable tbody', '€3')
                ->assertSeeIn('#historyTable tbody', '€5');
        });
    }

    public function test_account_history_uses_correct_currency_and_value_for_investment_transactions()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Get an account and investment of the user, using the same currency
        $account = $user->accounts()->where('name', 'Investment account USD')->first();
        $investment = $user->investments()->where('name', 'Test investment USD')->first();

        // Set the date for the transactions far to the future, which is not covered by the factory
        $date = now()->addYears(10);

        // Create various transactions for this user
        $baseData = [
            'date' => $date->format('Y-m-d'),
            'config_type' => 'investment',
            'comment' => null
        ];

        // We will use make and save instead of create, to avoid the afterCreating callback

        // Buy - cash flow value is -1150 USD
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account->id,
                    'investment_id' => $investment->id,
                    'quantity' => 10,
                    'price' => 100,
                    'commission' => 100,
                    'tax' => 50,
                    'dividend' => null,
                ]),
                'config'
            )
            ->make($baseData + [
                'transaction_type_id' => TransactionType::where('name', 'Buy')->first()->id,
                // Also store the cash flow value, which would be calculated in the real application
                'cashflow_value' => '-1150'
            ])
            ->save();

        // Add some shares - cash flow value is 0 USD
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account->id,
                    'investment_id' => $investment->id,
                    'quantity' => 5,
                    'price' => null,
                    'commission' => null,
                    'tax' => null,
                    'dividend' => null,
                ]),
                'config'
            )
            ->make($baseData + [
                'transaction_type_id' => TransactionType::where('name', 'Add shares')->first()->id,
                'cashflow_value' => '0'
            ])
            ->save();

        // Receive dividend - cash flow value is 100 USD
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account->id,
                    'investment_id' => $investment->id,
                    'quantity' => null,
                    'price' => null,
                    'commission' => null,
                    'tax' => null,
                    'dividend' => 100,
                ]),
                'config'
            )
            ->make($baseData + [
                'transaction_type_id' => TransactionType::where('name', 'Dividend')->first()->id,
                'cashflow_value' => '100'
            ])
            ->save();

        // Sell some shares - cash flow value is 300 USD
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailInvestment::factory()->create([
                    'account_id' => $account->id,
                    'investment_id' => $investment->id,
                    'quantity' => 3,
                    'price' => 150,
                    'commission' => 100,
                    'tax' => 50,
                    'dividend' => null,
                ]),
                'config'
            )
            ->make($baseData + [
                'transaction_type_id' => TransactionType::where('name', 'Sell')->first()->id,
                'cashflow_value' => '300'
            ])
            ->save();

        // Run the tests
        $this->browse(function (Browser $browser) use ($user, $account, $date) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the account show page for the investment account and pass the date range parameters
                ->visitRoute('account-entity.show', [
                    'account_entity' => $account->id,
                    'date_from' => $date->format('Y-m-d'),
                    'date_to' => $date->format('Y-m-d'),
                ])
                // Wait for the page to load, including the table content
                ->waitFor('#historyTable')
                ->waitUsing(5, 75, fn () => $this->getTableRowCount($browser, '#historyTable') === 4)
                // Verify the currency and amount in the table for each transaction
                ->assertSeeIn('#historyTable tbody', '-$1,150')
                ->assertSeeIn('#historyTable tbody', '$0')
                ->assertSeeIn('#historyTable tbody', '$100')
                ->assertSeeIn('#historyTable tbody', '$300');
        });
    }

    public function test_date_parameters_take_precedence_over_preset_settings() {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // For this test, make sure the user has a non-default date setting
        $user->account_details_date_range = 'previous30Days';
        $user->save();

        // Get an account and a payee of the user
        $account = $user->accounts()->where('name', 'Wallet')->first();
        $payee = $user->payees()->first();

        // Set the date range for the account, too
        $account->config->default_date_range = 'previous90Days';
        $account->config->save();

        // Set the date for the transactions to the future, out of the default ranges
        $date = now()->addYears(1);

        // Create a transaction for this user
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 1.11,
                    'amount_to' => 1.11,
                    'account_from_id' => $account->id,
                    'account_to_id' => $payee->id,
                ]),
                'config'
            )
            ->make([
                'date' => $date->format('Y-m-d'),
                'config_type' => 'standard',
                'comment' => null,
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
            ])
            ->save();

        // Run the test, opening the account page with date parameters that include the transaction, and verifying the transaction is shown
        $this->browse(function (Browser $browser) use ($user, $account, $date) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the account show page for the Wallet account and pass the date range parameters that include the transaction
                ->visitRoute('account-entity.show', [
                    'account_entity' => $account->id,
                    'date_from' => $date->copy()->subDays(1)->format('Y-m-d'),
                    'date_to' => $date->copy()->addDays(1)->format('Y-m-d'),
                ])
                // Wait for the page to load, including the table content
                ->waitFor('#historyTable')
                ->waitUsing(5, 75, fn () => $this->getTableRowCount($browser, '#historyTable') === 1)
                // Verify the transaction is shown
                ->assertSeeIn('#historyTable tbody', '€1.11')
                // Additionally, verify that the date range selector shows the default "Select preset" option, as we used explicit date parameters
                ->assertSeeIn('#dateRangePickerPresets', 'Select preset');
        });
    }

    public function test_preset_parameter_takes_precedence_over_account_and_user_setting() {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // For this test, make sure the user has a non-default date setting
        $user->account_details_date_range = 'previous30Days';
        $user->save();

        // Get an account and a payee of the user
        $account = $user->accounts()->where('name', 'Wallet')->first();

        // Set the date range for the account, too, to a different value than the user's setting
        $account->config->default_date_range = 'previous90Days';
        $account->config->save();

        // Run the test, opening the account page with a preset parameter that takes precedence over both the account and user settings
        $this->browse(function (Browser $browser) use ($user, $account) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the account show page for the Wallet account with a preset parameter that takes precedence over both the account and user settings
                ->visitRoute('account-entity.show', [
                    'account_entity' => $account->id,
                    'preset' => 'previous7Days',
                ])
                // Wait for the page to load, including the table content
                ->waitFor('#historyTable')
                // Additionally, verify that the date range selector shows the preset option we used in the URL parameters
                ->assertSeeIn('#dateRangePickerPresets', 'Previous 7 days');
        });
    }

    public function test_account_date_preset_setting_takes_precedence_over_user_setting() {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // For this test, make sure the user has a non-default date setting
        $user->account_details_date_range = 'previous30Days';
        $user->save();

        // Get an account and a payee of the user
        $account = $user->accounts()->where('name', 'Wallet')->first();

        // Set the date range for the account, too, to a different value than the user's setting
        $account->config->default_date_range = 'previous90Days';
        $account->config->save();

        // Run the test, opening the account page without date parameters, and verifying the transaction is shown as per the account's preset setting
        $this->browse(function (Browser $browser) use ($user, $account) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the account show page for the Wallet account without any date range parameters
                ->visitRoute('account-entity.show', [
                    'account_entity' => $account->id,
                ])
                // Wait for the page to load, including the table content
                ->waitFor('#historyTable')
                // Additionally, verify that the date range selector shows the account's preset option, as we used no explicit date parameters
                ->assertSeeIn('#dateRangePickerPresets', 'Previous 90 days');
        });
    }

    public function test_user_date_setting_is_used_if_no_other_date_setting_exists() {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // For this test, make sure the user has a non-default date setting
        $user->account_details_date_range = 'previous30Days';
        $user->save();

        // Get an account and a payee of the user
        $account = $user->accounts()->where('name', 'Wallet')->first();

        // Make sure the account has no date range setting
        $account->config->default_date_range = null;
        $account->config->save();

        // Run the test, opening the account page without date parameters, and verifying the transaction is shown as per the user's preset setting
        $this->browse(function (Browser $browser) use ($user, $account) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the account show page for the Wallet account without any date range parameters
                ->visitRoute('account-entity.show', [
                    'account_entity' => $account->id,
                ])
                // Wait for the page to load, including the table content
                ->waitFor('#historyTable')
                // Additionally, verify that the date range selector shows the user's preset option, as we used no explicit date parameters and the account has no setting
                ->assertSeeIn('#dateRangePickerPresets', 'Previous 30 days');
        });
    }
}
