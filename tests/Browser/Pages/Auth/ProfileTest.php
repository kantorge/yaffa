<?php

namespace Tests\Browser\Pages\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProfileTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_password_change_client_side_validations()
    {
         // Make sure the GTM ID is Not set in the .env file, and sandbox mode is disabled
         $originalGtmId = $this->getConfig('yaffa.gtm_container_id');
         $originalSandboxMode = $this->getConfig('yaffa.sandbox_mode');

         $this->setConfig('yaffa.gtm_container_id', '');
         $this->setConfig('yaffa.sandbox_mode', false);

        /** @var User $user */
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/user/settings')
                ->waitFor('#changePasswordForm')
                // Validate that no errors are shown initially in the change password form
                ->assertMissing('#changePasswordForm div.invalid-feedback')
                // Type incorrect current password
                ->type('current_password', 'incorrect')
                // Type new password that is too short
                ->type('password', 'short')
                // Type new password that does not match the confirmation
                ->type('password_confirmation', 'doesnotmatch')
                // Submit the form and wait for the validation errors, including the toast message
                ->press('@button-change-password')
                ->waitForTextIn('div.toast-container div.toast.bg-danger.show', 'Validation failed. Please check the form for errors.' )
                ->waitForTextIn('#current_password + div.invalid-feedback', 'The password is incorrect.')
                ->waitForTextIn('#password + div.invalid-feedback', 'The password must be at least 8 characters.')
                // Set the correct password, a valid new password with a non-matching confirmation
                ->waitUntilMissing('div.toast-container div.toast.bg-danger.show')
                ->type('current_password', 'password')
                ->type('password', 'password123')
                ->type('password_confirmation', 'password1234')
                // Submit the form and wait for the validation errors, including the toast message
                ->press('@button-change-password')
                ->waitForTextIn('div.toast-container div.toast.bg-danger.show', 'Validation failed. Please check the form for errors.' )
                ->waitForTextIn('#password + div.invalid-feedback', 'The password confirmation does not match.')
                // Set the correct password, a valid new password with a matching confirmation
                ->waitUntilMissing('div.toast-container div.toast.bg-danger.show')
                ->type('current_password', 'password')
                ->type('password', 'password123')
                ->type('password_confirmation', 'password123')
                // Submit the form and wait for the success toast message
                ->press('@button-change-password')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'Password changed successfully.')
                // The form should be reset
                ->assertInputValue('current_password', '')
                ->assertInputValue('password', '')
                ->assertInputValue('password_confirmation', '');

            // Now log out and log back in with the new password
            $browser
                ->logout()
                ->visitRoute('home')
                // Page should redirect to login
                ->type('email', $user->email)
                ->type('password', 'password123')
                ->press('@login-button')
                ->assertRouteIs('home');
        });

        // Reset the GTM ID and sandbox mode
        $this->setConfig('yaffa.gtm_container_id', $originalGtmId);
        $this->setConfig('yaffa.sandbox_mode', $originalSandboxMode);
    }
}
