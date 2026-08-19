<?php

namespace Tests\Browser\Pages\Auth;

use App\Models\User;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

#[Group('extended')]
class LoginTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate only once for this file - these tests only need factory-created
        // users, not the seeded demo data, so db:seed is skipped.
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            static::$migrationRun = true;
        }
    }

    public function test_login_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertSee('YAFFA');
        });
    }

    public function test_user_login_redirects_to_main_page(): void
    {
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
    }
}
