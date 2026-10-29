<?php

use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\User;
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
    ], $overrides);
}

beforeEach(function () {
    $this->travelTo('2026-10-01 09:30:00');
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

it('hashes the notification text so the same-event check can match it', function () {
    $response = $this->postJson(route('api.v1.documents.store'), notificationPayload())->assertCreated();

    expect(AiDocument::query()->findOrFail($response->json('id'))->content_hash)->not->toBeNull();
});
