<?php

namespace Tests\Browser\Pages\Partials;

use App\Models\User;
use Tests\DuskTestCase;

class DataLayerTest extends DuskTestCase
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

    public function testDataLayerIsNotPresentIfGtmIdIsNotSet()
    {
        // Make sure, that the GTM ID is not set in the .env file
        $this->setConfig('yaffa.gtm_container_id', null);

        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function ($browser) use ($user) {
            // Open the login page
            $browser->visit(route('login'));

            // Make sure the dataLayer is empty
            $output = $browser->script('return window.dataLayer;');
            $this->assertEmpty($output[0]);

            // Log in using the generic test user
            $browser->loginAs($user)
                ->visit(route('home'));

            // Make sure the dataLayer is still empty
            $output = $browser->script('return window.dataLayer;');
            $this->assertEmpty($output[0]);

            // Finally, log out by submitting the logout form
            $browser->logout();
        });
    }

    public function testDataLayerIsPresentIfGtmIdIsSet()
    {
        // Make sure the GTM ID is set in the .env file, and sandbox mode is enabled
        $originalGtmId = $this->getConfig('yaffa.gtm_container_id');
        $originalSandboxMode = $this->getConfig('yaffa.sandbox_mode');

        $this->setConfig('yaffa.gtm_container_id', 'GTM-XXXXXXX');
        $this->setConfig('yaffa.sandbox_mode', true);

        // Create a new, verified user
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'language' => 'en',
        ]);

        $this->browse(function ($browser) use ($user) {
            // Open the login page
            $browser->visit(route('login'))
                // Wait for the page to load
                ->waitForLocation('/login', 10)
                ->waitFor('#login', 10);

            // Make sure the dataLayer is not empty
            $output = $browser->script('return window.dataLayer;');

            $this->assertNotEmpty($output[0]);

            // Log in using the generic test user by continuing with the current browser session
            $browser
                ->type('email', $this::USER_EMAIL)
                ->type('password', 'demo')
                ->press('@login-button')
                // Wait for the page to load
                ->waitForLocation('/', 10)
                ->waitFor('footer', 10);

            // Make sure the dataLayer exists and the loginSuccess event is present, with the correct demo user flag
            $output = $browser->script('
                const events = window.dataLayer.filter(function(item) {
                    return item.event === "loginSuccess" && item.is_generic_demo_user === true;
                });

                return events.length;');

            $this->assertEquals(1, $output[0]);

            // Finally, log out by submitting the logout form
            $browser->logout();

            // Log in using the newly created user
            $browser->visit(route('login'))
                // Wait for the page to load
                ->waitFor('#login')
                ->type('email', $user->email)
                ->type('password', 'password')
                ->press('@login-button')
                // Wait for the page to load
                ->waitForLocation('/', 10)
                ->waitFor('footer', 10);

            // Make sure the dataLayer exists and the loginSuccess event is present, with the correct demo user flag
            $output = $browser->script('
                const events = window.dataLayer.filter(function(item) {
                    return item.event === "loginSuccess" && item.is_generic_demo_user === false;
                });

                return events.length;');

            $this->assertEquals(1, $output[0]);

            // Finally, log out by submitting the logout form
            $browser->logout();
        });

        // Reset the original GTM ID and sandbox mode
        $this->setConfig('yaffa.gtm_container_id', $originalGtmId);
        $this->setConfig('yaffa.sandbox_mode', $originalSandboxMode);
    }

    public function testDataLayerIsPresentIfLoginFailed()
    {
        // Make sure the GTM ID is set in the .env file, and sandbox mode is enabled
        $originalGtmId = $this->getConfig('yaffa.gtm_container_id');
        $originalSandboxMode = $this->getConfig('yaffa.sandbox_mode');

        $this->setConfig('yaffa.gtm_container_id', 'GTM-XXXXXXX');
        $this->setConfig('yaffa.sandbox_mode', true);

        $this->browse(function ($browser) {
            // Open the login page
            $browser->visit(route('login'))
                // Wait for the page to load
                ->waitForLocation('/login', 10)
                ->waitFor('#login', 10)
                // Type in an invalid email address
                ->type('email', 'thisisnotauser@example.com')
                ->type('password', 'password')
                ->press('@login-button')
                // Wait for the page to reload
                ->waitForLocation('/login', 10)
                ->waitFor('#login', 10);

            // Make sure the dataLayer exists and the loginFailed event is present, with the correct demo user flag
            $output = $browser->script('
                const events = window.dataLayer.filter(function(item) {
                    return item.event === "loginFailed" && item.is_generic_demo_user === false;
                });

                return events.length;');

            $this->assertEquals(1, $output[0]);

            // Type in the demo user email address
            $browser->type('email', $this::USER_EMAIL)
                ->type('password', 'incorrect_password')
                ->press('@login-button')
                // Wait for the page to reload
                ->waitForLocation('/login', 10)
                ->waitFor('#login', 10);

            // Make sure the dataLayer exists and the loginFailed event is present, with the correct demo user flag
            $output = $browser->script('
                const events = window.dataLayer.filter(function(item) {
                    return item.event === "loginFailed" && item.is_generic_demo_user === true;
                });

                return events.length;');

            $this->assertEquals(1, $output[0]);
        });

        // Reset the original GTM ID and sandbox mode
        $this->setConfig('yaffa.gtm_container_id', $originalGtmId);
        $this->setConfig('yaffa.sandbox_mode', $originalSandboxMode);
    }
}
