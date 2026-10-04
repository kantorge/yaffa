<?php

use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function notificationPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'mobile_notification',
        'source_app' => 'com.bank.app',
        'title' => 'Card payment',
        'text' => 'You paid 12.50 EUR at Tesco',
        'posted_at' => '2026-10-01T09:30:00Z',
    ], $overrides);
}

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');

    $this->user = User::factory()->create();
    AiUserSettings::factory()->create(['user_id' => $this->user->id, 'ai_enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);
});

it('accepts a text-only notification and stores it in the same layout as a forwarded email', function () {
    $response = $this->postJson(route('api.v1.documents.store'), notificationPayload())->assertCreated();

    $document = AiDocument::query()->findOrFail($response->json('id'));
    expect($document->source_type)->toBe('mobile_notification');
    expect($document->captured_at->toIso8601String())->toBe('2026-10-01T09:30:00+00:00');

    $file = $document->files->sole();
    expect($file->file_type)->toBe('txt');
    expect(Storage::disk('local')->get($file->file_path))->toBe(
        "Subject: Card payment\nFrom: com.bank.app\nDate: 2026-10-01 09:30:00\n\n---\n\nYou paid 12.50 EUR at Tesco"
    );
    Queue::assertPushed(AiProcessingJob::class, 1);
});

it('requires the source app and text for a notification and rejects files', function () {
    $this->postJson(route('api.v1.documents.store'), ['source' => 'mobile_notification'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source_app', 'text']);
});

it('returns the original document when the bank app updates and re-posts the notification', function () {
    $headers = ['Idempotency-Key' => 'notif-abc'];

    $first = $this->postJson(route('api.v1.documents.store'), notificationPayload(), $headers)->assertCreated();
    $second = $this->postJson(
        route('api.v1.documents.store'),
        notificationPayload(['text' => 'You paid 12.50 EUR at Tesco. Balance: 100 EUR']),
        $headers
    )->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json('id'))->toBe($first->json('id'));
    expect(AiDocument::query()->count())->toBe(1);
    Queue::assertPushed(AiProcessingJob::class, 1);
});

it('flags a matching transaction and an earlier notification, without modifying either', function () {
    $service = app(DuplicateDetectionService::class);

    $transaction = Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id, 'date' => '2026-10-01']);
    $amount = (float) $transaction->transactionItems->sum(fn ($item) => App\Casts\MoneyCast::toFloat($item->amount));
    $before = $transaction->fresh()->toArray();

    $processed = fn (string $date, float $value) => [
        'raw' => ['date' => $date, 'amount' => $value, 'config_type' => 'standard'],
        'date' => $date,
        'config_type' => 'standard',
        'config' => ['amount_from' => $value, 'amount_to' => $value, 'account_from_id' => 5, 'account_to_id' => 6],
    ];

    $notification = AiDocument::factory()->for($this->user)->create([
        'source_type' => 'mobile_notification',
        'status' => 'ready_for_review',
        'processed_transaction_data' => $processed('2026-10-01', 12.5),
    ]);
    $receipt = AiDocument::factory()->for($this->user)->create([
        'source_type' => 'mobile_scan',
        'status' => 'ready_for_review',
        'processed_transaction_data' => $processed('2026-10-02', 12.5),
    ]);
    $unrelated = AiDocument::factory()->for($this->user)->create([
        'processed_transaction_data' => $processed('2026-10-02', 480.0),
    ]);

    $found = $service->findDocumentDuplicates($this->user, $receipt);

    expect(collect($found)->pluck('id')->all())->toBe([$notification->id]);
    expect($notification->fresh()->processed_transaction_data)->toEqual($processed('2026-10-01', 12.5));
    expect($unrelated->fresh()->status)->not->toBeNull();
    expect($transaction->fresh()->toArray())->toBe($before);
    expect($amount)->toBeFloat();
});

it('reports document duplicates through the check-duplicates endpoint', function () {
    $data = [
        'raw' => ['date' => '2026-10-01', 'amount' => 12.5],
        'date' => '2026-10-01',
        'config' => ['amount_from' => 12.5, 'amount_to' => 12.5],
    ];
    $first = AiDocument::factory()->for($this->user)->create(['processed_transaction_data' => $data]);
    $second = AiDocument::factory()->for($this->user)->create(['processed_transaction_data' => $data]);

    $this->postJson(route('api.v1.documents.checkDuplicates', $second))
        ->assertOk()
        ->assertJsonPath('document_duplicates.0.id', $first->id);
});

it('stores duplicate candidates on the document once processing succeeds', function () {
    $data = [
        'raw' => ['date' => '2026-10-01', 'amount' => 12.5],
        'date' => '2026-10-01',
        'config' => ['amount_from' => 12.5, 'amount_to' => 12.5],
    ];
    $earlier = AiDocument::factory()->for($this->user)->create(['processed_transaction_data' => $data]);
    $document = AiDocument::factory()->for($this->user)->create(['processed_transaction_data' => $data]);

    $service = Mockery::mock(App\Services\ProcessDocumentService::class);
    $service->shouldReceive('process')->once()->andReturn(['success' => true]);

    (new AiProcessingJob($document))->handle(
        $service,
        app(App\Services\AiUserSettingsResolver::class),
        app(DuplicateDetectionService::class),
    );

    $candidates = $document->fresh()->processed_transaction_data['duplicate_candidates'];
    expect($candidates['documents'][0]['id'])->toBe($earlier->id);
    expect($candidates['transactions'])->toBe([]);
});
