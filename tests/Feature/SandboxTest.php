<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_alert_is_not_present_if_sandbox_is_disabled(): void
    {
        config(['yaffa.sandbox_mode' => false]);

        $user = User::factory()->create(['language' => 'en']);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('sandBoxResetAlert', false);
    }

    public function test_reset_alert_is_present_if_sandbox_is_enabled(): void
    {
        config(['yaffa.sandbox_mode' => true]);

        $user = User::factory()->create(['language' => 'en']);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('sandBoxResetAlert', false);
    }
}
