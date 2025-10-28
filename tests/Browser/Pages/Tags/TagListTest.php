<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(Tests\DuskTestCase::class);
beforeEach(function () {
    // Migrate and seed only once for this file
    if (!static::$migrationRun) {
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
        static::$migrationRun = true;
    }
});


test('user can load the tag list and use filters', function () {
    // Load the main test user
    $user = User::firstWhere('email', $this::USER_EMAIL)
        ->load('tags');

    // Get the first tag of the user.
    $tagToSearch = $user->tags()->first();

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
        getTagCount($browser, '#table')
    );

    // Filter the table using the button bar to show only inactive tags
    $browser->click('label[for=table_filter_active_no]');
    $this->assertEquals(
        $user->tags()->where('active', false)->count(),
        getTagCount($browser, '#table')
    );

    // Filter the table using the button bar to show only active tags
    $browser->click('label[for=table_filter_active_yes]');
    $this->assertEquals(
        $user->tags()->where('active', true)->count(),
        getTagCount($browser, '#table')
    );

    // Filter the table using the button bar to show all tags
    $browser->click('label[for=table_filter_active_any]');
    $this->assertEquals(
        $user->tags()->count(),
        getTagCount($browser, '#table')
    );

    // Filter the table using the search field
    $browser->type('@input-table-filter-search', $tagToSearch->name);
    // The number of filtered tags should be 1
    $this->assertEquals(
        1,
        getTagCount($browser, '#table')
    );

    // Clear the search field
    $browser->clear('@input-table-filter-search');
    // Enter a dummy search string
    $browser->type('@input-table-filter-search', 'dummy');
    // The number of filtered tags should be 0
    $this->assertEquals(0, getTagCount($browser, '#table'));;
});

test('user can reach the new tag form via the tag list', function () {
    $user = User::firstWhere('email', $this::USER_EMAIL);

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
        ->assertPresent('@form-tag');;
});

test('user can reach the edit tag form via the tag list', function () {
    // Load the main test user
    $user = User::firstWhere('email', $this::USER_EMAIL);
    // Get the firt tag of the user. We assume, that it will be visible in the list.
    $tagToEdit = $user->tags()->first();

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
        ->assertInputValue('@form-tag-field-name', $tagToEdit->name);;
});

test('user can reach the delete tag form via the tag list', function () {
    // Load the main test user
    $user = User::firstWhere('email', $this::USER_EMAIL);

    // Get the firt tag of the user. We assume, that it will be visible in the list.
    $tagToDelete = $user->tags()->first();

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
        ->assertDontSeeIn('@table-tags', $tagToDelete->name);;
});

// Helpers
function getTagCount(Browser $browser, string $tableSelector): int
{
    return $browser->script("return $('{$tableSelector}').DataTable().rows({search:'applied'}).count()")[0];
}
