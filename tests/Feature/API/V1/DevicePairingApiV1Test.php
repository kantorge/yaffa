<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a named full-access token and returns it once as a deep link and QR', function () {
    config(['app.url' => 'https://yaffa.example.com']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.users.me.tokens.pairing'), ['name' => 'Pixel 8 – YAFFA app'])
        ->assertCreated()
        ->assertJsonPath('warnings', []);

    $token = $response->json('token');
    expect($response->json('deep_link'))->toBe(
        'yaffa://pair?v=1&url=https%3A%2F%2Fyaffa.example.com&token=' . rawurlencode($token)
    );
    expect($response->json('qr_svg'))->toContain('<svg');

    $stored = $user->tokens()->first();
    expect($stored->name)->toBe('Pixel 8 – YAFFA app');
    expect($stored->abilities)->toEqualCanonicalizing(['read', 'write', 'settings']);

    // The token really works for the app, through the dedicated header
    $this->app['auth']->forgetGuards();
    $this->withHeader('X-Yaffa-Token', $token)->getJson(route('api.v1.meta'))->assertJsonPath('user.locale', $user->locale);
});

it('warns about a non-HTTPS or localhost base URL', function () {
    config(['app.url' => 'http://localhost']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.v1.users.me.tokens.pairing'), ['name' => 'Test phone'])
        ->assertCreated()
        ->assertJsonPath('warnings', ['not_https', 'localhost']);
});

it('does not let a device token pair further devices', function () {
    $token = User::factory()->create()->createToken('device', ['*'])->plainTextToken;

    $this->withToken($token)
        ->postJson(route('api.v1.users.me.tokens.pairing'), ['name' => 'Another'])
        ->assertForbidden();
});

it('is blocked in sandbox mode', function () {
    config(['yaffa.sandbox_mode' => true]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.v1.users.me.tokens.pairing'), ['name' => 'Test phone'])
        ->assertForbidden();
});

it('revokes a device so its next call fails', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device', ['*']);

    $this->actingAs($user)
        ->deleteJson(route('api.v1.users.me.tokens.destroy', $token->accessToken->id))
        ->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->withToken($token->plainTextToken)->getJson(route('api.v1.meta'))->assertJsonMissingPath('user');
});
