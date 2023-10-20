<?php

namespace Tests\Browser\Tests\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_login_page_loads()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertSee('YAFFA');
        });
    }

    public function test_user_login_redirects_to_main_page()
    {
        $user = User::factory()->create([
            'language' => 'en',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('email', $user->email)
                ->type('password', 'password')
                ->press('@login-button')
                ->assertPathIs('/');
        });
    }
}
