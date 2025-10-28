<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(Tests\DuskTestCase::class);
uses(DatabaseMigrations::class);

test('login page loads', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
            ->assertSee('YAFFA');
    });
});

test('user login redirects to main page', function () {
    $user = User::factory()->create([
        'language' => 'en'
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('@login-button')
            ->waitForLocation('/', 10);
    });
});
