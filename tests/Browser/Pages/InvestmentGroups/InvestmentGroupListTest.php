<?php

namespace Tests\Browser\Pages\InvestmentGroups;

use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

const TABLE_SELECTOR = '#table';

class InvestmentGroupListTest extends DuskTestCase
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

    public function test_user_can_load_the_investment_group_list_and_use_filters()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL)
            ->load('investmentGroups');

        // Create an investment group to search for, with unique name
        /** @var InvestmentGroup $investmentGroupToSearch */
        $investmentGroups = InvestmentGroup::factory()
            ->for($user)
            ->count(2)
            ->create();

        $investmentGroupToSearch = $investmentGroups->first();

        $this->browse(function (Browser $browser) use ($user, $investmentGroupToSearch) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the investment group list
                ->visitRoute('investment-group.index')
                // Wait for the table to load
                ->waitFor('@table-investment-groups')
                // Check that the investment group list is visible
                ->assertPresent('@table-investment-groups');

            // Get the number of investment groups in the table using JavaScript
            $this->assertEquals(
                $user->investmentGroups()->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the search field
            $browser->type('@input-table-filter-search', $investmentGroupToSearch->name);
            $this->assertEquals(
                1,
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Clear the search field
            $browser->clear('@input-table-filter-search');
            // Enter a dummy search string
            $browser->type('@input-table-filter-search', '_dummy_');
            // The number of filtered investment groups should be 0
            $this->assertEquals(
                0,
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );
        });
    }

    public function test_delete_button_behaviour()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Create investment groups for various test cases

        // Standalone investment group, which can be deleted
        $standaloneInvestmentGroup = InvestmentGroup::factory()
            ->for($user)
            ->create();

        // Investment group assigned to an investment, which cannot be deleted
        $investmentGroupWithInvestment = InvestmentGroup::factory()
            ->for($user)
            ->create();
        Investment::factory()->for($user)->for($investmentGroupWithInvestment)->create();

        // Perform the tests
        $this->browse(function (Browser $browser) use ($user, $standaloneInvestmentGroup, $investmentGroupWithInvestment) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the investment group list
                ->visitRoute('investment-group.index')
                // Wait for the table to load
                ->waitFor('@table-investment-groups')
                // Check that the investment group list is visible
                ->assertPresent('@table-investment-groups');

            // Validate that the standalone investment group can be deleted
            $browser->assertPresent(
                TABLE_SELECTOR . " button.deleteIcon[data-id='{$standaloneInvestmentGroup->id}']"
            );

            // Validate that the investment group with an investment cannot be deleted
            $browser->assertMissing(
                TABLE_SELECTOR . " button.deleteIcon[data-id='{$investmentGroupWithInvestment->id}']"
            );
            $browser->assertPresent(
                TABLE_SELECTOR . " button[data-id='{$investmentGroupWithInvestment->id}']:not(.deleteIcon)"
            );
        });
    }
}
