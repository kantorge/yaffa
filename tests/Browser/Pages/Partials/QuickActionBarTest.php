<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Throwable;

uses(Tests\DuskTestCase::class);

const QUICK_ACTION_BAR_SELECTOR = '@quick-action-bar';
beforeEach(function () {
    // Migrate and seed only once for this file
    if (!static::$migrationRun) {
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
        static::$migrationRun = true;
    }
});


/**
 * @throws Throwable
 */
test('quick action bar is initially hidden', function () {
    // Load the main test user
    $user = User::firstWhere('email', $this::USER_EMAIL);

    $this->actingAs($user);

        $browser = visit(route('home'))
        ->assertMissing(QUICK_ACTION_BAR_SELECTOR . ':not(.hidden)');;
});

/**
 * @throws Throwable
 */
test('quick action bar can be opened and closed', function () {
    // Load the main test user
    $user = User::firstWhere('email', $this::USER_EMAIL);

    $this->actingAs($user);

        $browser = visit(route('home'))
        ->assertMissing(QUICK_ACTION_BAR_SELECTOR . ':not(.hidden)')
        ->click('@quick-action-bar-toggler')
        ->waitFor(QUICK_ACTION_BAR_SELECTOR)
        ->click('@quick-action-bar-close')
        ->waitUntilMissing(QUICK_ACTION_BAR_SELECTOR . ':not(.hidden)');;
});
