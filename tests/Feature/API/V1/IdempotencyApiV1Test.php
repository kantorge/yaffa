<?php

use App\Jobs\AiProcessingJob;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function standardTransactionPayload(User $user): array
{
    $account = AccountEntity::factory()->asAccount($user)->create(['active' => true]);
    $payee = AccountEntity::factory()->asPayee($user)->create(['active' => true]);
    $category = Category::factory()->for($user)->create(['active' => true]);

    return [
        'action' => 'create',
        'transaction_type' => 'withdrawal',
        'config_type' => 'standard',
        'date' => now()->format('Y-m-d'),
        'reconciled' => false,
        'schedule' => false,
        'config' => [
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
            'amount_from' => 10,
            'amount_to' => 10,
        ],
        'items' => [['amount' => 10, 'category_id' => $category->id, 'tags' => []]],
    ];
}

it('creates a standard transaction only once for a repeated Idempotency-Key', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);
    $payload = standardTransactionPayload($user);
    $headers = ['Idempotency-Key' => 'tx-key-1'];

    $first = $this->postJson(route('api.v1.transactions.store-standard'), $payload, $headers)->assertOk();
    $second = $this->postJson(route('api.v1.transactions.store-standard'), $payload, $headers)
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json('transaction.id'))->toBe($first->json('transaction.id'));
    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('rejects a reused key with a different payload', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);
    $payload = standardTransactionPayload($user);
    $headers = ['Idempotency-Key' => 'tx-key-2'];

    $this->postJson(route('api.v1.transactions.store-standard'), $payload, $headers)->assertOk();

    $payload['items'][0]['amount'] = 11;
    $payload['config']['amount_from'] = 11;
    $payload['config']['amount_to'] = 11;

    $this->postJson(route('api.v1.transactions.store-standard'), $payload, $headers)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
});

it('does not remember failed requests, so the same key can be retried after a fix', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);
    $payload = standardTransactionPayload($user);
    $headers = ['Idempotency-Key' => 'tx-key-3'];

    $bad = $payload;
    $bad['items'][0]['category_id'] = 999999;
    $this->postJson(route('api.v1.transactions.store-standard'), $bad, $headers)->assertUnprocessable();
    expect(IdempotencyKey::query()->count())->toBe(0);

    $this->postJson(route('api.v1.transactions.store-standard'), $payload, $headers)->assertOk();
});

it('maps item row validation errors to indexed field keys inside the error envelope', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);
    $payload = standardTransactionPayload($user);
    $payload['items'][0]['category_id'] = 999999;

    $this->postJson(route('api.v1.transactions.store-standard'), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors(['items.0.category_id']);
});

it('creates one document for a repeated multipart upload with the same key', function () {
    Queue::fake();
    Storage::fake('local');
    config(['ai-documents.file_upload.allowed_types' => ['jpg', 'png', 'pdf']]);

    $user = User::factory()->create();
    AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => true]);
    Sanctum::actingAs($user, ['*']);

    $send = fn () => $this->post(
        route('api.v1.documents.store'),
        [
            'files' => [UploadedFile::fake()->image('receipt.jpg'), UploadedFile::fake()->image('receipt.jpg')],
            'source' => 'mobile_scan',
            'captured_at' => '2026-09-28T10:15:00Z',
            'note' => 'Lunch',
        ],
        ['Idempotency-Key' => 'doc-key-1', 'Accept' => 'application/json']
    );

    $first = $send()->assertCreated();
    $second = $send()->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json('id'))->toBe($first->json('id'));
    expect(AiDocument::query()->count())->toBe(1);
    Queue::assertPushed(AiProcessingJob::class, 1);

    $document = AiDocument::query()->first();
    expect($document->source_type)->toBe('mobile_scan');
    expect($document->note)->toBe('Lunch');
    // Two same-named files must not overwrite each other
    expect($document->files)->toHaveCount(2);
    expect($document->files->pluck('file_path')->unique())->toHaveCount(2);
});

it('returns the limit in the error payload for an oversize file', function () {
    Storage::fake('local');
    config(['ai-documents.file_upload.allowed_types' => ['jpg'], 'ai-documents.file_upload.max_file_size_mb' => 1]);

    $user = User::factory()->create();
    AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->post(
        route('api.v1.documents.store'),
        ['files' => [UploadedFile::fake()->create('big.jpg', 2048, 'image/jpeg')]],
        ['Accept' => 'application/json']
    )
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'FILE_TOO_LARGE')
        ->assertJsonPath('error.limit_mb', 1);
});

it('filters documents by updated_since', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $old = AiDocument::factory()->for($user)->create();
    $old->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
    $recent = AiDocument::factory()->for($user)->create();

    $ids = collect($this->getJson(route('api.v1.documents.index', ['updated_since' => now()->subDay()->toIso8601String()]))
        ->assertOk()->json('data'))->pluck('id');

    expect($ids->all())->toBe([$recent->id]);
});
