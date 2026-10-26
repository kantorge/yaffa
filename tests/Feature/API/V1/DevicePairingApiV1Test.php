<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a named read+write token and returns it once as a deep link and QR', function () {
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
    expect($stored->abilities)->toEqualCanonicalizing(['read', 'write']);

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

it('replaces the secret of an existing token and returns a fresh pairing code', function () {
    config(['app.url' => 'https://yaffa.example.com']);
    $user = User::factory()->create();
    $old = $user->createToken('Pixel', ['read', 'write'], now()->addDays(30));

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.users.me.tokens.pairing.rotate', $old->accessToken->id))
        ->assertOk();

    $new = $response->json('token');
    expect($new)->not->toBe($old->plainTextToken);
    expect($response->json('qr_svg'))->toContain('<svg');
    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->abilities)->toEqualCanonicalizing(['read', 'write']);

    $this->app['auth']->forgetGuards();
    $this->withToken($old->plainTextToken)->getJson(route('api.v1.meta'))->assertJsonMissingPath('user');
    $this->app['auth']->forgetGuards();
    $this->withToken($new)->getJson(route('api.v1.meta'))->assertJsonPath('user.locale', $user->locale);
});

it('cannot rotate another user\'s token or be used by a token', function () {
    $other = User::factory()->create()->createToken('theirs', ['*']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.v1.users.me.tokens.pairing.rotate', $other->accessToken->id))
        ->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->withToken($other->plainTextToken)
        ->postJson(route('api.v1.users.me.tokens.pairing.rotate', $other->accessToken->id))
        ->assertForbidden();
});

it('refuses to show a pairing code for a read-only token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('script', ['read']);

    $this->actingAs($user)
        ->postJson(route('api.v1.users.me.tokens.pairing.rotate', $token->accessToken->id))
        ->assertUnprocessable();
});

it('refuses to show a pairing code for a token with settings or wildcard access', function (array $abilities) {
    $user = User::factory()->create();
    $token = $user->createToken('admin', $abilities);
    $secret = $token->accessToken->token;

    $this->actingAs($user)
        ->postJson(route('api.v1.users.me.tokens.pairing.rotate', $token->accessToken->id))
        ->assertUnprocessable();

    expect($token->accessToken->fresh()->token)->toBe($secret);
})->with([[['read', 'write', 'settings']], [['*']]]);

it('refuses to pair over plain HTTP in production', function () {
    config(['app.url' => 'http://yaffa.example.com']);
    $this->app['env'] = 'production';
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.v1.users.me.tokens.pairing'), ['name' => 'Phone'])
        ->assertUnprocessable();

    expect($user->tokens()->count())->toBe(0);
});
