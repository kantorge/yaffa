<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns public server info to anonymous clients', function () {
    $this->getJson(route('api.v1.meta'))
        ->assertOk()
        ->assertJsonPath('api_version', 1)
        ->assertJsonPath('token_header', 'X-Yaffa-Token')
        ->assertJsonPath('features.ai_documents', true)
        ->assertJsonPath('features.notifications', true)
        ->assertJsonPath('features.push', false)
        ->assertJsonStructure(['yaffa_version', 'min_app_version', 'features' => ['notifications', 'push']])
        ->assertJsonMissingPath('user');
});

it('adds user context when a valid token is sent', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device', ['*'])->plainTextToken;

    $this->withToken($token)->getJson(route('api.v1.meta'))
        ->assertOk()
        ->assertJsonPath('user.locale', $user->locale)
        ->assertJsonStructure(['user' => ['base_currency', 'max_upload_mb', 'max_files_per_submission']]);
});

it('reads the token from X-Yaffa-Token next to Basic credentials', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device', ['*'])->plainTextToken;

    $this->withHeaders([
        'Authorization' => 'Basic ' . base64_encode('proxy:secret'),
        'X-Yaffa-Token' => $token,
    ])->getJson(route('api.v1.meta'))
        ->assertOk()
        ->assertJsonPath('user.locale', $user->locale);
});

it('ignores an invalid token instead of leaking user context', function () {
    $this->withHeader('X-Yaffa-Token', '1|invalid')->getJson(route('api.v1.meta'))
        ->assertOk()
        ->assertJsonMissingPath('user');
});
