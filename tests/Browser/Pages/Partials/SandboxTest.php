<?php

namespace Tests\Browser\Pages\Partials;

use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

#[Group('extended')]
class SandboxTest extends DuskTestCase
{
    public function testResetAlertIsNotPresentIfSandboxIsDisabled(): void
    {
        // Make sure sandbox mode is disabled
        $this->setConfig('yaffa.sandbox_mode', false);

        $user = User::factory()->create([
            'language' => 'en'
        ]);

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

        $user = User::factory()->create([
            'language' => 'en'
        ]);

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
