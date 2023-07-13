<?php

namespace Tests\Browser\Pages\Categories;

use App\Models\Category;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

const TABLESELECTOR = '#table';

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
        $user = User::firstWhere('email', 'demo@yaffa.cc')
            ->load('categories');

        // Create a category to search for, with unique name, parent and active status
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
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only inactive categories
            $browser->click('label[for=table_filter_active_no]');
            $this->assertEquals(
                $user->categories()->where('active', false)->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only active categories
            $browser->click('label[for=table_filter_active_yes]');
            $this->assertEquals(
                $user->categories()->where('active', true)->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show all categories
            $browser->click('label[for=table_filter_active_any]');
            $this->assertEquals(
                $user->categories()->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only root categories
            $browser->click('label[for=table_filter_category_level_parent]');
            $this->assertEquals(
                $user->categories()->whereNull('parent_id')->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only sub categories
            $browser->click('label[for=table_filter_category_level_child]');
            $this->assertEquals(
                $user->categories()->whereNotNull('parent_id')->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show all categories
            $browser->click('label[for=table_filter_category_level_any]');
            $this->assertEquals(
                $user->categories()->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the search field
            $browser->type('@input-table-filter-search', $categoryToSearch->name);
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
