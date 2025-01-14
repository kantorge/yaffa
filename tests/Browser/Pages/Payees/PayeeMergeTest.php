<?php

namespace Tests\Browser\Pages\Payees;



use App\Models\User;
use Tests\DuskTestCase;

class PayeeMergeTest extends DuskTestCase
{
    public function test_user_can_load_the_merge_payee_form()
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        $this->browse(function ($browser) use ($user) {
            $browser
                ->loginAs($user)
                ->visitRoute('payees.merge.form')
                ->assertSee('Merge payees');
        });
    }
}
