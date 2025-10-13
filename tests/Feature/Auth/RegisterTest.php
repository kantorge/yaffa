<?php

namespace Tests\Feature\Auth;

use App\Events\Registered;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure that the yaffa.email_verification_required config value is set to true for these tests.
     * Exceptions will be set for specific tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['yaffa.email_verification_required' => true]);
        // By default, allow infinite users for these tests.
        config(['yaffa.registered_user_limit' => 0]);

        // Ensure that recaptcha is disabled for these tests.
        config(['recaptcha.api_site_key' => null]);
        config(['recaptcha.api_secret_key' => null]);
    }

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

    public function test_user_can_view_registration_form(): void
    {
        $response = $this->get($this->registerGetRoute());

        $response->assertSuccessful();
        $response->assertViewIs('auth.register');
    }

    public function test_user_cannot_view_registration_form_when_authenticated(): void
    {
        /** @var User $user */
        $user = User::factory()->make();

        $response = $this->actingAs($user)->get($this->registerGetRoute());

        $response->assertRedirect($this->guestMiddlewareRoute());
    }

    public function test_user_can_register(): void
    {
        Event::fake();

        /** @var User $userData */
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
        // By default, a user is not verified
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check($password, $user->password));

        // Registration generates event
        Event::assertDispatched(Registered::class, fn ($e) => $e->user->id === $user->id);
    }

    public function test_user_registration_fails_when_user_limit_is_reached(): void
    {
        // For this test, set the allowed user count to 1.
        config(['yaffa.registered_user_limit' => 1]);

        // Create a user to reach or exceed  the limit.
        User::factory()->create([
            'email' => 'test1@yaffa.cc'
        ]);

        // When opening the registration page, the user should be redirected to the login page with an error message,
        // instead of opening the registration page.
        $response = $this->get($this->registerGetRoute());
        $response->assertRedirect(route('login'));

        // The notification_collection session key should contain a message about the user limit being reached.
        $this->assertTrue(session()->has('notification_collection'));
        $notifications = session('notification_collection');
        $this->assertNotEmpty($notifications);
        // The title of the first notification should read "User limit reached"
        $this->assertEquals("User limit reached", $notifications[0]['title']);

        $this->assertGuest();
    }

    public function test_user_is_automatically_verified_if_feature_is_enabled(): void
    {
        // Ensure that the yaffa.email_verification_required config value is set to false for this test.
        config(['yaffa.email_verification_required' => false]);

        /** @var User $userData */
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
        $user = User::firstWhere('email', $userData->email);
        $this->assertAuthenticatedAs($user);

        // By default, a user is verified
        $this->assertNotNull($user->email_verified_at);

        // The user is redirected to the home page after registering, but instead, the verification page should be shown.
        $response->assertRedirect(route('home'));
    }

    public function test_user_receives_verification_email_if_feature_is_enabled(): void
    {
        Notification::fake();

        /** @var User $userData */
        $userData = User::factory()->make();
        $password = 'notasecret';
        $currency = CurrencyData::getRandomIsoCode();
        $this
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
                'base_currency' => $currency,
            ]);

        // Verify that the user is created and not verified.
        $users = User::where('email', $userData->email)->get();
        $this->assertCount(1, $users);
        $user = $users->first();
        $this->assertNull($user->email_verified_at);

        // Verify that a notification was sent to the user.
        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    public function test_user_cannot_register_without_name(): void
    {
        /** @var User $userData */
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

    public function test_user_cannot_register_without_email(): void
    {
        /** @var User $userData */
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

    public function test_user_cannot_register_with_invalid_email(): void
    {
        /** @var User $userData */
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

    public function test_user_cannot_register_without_password(): void
    {
        /** @var User $userData */
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

    public function test_user_cannot_register_without_password_confirmation(): void
    {
        /** @var User $userData */
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

    public function test_user_cannot_register_with_passwords_not_matching(): void
    {
        /** @var User $userData */
        $userData = User::factory()->make();
        $password = 'notasecret';

        $response = $this
            ->from($this->registerGetRoute())
            ->post($this->registerPostRoute(), [
                'name' => $userData->name,
                'email' => $userData->email,
                'password' => $password,
                'password_confirmation' => 'not' . $password, // Does not match
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

    public function test_user_cannot_register_without_accepting_terms(): void
    {
        /** @var User $userData */
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
                'tos' => '',  // Terms of Service not accepted
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
