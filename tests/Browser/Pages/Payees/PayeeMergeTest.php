<?php

use App\Models\User;
use Tests\DuskTestCase;

uses(Tests\DuskTestCase::class);

test('user can load the merge payee form', function () {
    // Load the main test user
    $user = User::firstWhere('email', $this::USER_EMAIL);

    $this->actingAs($user);

        $browser = visit(route('payees.merge.form'))
        ->assertSee('Merge payees');;
});
