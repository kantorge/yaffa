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
        // Make sure the GTM ID is set in the .env file
        $this->setConfig('yaffa.gtm_container_id', 'GTM-XXXXXXX');

        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function ($browser) {
            // Open the login page
            $browser->visit(route('login'))
                // Wait for the page to load
                ->waitFor('#login');

            // Make sure the dataLayer is not empty
            $output = $browser->script('return window.dataLayer;');

            $this->assertNotEmpty($output[0]);

            // Log in using the generic test user by continuing with the current browser session
            $browser
                ->type('email', $this::USER_EMAIL)
                ->type('password', 'demo')
                ->press('Login')
                // Wait for the page to load
                ->waitFor('footer');

            // Make sure the dataLayer exists and the loginSuccess event is present
            $output = $browser->script(' 
                const events = window.dataLayer.filter(function(item) {
                    return item.event === "loginSuccess";
                });
                
                return events.length;');

            $this->assertEquals(1, $output[0]);
        });
    }
}
