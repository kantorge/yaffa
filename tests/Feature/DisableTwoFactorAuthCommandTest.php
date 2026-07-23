<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisableTwoFactorAuthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_disables_two_factor_for_the_given_user(): void
    {
        $user = User::factory()->create();
        $user->createTwoFactorAuth();
        $user->confirmTwoFactorAuth($user->fresh()->makeTwoFactorCode());

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->artisan('app:user:disable-2fa', ['email' => $user->email])
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_it_is_a_no_op_when_two_factor_is_not_enabled(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:user:disable-2fa', ['email' => $user->email])
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_it_fails_for_an_unknown_email(): void
    {
        $this->artisan('app:user:disable-2fa', ['email' => 'nobody@example.com'])
            ->assertExitCode(1);
    }
}
