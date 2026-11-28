<?php

use App\Models\AiProviderConfig;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['read', 'write', 'settings']);
});

function compatPayload(array $overrides = []): array
{
    return array_merge([
        'provider' => 'openai_compatible',
        'model' => 'my-custom-model',
        'api_key' => 'sk-test-1234567890',
        'base_url' => 'https://93.184.216.34/v1',
    ], $overrides);
}

it('stores an openai-compatible config with a free-text model and base url', function () {
    $this->postJson(route('api.v1.ai.config.store'), compatPayload())
        ->assertCreated()
        ->assertJsonPath('base_url', 'https://93.184.216.34/v1');

    $config = AiProviderConfig::first();
    expect($config->isModelAvailable())->toBeTrue()
        ->and($config->prismProvider())->toBe('openai')
        ->and($config->prismConfig()['url'])->toBe('https://93.184.216.34/v1');
});

it('requires a base url for openai-compatible', function () {
    $this->postJson(route('api.v1.ai.config.store'), compatPayload(['base_url' => null]))
        ->assertJsonValidationErrors('base_url');
});

it('rejects base url for regular providers', function () {
    $this->postJson(route('api.v1.ai.config.store'), [
        'provider' => 'openai',
        'model' => 'gpt-5.6-luna',
        'api_key' => 'sk-test-1234567890',
        'base_url' => 'https://example.com',
    ])->assertJsonValidationErrors('base_url');
});

it('blocks private hosts unless the operator opts in, and metadata always', function () {
    $this->postJson(route('api.v1.ai.config.store'), compatPayload(['base_url' => 'http://192.168.1.10:11434/v1']))
        ->assertJsonValidationErrors('base_url');

    config(['yaffa.ai_allow_private_endpoints' => true]);

    $this->postJson(route('api.v1.ai.config.store'), compatPayload(['base_url' => 'http://169.254.169.254/v1']))
        ->assertJsonValidationErrors('base_url');
    $this->postJson(route('api.v1.ai.config.store'), compatPayload(['base_url' => 'http://192.168.1.10:11434/v1']))
        ->assertCreated();
});
