<?php

namespace Tests\Browser\Pages\Partials;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Throwable;

const QUICK_ACTION_BAR_SELECTOR = '@quick-action-bar';

class QuickActionBarTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

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

    /**
     * @throws Throwable
     */
    public function test_quick_action_bar_is_initially_hidden(): void
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('home'))
                ->assertMissing(QUICK_ACTION_BAR_SELECTOR . ':not(.hidden)');
        });
    }

    /**
     * @throws Throwable
     */
    public function test_quick_action_bar_can_be_opened_and_closed(): void
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('home'))
                ->assertMissing(QUICK_ACTION_BAR_SELECTOR . ':not(.hidden)')
                ->click('@quick-action-bar-toggler')
                ->waitFor(QUICK_ACTION_BAR_SELECTOR)
                ->click('@quick-action-bar-close')
                ->waitUntilMissing(QUICK_ACTION_BAR_SELECTOR . ':not(.hidden)');
        });
    }
}
