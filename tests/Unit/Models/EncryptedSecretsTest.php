<?php

use App\Models\AiProviderConfig;
use App\Models\GoogleDriveConfig;
use App\Models\InvestmentProviderConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

dataset('secret columns', [
    'AI provider api key' => [AiProviderConfig::class, 'api_key', 'sk-test-super-secret-key-12345'],
    'investment provider credentials' => [InvestmentProviderConfig::class, 'credentials', ['api_key' => 'alpha-test-super-secret-key']],
    'Google Drive service account' => [GoogleDriveConfig::class, 'service_account_json', '{"type":"service_account","private_key":"-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----"}'],
]);

it('stores the secret encrypted at rest', function (string $model, string $column, mixed $secret) {
    $config = $model::factory()->create([$column => $secret]);

    $raw = DB::table($config->getTable())->where('id', $config->id)->value($column);

    // Whatever the cast, no fragment of the plaintext may be readable in the column
    $needle = is_array($secret) ? reset($secret) : mb_substr($secret, 0, 20);
    expect($raw)->not->toContain($needle);
})->with('secret columns');

it('hides the secret from serialized output', function (string $model, string $column, mixed $secret) {
    $config = $model::factory()->create([$column => $secret]);

    expect($config->toArray())->not->toHaveKey($column);
})->with('secret columns');
