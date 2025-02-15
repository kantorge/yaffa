<?php

namespace Tests\Browser\Pages\Categories;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

const TABLE_SELECTOR = '#table';

class CategoryListTest extends DuskTestCase
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

    public function test_user_can_load_the_category_list_and_use_filters()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL)
            ->load('categories');

        // Create a category to search for, with unique name, parent and active status
        /** @var Category $categoryToSearch */
        $categoryToSearch = Category::factory()
            ->for($user)
            ->create([
                'parent_id' => null,
                'active' => false,
            ]);

        $this->browse(function (Browser $browser) use ($user, $categoryToSearch) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the category list
                ->visitRoute('categories.index')
                // Wait for the table to load
                ->waitFor('@table-categories')
                // Check that the category list is visible
                ->assertPresent('@table-categories');

            // Get the number of categories in the table using JavaScript
            $this->assertEquals(
                $user->categories()->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the button bar to show only inactive categories
            $browser->click('label[for=table_filter_active_no]');
            $this->assertEquals(
                $user->categories()->where('active', false)->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the button bar to show only active categories
            $browser->click('label[for=table_filter_active_yes]');
            $this->assertEquals(
                $user->categories()->where('active', true)->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the button bar to show all categories
            $browser->click('label[for=table_filter_active_any]');
            $this->assertEquals(
                $user->categories()->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the button bar to show only root categories
            $browser->click('label[for=table_filter_category_level_parent]');
            $this->assertEquals(
                $user->categories()->whereNull('parent_id')->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the button bar to show only sub categories
            $browser->click('label[for=table_filter_category_level_child]');
            $this->assertEquals(
                $user->categories()->whereNotNull('parent_id')->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the button bar to show all categories
            $browser->click('label[for=table_filter_category_level_any]');
            $this->assertEquals(
                $user->categories()->count(),
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Filter the table using the search field
            $browser->type('@input-table-filter-search', $categoryToSearch->name);
            $this->assertEquals(
                1,
                $this->getTableRowCount($browser, TABLE_SELECTOR)
            );

            // Clear the search field
            $browser->clear('@input-table-filter-search');
            // Enter a dummy search string
            $browser->type('@input-table-filter-search', 'dummy');
            // The number of filtered categories should be 0
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

        // Create categories for various test cases

        // Standalone category, which can be deleted
        $standaloneParentCategory = Category::factory()
            ->for($user)
            ->create([
                'parent_id' => null,
            ]);

        // Parent category with a child category, which cannot be deleted
        /** @var Category $parentWithChildCategory */
        $parentWithChildCategory = Category::factory()
            ->for($user)
            ->create([
                'parent_id' => null,
            ]);

        Category::factory()
            ->for($user)
            ->create([
                'parent_id' => $parentWithChildCategory->id,
            ]);

        // Parent category assigned to a payee, which cannot be deleted
        $payeeDefaultCategory = Category::factory()
            ->for($user)
            ->create([
                'parent_id' => null,
            ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user)->create(['category_id' => $payeeDefaultCategory->id]), 'config')
            ->create();

        // Parent category which is the preferred category of a payee, which cannot be deleted
        /** @var Category $payeePreferredCategory */
        $payeePreferredCategory = Category::factory()
            ->for($user)
            ->create([
                'parent_id' => null,
            ]);

        /** @var AccountEntity $payeeWithPreferredCategory */
        $payeeWithPreferredCategory = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user)->create(['category_id' => null]), 'config')
            ->create();

        $payeeWithPreferredCategory
            ->categoryPreference()
            ->attach($payeePreferredCategory->id, ['preferred' => true]);

        // Parent category which is the deferred category of a payee, which cannot be deleted
        /** @var Category $payeeDeferredCategory */
        $payeeDeferredCategory = Category::factory()
            ->for($user)
            ->create([
                'parent_id' => null,
            ]);

        /** @var AccountEntity $payeeWithDeferredCategory */
        $payeeWithDeferredCategory = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user)->create(['category_id' => null]), 'config')
            ->create();

        $payeeWithDeferredCategory
            ->categoryPreference()
            ->attach($payeeDeferredCategory->id, ['preferred' => false]);

        // Perform the tests
        $this->browse(function (Browser $browser) use ($user, $standaloneParentCategory, $parentWithChildCategory, $payeeDefaultCategory, $payeePreferredCategory, $payeeDeferredCategory) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the category list
                ->visitRoute('categories.index')
                // Wait for the table to load
                ->waitFor('@table-categories')
                // Check that the category list is visible
                ->assertPresent('@table-categories');

            // Validate the delete button is enabled for a standalone parent category
            $browser->assertPresent(
                TABLE_SELECTOR . " button.deleteIcon[data-id='{$standaloneParentCategory->id}']"
            );

            // Validate the delete button is disabled for a parent category with a child category
            $browser->assertMissing(
                TABLE_SELECTOR . " button.deleteIcon[data-id='{$parentWithChildCategory->id}']"
            );
            $browser->assertPresent(
                TABLE_SELECTOR . " button[data-id='{$parentWithChildCategory->id}']:not(.deleteIcon)"
            );

            // Validate the delete button is disabled for a category assigned to a payee as default category
            $browser->assertMissing(
                TABLE_SELECTOR . " button.deleteIcon[data-id='{$payeeDefaultCategory->id}']"
            );
            $browser->assertPresent(
                TABLE_SELECTOR . " button[data-id='{$payeeDefaultCategory->id}']:not(.deleteIcon)"
            );

            // Validate the delete button is disabled for a category which is the preferred category of a payee
            $browser->assertMissing(
                TABLE_SELECTOR . " button.deleteIcon[data-id='{$payeePreferredCategory->id}']"
            );
            $browser->assertPresent(
                TABLE_SELECTOR . " button[data-id='{$payeePreferredCategory->id}']:not(.deleteIcon)"
            );

            // Validate the delete button is disabled for a category which is the deferred category of a payee
            $browser->assertMissing(
                TABLE_SELECTOR . " button.deleteIcon[data-id='{$payeeDeferredCategory->id}']"
            );
            $browser->assertPresent(
                TABLE_SELECTOR . " button[data-id='{$payeeDeferredCategory->id}']:not(.deleteIcon)"
            );
        });
    }
}
