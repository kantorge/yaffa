<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function logoutRoute(): string
    {
        return route('logout');
    }

    protected function successfulLogoutRoute(): string
    {
        return '/';
    }

    /** @test */
    public function test_user_can_logout(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->be($user);

        $response = $this->post($this->logoutRoute());

        $response->assertRedirect($this->successfulLogoutRoute());
        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_logout_when_not_authenticated(): void
    {
        $response = $this->post($this->logoutRoute());

        $response->assertRedirect($this->successfulLogoutRoute());
        $this->assertGuest();
    }
}
