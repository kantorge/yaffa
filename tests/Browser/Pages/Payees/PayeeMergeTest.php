<?php

use App\Models\User;
use Tests\DuskTestCase;

uses(Tests\DuskTestCase::class);

test('user can load the merge payee form', function () {
    // Load the main test user
    $user = User::firstWhere('email', $this::USER_EMAIL);

    $this->browse(function ($browser) use ($user) {
        $browser
            ->loginAs($user)
            ->visitRoute('payees.merge.form')
            ->assertSee('Merge payees');
    });
});
