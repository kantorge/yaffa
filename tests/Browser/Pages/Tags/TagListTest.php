<?php

namespace Tests\Browser\Pages\Tags;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TagListTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    // Helper function to read the number of tags in a DataTable
    private function getTagCount(Browser $browser, string $tableSelector): int
    {
        return $browser->script("return $('{$tableSelector}').DataTable().rows({search:'applied'}).count()")[0];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }
    }

    public function test_user_can_load_the_tag_list_and_use_filters()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL)
            ->load('tags');

        // Get the first tag of the user.
        $tagToSearch = $user->tags()->first();

        $this->browse(function (Browser $browser) use ($user, $tagToSearch) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the tag list
                ->visitRoute('tag.index')
                // Wait for the table to load
                ->waitFor('@table-tags')
                // Check that the tag list is visible
                ->assertPresent('@table-tags');

            // Get the number of tags in the table using JavaScript
            $this->assertEquals(
                $user->tags()->count(),
                $this->getTagCount($browser, '#table')
            );

            // Filter the table using the button bar to show only inactive tags
            $browser->click('label[for=table_filter_active_no]');
            $this->assertEquals(
                $user->tags()->where('active', false)->count(),
                $this->getTagCount($browser, '#table')
            );

            // Filter the table using the button bar to show only active tags
            $browser->click('label[for=table_filter_active_yes]');
            $this->assertEquals(
                $user->tags()->where('active', true)->count(),
                $this->getTagCount($browser, '#table')
            );

            // Filter the table using the button bar to show all tags
            $browser->click('label[for=table_filter_active_any]');
            $this->assertEquals(
                $user->tags()->count(),
                $this->getTagCount($browser, '#table')
            );

            // Filter the table using the search field
            $browser->type('@input-table-filter-search', $tagToSearch->name);
            // The number of filtered tags should be 1
            $this->assertEquals(
                1,
                $this->getTagCount($browser, '#table')
            );

            // Clear the search field
            $browser->clear('@input-table-filter-search');
            // Enter a dummy search string
            $browser->type('@input-table-filter-search', 'dummy');
            // The number of filtered tags should be 0
            $this->assertEquals(0, $this->getTagCount($browser, '#table'));
        });
    }

    public function test_user_can_reach_the_new_tag_form_via_the_tag_list()
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function (Browser $browser) use ($user) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the tag list
                ->visitRoute('tag.index')
                // Click the "Add" button
                ->click('@button-new-tag')
                // Wait for the form to load
                ->waitFor('@form-tag')
                // Check that the form is visible
                ->assertPresent('@form-tag');
        });
    }

    public function test_user_can_reach_the_edit_tag_form_via_the_tag_list()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);
        // Get the firt tag of the user. We assume, that it will be visible in the list.
        $tagToEdit = $user->tags()->first();

        $this->browse(function (Browser $browser) use ($user, $tagToEdit) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the tag list
                ->visitRoute('tag.index')
                // Wait for the table to load
                ->waitFor('@table-tags');

            // Click the "Edit" button for the first tag
            $browser->with('@table-tags', function ($table) use ($tagToEdit) {
                // After save option "return to selected account" should be always visible
                $table->click('a[href="' . route('tag.edit', $tagToEdit) . '"]');
            });
            // Wait for the form to load
            $browser->waitFor('@form-tag')
                // Check that the form is visible
                ->assertPresent('@form-tag')
                // Check that the form is filled with the correct data
                ->assertInputValue('@form-tag-field-name', $tagToEdit->name);
        });
    }

    public function test_user_can_reach_the_delete_tag_form_via_the_tag_list()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Get the firt tag of the user. We assume, that it will be visible in the list.
        $tagToDelete = $user->tags()->first();

        $this->browse(function (Browser $browser) use ($user, $tagToDelete) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the tag list
                ->visitRoute('tag.index')
                // Wait for the table to load
                ->waitFor('@table-tags');

            // Click the "Delete" button for the first tag
            $browser->with('@table-tags', function ($table) use ($tagToDelete) {
                // After save option "return to selected account" should be always visible
                $table->click('button.data-delete[data-id="' . $tagToDelete->id . '"]');
            });

            // Click cancel on the confirmation dialog
            $browser->waitForDialog()
                ->dismissDialog();
            // Check that the tag is still visible in the table
            $browser->assertSeeIn('#table', $tagToDelete->name);

            $browser->waitForReload(function (Browser $browser) use ($tagToDelete) {
                // Click the "Delete" button for the first tag again
                $browser->with('@table-tags', function ($table) use ($tagToDelete) {
                    // After save option "return to selected account" should be always visible
                    $table->click('button.data-delete[data-id="' . $tagToDelete->id . '"]');
                });
                $browser->waitForDialog()
                    ->acceptDialog();
            });

            // Check that a notification is visible
            $browser->assertSeeIn('#BootstrapNotificationContainer', 'Tag deleted')
                // Check that the tag is not visible in the table anymore
                ->assertDontSeeIn('@table-tags', $tagToDelete->name);
        });
    }
}
