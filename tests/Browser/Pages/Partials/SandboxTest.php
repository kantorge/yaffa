<?php

namespace Tests\Browser\Pages\Partials;

use App\Models\User;
use Tests\DuskTestCase;

class SandboxTest extends DuskTestCase
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

    public function testResetAlertIsNotPresentIfSandboxIsDisabled(): void
    {
        // Make sure sandbox mode is disabled
        $this->setConfig('yaffa.sandbox_mode', false);

        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function ($browser) use ($user) {
            // Log in using the generic test user
            $browser->loginAs($user)
                ->visit(route('home'));

            // Make sure the element is not present
            $browser->assertMissing('#sandBoxResetAlert');

            // Finally, log out by submitting the logout form
            $browser->logout();
        });
    }

    public function testResetAlertIsPresentIfSandboxIsEnabled(): void
    {
        // Make sure sandbox mode is enabled
        $this->setConfig('yaffa.sandbox_mode', true);

        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function ($browser) use ($user) {
            // Log in using the generic test user
            $browser->loginAs($user)
                ->visit(route('home'));

            // Make sure the element is present
            $browser->assertVisible('#sandBoxResetAlert');

            // Finally, log out by submitting the logout form
            $browser->logout();
        });
    }
}
