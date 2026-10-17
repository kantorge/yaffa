<?php

use App\Models\GoogleDriveConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

$validJson = '{"type":"service_account","project_id":"test-project","private_key_id":"key123","private_key":"-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----","client_email":"test@test-project.iam.gserviceaccount.com","client_id":"123456789","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token"}';
$missingKeysJson = '{"type":"service_account","project_id":"test"}';

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($this->user, ['*']);
});

// ===== CREATE (POST /api/v1/google-drive/config) =====

it('rejects an invalid create request', function (array $payload, string $field) {
    $this->postJson(route('api.v1.google-drive.config.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing service_account_json' => [['folder_id' => 'test-folder-id'], 'service_account_json'],
    'missing folder_id' => [['service_account_json' => $validJson], 'folder_id'],
    'short service_account_json' => [['service_account_json' => 'too short', 'folder_id' => 'test-folder-id'], 'service_account_json'],
    'very long service_account_json' => [['service_account_json' => str_repeat('x', 5001), 'folder_id' => 'test-folder-id'], 'service_account_json'],
    'invalid JSON' => [['service_account_json' => '{"invalid": "json", missing bracket', 'folder_id' => 'test-folder-id'], 'service_account_json'],
    'JSON missing required keys' => [['service_account_json' => $missingKeysJson, 'folder_id' => 'test-folder-id'], 'service_account_json'],
    'untrusted token_uri' => [[
        'service_account_json' => str_replace('https://oauth2.googleapis.com/token', 'http://169.254.169.254/latest/meta-data/', $validJson),
        'folder_id' => 'test-folder-id',
    ], 'service_account_json'],
    'untrusted auth_uri' => [[
        'service_account_json' => str_replace('https://accounts.google.com/o/oauth2/auth', 'http://internal.attacker.example/oauth', $validJson),
        'folder_id' => 'test-folder-id',
    ], 'service_account_json'],
    'processed_folder_id equal to folder_id' => [[
        'service_account_json' => $validJson,
        'folder_id' => 'same-folder-id',
        'post_import_actions' => ['move_to_processed'],
        'processed_folder_id' => 'same-folder-id',
    ], 'processed_folder_id'],
]);

it('accepts a valid create request', function (array $extra) use ($validJson) {
    $this->postJson(route('api.v1.google-drive.config.store'), [
        'service_account_json' => $validJson,
        'folder_id' => 'test-folder-id',
        ...$extra,
    ])->assertCreated();
})->with([
    'minimal' => [[]],
    'post_import_actions array' => [['post_import_actions' => ['delete', 'trash']]],
    'enabled boolean' => [['enabled' => false]],
]);

it('prevents multiple configs per user', function () use ($validJson) {
    GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

    $this->postJson(route('api.v1.google-drive.config.store'), [
        'service_account_json' => $validJson,
        'folder_id' => 'another-folder-id',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['folder_id']);
});

// ===== UPDATE (PATCH /api/v1/google-drive/config/{id}) =====

it('keeps the folder_id when an update omits it', function () {
    $config = GoogleDriveConfig::factory()->create([
        'user_id' => $this->user->id,
        'folder_id' => 'original-folder-id',
    ]);

    $this->patchJson(route('api.v1.google-drive.config.update', $config), ['enabled' => true])
        ->assertOk();

    expect($config->refresh()->folder_id)->toBe('original-folder-id');
});

/*
 * These cases assert only that the request passes validation (200). The resulting persisted
 * values are asserted with DB checks in GoogleDriveConfigApiControllerTest
 * (test_update_preserves_service_account_json_when_not_provided / _when_empty /
 * _with_existing_placeholder, test_update_changes_service_account_json_when_provided,
 * test_update_changes_post_import_actions, test_update_changes_enabled_status) - not repeated here.
 */
it('accepts a valid update request', function (array $payload) {
    $config = GoogleDriveConfig::factory()->create([
        'user_id' => $this->user->id,
        'folder_id' => 'original-folder-id',
        'post_import_actions' => null,
        'enabled' => true,
    ]);

    $this->patchJson(route('api.v1.google-drive.config.update', $config), $payload)
        ->assertOk();
})->with([
    'missing service_account_json' => [['folder_id' => 'new-folder-id']],
    'empty service_account_json' => [['folder_id' => 'new-folder-id', 'service_account_json' => '']],
    'new service_account_json' => [[
        'folder_id' => 'new-folder-id',
        'service_account_json' => str_replace('"project_id":"test-project"', '"project_id":"new-project"', $validJson),
    ]],
    'existing placeholder' => [['folder_id' => 'new-folder-id', 'service_account_json' => '__existing__']],
    'changed post_import_actions' => [['folder_id' => 'original-folder-id', 'post_import_actions' => ['delete']]],
    'changed enabled' => [['folder_id' => 'original-folder-id', 'enabled' => false]],
    'processed_folder_id different from folder_id' => [[
        'folder_id' => 'original-folder-id',
        'post_import_actions' => ['move_to_processed'],
        'processed_folder_id' => 'processed-folder-id',
    ]],
]);

it('rejects an invalid update request', function (array $payload, string $field) {
    $config = GoogleDriveConfig::factory()->create([
        'user_id' => $this->user->id,
        'folder_id' => 'original-folder-id',
    ]);

    $this->patchJson(route('api.v1.google-drive.config.update', $config), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'invalid service_account_json' => [['folder_id' => 'new-folder-id', 'service_account_json' => '{"invalid": json}'], 'service_account_json'],
    'processed_folder_id equal to folder_id' => [[
        'folder_id' => 'original-folder-id',
        'post_import_actions' => ['move_to_processed'],
        'processed_folder_id' => 'original-folder-id',
    ], 'processed_folder_id'],
    'processed_folder_id equal to the stored folder_id when folder_id is omitted' => [[
        'post_import_actions' => ['move_to_processed'],
        'processed_folder_id' => 'original-folder-id',
    ], 'processed_folder_id'],
    'move_to_processed without processed_folder_id' => [[
        'post_import_actions' => ['move_to_processed'],
        'processed_folder_id' => null,
    ], 'processed_folder_id'],
]);

// ===== TEST CONNECTION (POST /api/v1/google-drive/config/test) =====

it('rejects an invalid test connection request', function (array $payload, string $field) {
    $this->postJson(route('api.v1.google-drive.config.test'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing service_account_json' => [['folder_id' => 'test-folder-id'], 'service_account_json'],
    'missing folder_id' => [['service_account_json' => $validJson], 'folder_id'],
    'invalid JSON' => [['service_account_json' => 'not valid json', 'folder_id' => 'test-folder-id'], 'service_account_json'],
    'JSON missing required keys' => [['service_account_json' => $missingKeysJson, 'folder_id' => 'test-folder-id'], 'service_account_json'],
]);

// The connection attempt itself fails (the credentials aren't real); only validation passing is asserted.
it('passes test connection validation with the existing placeholder', function () {
    GoogleDriveConfig::factory()->create(['user_id' => $this->user->id]);

    $response = $this->postJson(route('api.v1.google-drive.config.test'), [
        'service_account_json' => '__existing__',
        'folder_id' => 'test-folder-id',
    ]);

    expect($response->status())->not->toBe(422);
});

it('passes test connection validation with a new service_account_json', function () use ($validJson) {
    $response = $this->postJson(route('api.v1.google-drive.config.test'), [
        'service_account_json' => $validJson,
        'folder_id' => 'test-folder-id',
    ]);

    expect($response->status())->not->toBe(422);
});
