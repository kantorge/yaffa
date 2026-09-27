<?php

use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

$validCreatePayload = [
    'provider' => 'openai',
    'model' => 'gpt-4o-mini',
    'api_key' => 'sk-test-1234567890abcdefghij',
];

$validUpdatePayload = [
    'provider' => 'openai',
    'model' => 'gpt-4o',
];

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($this->user, ['*']);
});

function markGeminiProUnsupported(): void
{
    config(['ai-documents.providers.gemini.models' => [
        'gemini-2.5-flash' => ['vision' => true, 'supported' => true],
        'gemini-2.5-pro' => ['vision' => true, 'supported' => false],
    ]]);
}

// ===== CREATE (POST /api/v1/ai/config) =====

it('rejects an invalid create request', function (array $payload, string $field) {
    $this->postJson(route('api.v1.ai.config.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing provider' => [Arr::except($validCreatePayload, 'provider'), 'provider'],
    'missing model' => [Arr::except($validCreatePayload, 'model'), 'model'],
    'missing api_key' => [Arr::except($validCreatePayload, 'api_key'), 'api_key'],
    'invalid provider' => [[...$validCreatePayload, 'provider' => 'invalid-provider'], 'provider'],
    'invalid model for provider' => [[...$validCreatePayload, 'model' => 'invalid-model'], 'model'],
    'short api_key' => [[...$validCreatePayload, 'api_key' => 'short'], 'api_key'],
    'very long api_key' => [[...$validCreatePayload, 'api_key' => str_repeat('x', 501)], 'api_key'],
    'invalid vision_enabled' => [[...$validCreatePayload, 'vision_enabled' => 'yes'], 'vision_enabled'],
]);

it('rejects creating a config with an unsupported model', function () use ($validCreatePayload) {
    markGeminiProUnsupported();

    $this->postJson(route('api.v1.ai.config.store'), [
        ...$validCreatePayload,
        'provider' => 'gemini',
        'model' => 'gemini-2.5-pro',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['model']);
});

it('prevents multiple configs per user', function () {
    AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

    $this->postJson(route('api.v1.ai.config.store'), [
        'provider' => 'gemini',
        'model' => 'gemini-1.5-flash',
        'api_key' => 'test-key-1234567890abcdefghij',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['provider']);
});

// ===== UPDATE (PATCH /api/v1/ai/config/{id}) =====

it('rejects an invalid update request', function (array $payload, string $field) {
    $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

    $this->patchJson(route('api.v1.ai.config.update', $config), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing provider' => [['model' => 'gpt-4o-mini'], 'provider'],
    'missing model' => [['provider' => 'openai'], 'model'],
    'short api_key' => [[...$validUpdatePayload, 'api_key' => 'short'], 'api_key'],
    'invalid vision_enabled' => [[...$validUpdatePayload, 'vision_enabled' => 'invalid'], 'vision_enabled'],
]);

/*
 * These cases assert only that the request passes validation (200) for each api_key shape
 * update accepts. The resulting persisted value for each case is asserted with DB checks in
 * AiProviderConfigApiControllerTest (test_update_preserves_api_key_when_not_provided,
 * test_update_preserves_api_key_when_empty, test_update_changes_api_key_when_provided,
 * test_update_preserves_api_key_with_existing_placeholder) - not repeated here.
 */
it('accepts an update with any supported api_key shape', function (array $payload) {
    $config = AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

    $this->patchJson(route('api.v1.ai.config.update', $config), $payload)
        ->assertOk();
})->with([
    'missing api_key' => [$validUpdatePayload],
    'empty api_key' => [[...$validUpdatePayload, 'api_key' => '']],
    'new api_key' => [[...$validUpdatePayload, 'api_key' => 'sk-new-key-1234567890abcdefghij']],
    'existing placeholder' => [[...$validUpdatePayload, 'api_key' => '__existing__']],
]);

it('allows keeping an existing unsupported model on update', function () {
    $config = AiProviderConfig::factory()->create([
        'user_id' => $this->user->id,
        'provider' => 'gemini',
        'model' => 'gemini-2.5-pro',
    ]);

    $this->patchJson(route('api.v1.ai.config.update', $config), [
        'provider' => 'gemini',
        'model' => 'gemini-2.5-pro',
    ])->assertOk();
});

it('rejects switching to an unsupported model on update', function () {
    markGeminiProUnsupported();

    $config = AiProviderConfig::factory()->create([
        'user_id' => $this->user->id,
        'provider' => 'gemini',
        'model' => 'gemini-2.5-flash',
    ]);

    $this->patchJson(route('api.v1.ai.config.update', $config), [
        'provider' => 'gemini',
        'model' => 'gemini-2.5-pro',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['model']);

    expect($config->refresh()->model)->toBe('gemini-2.5-flash');
});

// ===== TEST CONNECTION (POST /api/v1/ai/test) =====

it('rejects a test connection request with a missing field', function (string $field) use ($validCreatePayload) {
    $this->postJson(route('api.v1.ai.config.test'), Arr::except($validCreatePayload, $field))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with(['provider', 'model', 'api_key']);

// The connection attempt itself fails (the key isn't real); only validation passing is asserted.
it('passes test connection validation with the existing placeholder', function () use ($validCreatePayload) {
    AiProviderConfig::factory()->create(['user_id' => $this->user->id]);

    $response = $this->postJson(route('api.v1.ai.config.test'), [...$validCreatePayload, 'api_key' => '__existing__']);

    expect($response->status())->not->toBe(422);
});

it('passes test connection validation with a new api_key', function () use ($validCreatePayload) {
    $response = $this->postJson(route('api.v1.ai.config.test'), $validCreatePayload);

    expect($response->status())->not->toBe(422);
});
