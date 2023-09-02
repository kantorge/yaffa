<?php

namespace Tests\Browser\Pages\Accounts;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

const TABLESELECTOR = '#table';
class AccountListTest extends DuskTestCase
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

    public function test_user_can_load_the_account_list_and_use_filters()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL)
            ->load('accounts');

        // Get the first account of the user.
        $accountToSearch = $user->accounts()->first();

        $this->browse(function (Browser $browser) use ($user, $accountToSearch) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the account list
                ->visitRoute('account-entity.index', ['type' => 'account'])
                // Wait for the table to load
                ->waitFor('@table-accounts')
                // Check that the account list is visible
                ->assertPresent('@table-accounts');

            // Get the number of accounts in the table using JavaScript
            $this->assertEquals(
                $user->accounts()->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only inactive accounts
            $browser->click('label[for=table_filter_active_no]');
            $this->assertEquals(
                $user->accounts()->where('active', false)->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only active accounts
            $browser->click('label[for=table_filter_active_yes]');
            $this->assertEquals(
                $user->accounts()->where('active', true)->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show all accounts
            $browser->click('label[for=table_filter_active_any]');
            $this->assertEquals(
                $user->accounts()->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the search field
            $browser->type('@input-table-filter-search', $accountToSearch->name);
            // The number of filtered accounts should be 1
            $this->assertEquals(
                1,
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Clear the search field
            $browser->clear('@input-table-filter-search');
            // Enter a dummy search string
            $browser->type('@input-table-filter-search', 'dummy');
            // The number of filtered tags should be 0
            $this->assertEquals(
                0,
                $this->getTableRowCount($browser, TABLESELECTOR)
            );
        });
    }
}
