<?php

namespace Tests\Browser\Pages\Accounts;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

const TABLE_SELECTOR = '#table';

#[Group('extended')]
class AccountListTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_can_load_the_account_list_and_use_filters(): void
    {
        // Create a user with accounts, which also needs one currency and one accoun group
        /** @var User $user */
        $user = User::factory()->create();
        Currency::factory()->for($user)->create(['base' => true]);
        AccountGroup::factory()->for($user)->create();

        // Create 5 active accounts and 3 inactive accounts for the user
        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user)->create(), 'config')
            ->count(5)
            ->create([
                'active' => true,
            ]);
        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user)->create(), 'config')
            ->count(3)
            ->create([
                'active' => false,
            ]);

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
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the button bar to show only inactive accounts
            $browser->click('label[for=table_filter_active_no]');
            $this->assertEquals(
                $user->accounts()->where('active', false)->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the button bar to show only active accounts
            $browser->click('label[for=table_filter_active_yes]');
            $this->assertEquals(
                $user->accounts()->where('active', true)->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the button bar to show all accounts
            $browser->click('label[for=table_filter_active_any]');
            $this->assertEquals(
                $user->accounts()->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the search field
            $browser->type('@input-table-filter-search', $accountToSearch->name);
            // The number of filtered accounts should be 1
            $this->assertEquals(
                1,
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Clear the search field
            $browser->clear('@input-table-filter-search');
            // Enter a dummy search string
            $browser->type('@input-table-filter-search', 'dummy');
            // The number of filtered tags should be 0
            $this->assertEquals(
                0,
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );
        });
    }
}
