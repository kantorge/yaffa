<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Failed;

class LoginFailedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_failed_event_is_dispatched_on_failed_login()
    {
        Event::fake();

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');

        Event::assertDispatched(Failed::class, fn ($event) => $event->credentials['email'] === $user->email);
    }
}
