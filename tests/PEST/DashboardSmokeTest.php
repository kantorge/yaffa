<?php

use App\Models\User;

it('shows the dashboard to the logged-in demo user', function () {
    $this->actingAs(User::where('email', 'demo@yaffa.cc')->firstOrFail());

    visit(route('home'))
        ->assertRoute('home')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
})->group('critical');
