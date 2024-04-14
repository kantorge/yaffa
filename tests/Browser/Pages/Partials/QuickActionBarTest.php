<?php

namespace Tests\Browser\Pages\Partials;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Throwable;

class QuickActionBarTest extends DuskTestCase
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

    /**
     * @throws Throwable
     */
    public function test_quick_action_bar_is_initially_hidden()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('home'))
                ->assertMissing('@quick-action-bar');
        });
    }

    /**
     * @throws Throwable
     */
    public function test_quick_action_bar_can_be_opened_and_closed()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('home'))
                ->assertMissing('@quick-action-bar')
                ->click('@quick-action-bar-toggler')
                ->waitFor('@quick-action-bar')
                ->click('@quick-action-bar-close')
                ->waitUntilMissing('@quick-action-bar');
        });
    }
}
