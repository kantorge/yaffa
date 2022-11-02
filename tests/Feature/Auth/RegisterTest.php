<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function successfulRegistrationRoute()
    {
        return route('home');
    }

    protected function registerGetRoute()
    {
        return route('register');
    }

    protected function registerPostRoute()
    {
        return route('register');
    }

    protected function guestMiddlewareRoute()
    {
        return route('home');
    }

    /** @test */
    public function test_user_can_view_registration_form()
    {
        $response = $this->get($this->registerGetRoute());

        $response->assertSuccessful();
        $response->assertViewIs('auth.register');
    }

    /** @test */
    public function test_user_cannot_view_registration_form_when_authenticated()
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = User::factory()->make();

        $response = $this->actingAs($user)->get($this->registerGetRoute());

        $response->assertRedirect($this->guestMiddlewareRoute());
    }

    /** @test */
    public function test_user_can_register()
    {
        Event::fake();

        $userData = User::factory()->make();
        $password = 'notasecret';

        $response = $this
        ->from($this->registerGetRoute())
        ->post($this->registerPostRoute(), [
            'name' => $userData->name,
            'email' => $userData->email,
            'password' => $password,
            'password_confirmation' => $password,
            'language' => $userData->language,
            'locale' => $userData->locale,
            'tos' => 'yes',
        ]);

        // Get newly created user by email address
        $users = User::where('email', $userData->email)->get();
        $user = $users->first();

        $response->assertRedirect($this->successfulRegistrationRoute());
        $this->assertCount(1, $users);
        $this->assertAuthenticatedAs($user);
        $this->assertEquals($userData->name, $user->name);
        $this->assertEquals($userData->email, $user->email);
        $this->assertTrue(Hash::check($password, $user->password));
        Event::assertDispatched(Registered::class, function ($e) use ($user) {
            return $e->user->id === $user->id;
        });
    }

    /** @test */
    public function test_user_cannot_register_without_name()
    {
        $userData = User::factory()->make();
        $password = 'notasecret';

        $response = $this
        ->from($this->registerGetRoute())
        ->post($this->registerPostRoute(), [
            'name' => '',
            'email' => $userData->email,
            'password' => $password,
            'password_confirmation' => $password,
            'language' => $userData->language,
            'locale' => $userData->locale,
            'tos' => 'yes',
        ]);

        $users = User::where('email', $userData->email)->get();

        $this->assertCount(0, $users);
        $response->assertRedirect($this->registerGetRoute());
        $response->assertSessionHasErrors('name');
        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_register_without_email()
    {
        $userData = User::factory()->make();
        $password = 'notasecret';

        $response = $this
        ->from($this->registerGetRoute())
        ->post($this->registerPostRoute(), [
            'name' => $userData->name,
            'email' => '',
            'password' => $password,
            'password_confirmation' => $password,
            'language' => $userData->language,
            'locale' => $userData->locale,
            'tos' => 'yes',
        ]);

        $users = User::where('email', $userData->email)->get();

        $this->assertCount(0, $users);
        $response->assertRedirect($this->registerGetRoute());
        $response->assertSessionHasErrors('email');
        $this->assertTrue(session()->hasOldInput('name'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_register_with_invalid_email()
    {
        $userData = User::factory()->make();
        $password = 'notasecret';

        $response = $this
        ->from($this->registerGetRoute())
        ->post($this->registerPostRoute(), [
            'name' => $userData->name,
            'email' => 'invalid-email',
            'password' => $password,
            'password_confirmation' => $password,
            'language' => $userData->language,
            'locale' => $userData->locale,
            'tos' => 'yes',
        ]);

        $users = User::where('email', $userData->email)->get();

        $this->assertCount(0, $users);
        $response->assertRedirect($this->registerGetRoute());
        $response->assertSessionHasErrors('email');
        $this->assertTrue(session()->hasOldInput('name'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_register_without_password()
    {
        $userData = User::factory()->make();

        $response = $this
        ->from($this->registerGetRoute())
        ->post($this->registerPostRoute(), [
            'name' => $userData->name,
            'email' => $userData->email,
            'password' => '',
            'password_confirmation' => '',
            'language' => $userData->language,
            'locale' => $userData->locale,
            'tos' => 'yes',
        ]);

        $users = User::where('email', $userData->email)->get();

        $this->assertCount(0, $users);
        $response->assertRedirect($this->registerGetRoute());
        $response->assertSessionHasErrors('password');
        $this->assertTrue(session()->hasOldInput('name'));
        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_register_without_password_confirmation()
    {
        $userData = User::factory()->make();
        $password = 'notasecret';

        $response = $this
        ->from($this->registerGetRoute())
        ->post($this->registerPostRoute(), [
            'name' => $userData->name,
            'email' => $userData->email,
            'password' => $password,
            'password_confirmation' => '',
            'language' => $userData->language,
            'locale' => $userData->locale,
            'tos' => 'yes',
        ]);

        $users = User::where('email', $userData->email)->get();

        $this->assertCount(0, $users);
        $response->assertRedirect($this->registerGetRoute());
        $response->assertSessionHasErrors('password');
        $this->assertTrue(session()->hasOldInput('name'));
        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_register_with_passwords_not_matching()
    {
        $userData = User::factory()->make();
        $password = 'notasecret';

        $response = $this
        ->from($this->registerGetRoute())
        ->post($this->registerPostRoute(), [
            'name' => $userData->name,
            'email' => $userData->email,
            'password' => $password,
            'password_confirmation' => 'not' . $password,
            'language' => $userData->language,
            'locale' => $userData->locale,
            'tos' => 'yes',
        ]);

        $users = User::where('email', $userData->email)->get();

        $this->assertCount(0, $users);
        $response->assertRedirect($this->registerGetRoute());
        $response->assertSessionHasErrors('password');
        $this->assertTrue(session()->hasOldInput('name'));
        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    public function test_user_cannot_register_without_accepting_terms()
    {
        $userData = User::factory()->make();
        $password = 'notasecret';

        $response = $this
        ->from($this->registerGetRoute())
        ->post($this->registerPostRoute(), [
            'name' => $userData->name,
            'email' => $userData->email,
            'password' => $password,
            'password_confirmation' => $password,
            'language' => $userData->language,
            'locale' => $userData->locale,
            'tos' => '',
        ]);

        $users = User::where('email', $userData->email)->get();

        $this->assertCount(0, $users);
        $response->assertRedirect($this->registerGetRoute());
        $response->assertSessionHasErrors('tos');
        $this->assertTrue(session()->hasOldInput('name'));
        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertFalse(session()->hasOldInput('tos'));
        $this->assertGuest();
    }
}
