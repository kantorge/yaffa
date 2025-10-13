<?php

namespace Tests\Unit\Events;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Tests\TestCase;

class LoginEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_event_is_dispatched_on_successful_login(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'password' => bcrypt($password = 'password'),
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertRedirect(route('home'));

        Event::assertDispatched(Login::class, fn ($event) => $event->user->id === $user->id);
    }
}
