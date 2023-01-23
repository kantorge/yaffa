<?php

namespace Tests\Feature\Auth;

use App\Events\Registered;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function successfulRegistrationRoute(): string
    {
        return route('home');
    }

    protected function registerGetRoute(): string
    {
        return route('register');
    }

    protected function registerPostRoute(): string
    {
        return route('register');
    }

    protected function guestMiddlewareRoute(): string
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
            'default_data' => 'default',
            'base_currency' => CurrencyData::getRandomIsoCode(),
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

        // Registration generates event
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
            'default_data' => 'default',
            'base_currency' => CurrencyData::getRandomIsoCode(),
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
            'default_data' => 'default',
            'base_currency' => CurrencyData::getRandomIsoCode(),
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
            'default_data' => 'default',
            'base_currency' => CurrencyData::getRandomIsoCode(),
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
            'default_data' => 'default',
            'base_currency' => CurrencyData::getRandomIsoCode(),
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
            'default_data' => 'default',
            'base_currency' => CurrencyData::getRandomIsoCode(),
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
            'default_data' => 'default',
            'base_currency' => CurrencyData::getRandomIsoCode(),
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
            'default_data' => 'default',
            'base_currency' => CurrencyData::getRandomIsoCode(),
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
