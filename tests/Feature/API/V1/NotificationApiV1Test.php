<?php

use App\Events\AiDocumentProcessedEvent;
use App\Events\AiDocumentProcessingFailedEvent;
use App\Models\AiDocument;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('raises a generic database notification when a document is processed or fails', function () {
    $user = User::factory()->create();
    $document = AiDocument::factory()->for($user)->create();

    AiDocumentProcessedEvent::dispatch($document);
    AiDocumentProcessingFailedEvent::dispatch($document, 'boom', RuntimeException::class, 0);

    $types = $user->notifications()->get()->pluck('data.type')->all();
    expect($types)->toEqualCanonicalizing(['ai_document.ready_for_review', 'ai_document.processing_failed']);

    $data = $user->notifications()->first()->data;
    expect(array_keys($data))->toEqualCanonicalizing(['type', 'entity_type', 'entity_id', 'title']);
    expect($data['entity_id'])->toBe($document->id);
});

it('lists notifications since a timestamp and marks them read', function () {
    $user = User::factory()->create();
    $document = AiDocument::factory()->for($user)->create();
    AiDocumentProcessedEvent::dispatch($document);
    $this->travel(2)->minutes();
    $cutoff = now()->toIso8601String();
    $this->travel(1)->minutes();
    AiDocumentProcessedEvent::dispatch($document);

    $token = $user->createToken('device', ['*'])->plainTextToken;

    $all = $this->withToken($token)->getJson(route('api.v1.notifications.index'))->assertOk();
    expect($all->json('data'))->toHaveCount(2);

    $recent = $this->withToken($token)->getJson(route('api.v1.notifications.index', ['since' => $cutoff]))->assertOk();
    expect($recent->json('data'))->toHaveCount(1);

    $id = $recent->json('data.0.id');
    $this->withToken($token)->postJson(route('api.v1.notifications.read', $id))->assertNoContent();
    expect($user->unreadNotifications()->count())->toBe(1);

    $this->withToken($token)->postJson(route('api.v1.notifications.read-all'))->assertNoContent();
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('does not expose or modify another user\'s notifications', function () {
    $owner = User::factory()->create();
    AiDocumentProcessedEvent::dispatch(AiDocument::factory()->for($owner)->create());
    $id = $owner->notifications()->first()->id;

    $token = User::factory()->create()->createToken('device', ['*'])->plainTextToken;

    $this->withToken($token)->getJson(route('api.v1.notifications.index'))->assertOk()->assertJsonCount(0, 'data');
    $this->withToken($token)->postJson(route('api.v1.notifications.read', $id))->assertNotFound();
});

it('registers, replaces and removes a push endpoint bound to the device token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device', ['*']);
    $headers = ['Authorization' => 'Bearer ' . $token->plainTextToken];

    $this->postJson(route('api.v1.devices.store'), ['type' => 'unifiedpush', 'endpoint' => 'https://push.example/up/abc'], $headers)
        ->assertOk()->assertJsonPath('type', 'unifiedpush');
    $this->postJson(route('api.v1.devices.store'), ['type' => 'fcm', 'endpoint' => 'fcm-registration-token'], $headers)->assertOk();

    expect(Device::query()->count())->toBe(1);
    expect(Device::query()->first()->type)->toBe('fcm');

    $this->deleteJson(route('api.v1.devices.destroy'), [], $headers)->assertNoContent();
    expect(Device::query()->count())->toBe(0);
});

it('rejects a non-URL UnifiedPush endpoint', function () {
    $token = User::factory()->create()->createToken('device', ['*'])->plainTextToken;

    $this->withToken($token)->postJson(route('api.v1.devices.store'), ['type' => 'unifiedpush', 'endpoint' => 'not a url'])
        ->assertUnprocessable();
});

it('does not bind a push endpoint to a browser session', function () {
    $this->actingAs(User::factory()->create())->postJson(route('api.v1.devices.store'), ['type' => 'fcm', 'endpoint' => 'tok'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'TOKEN_REQUIRED');
});

it('removes the push endpoint when the device token is revoked', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device', ['*']);
    $this->withToken($token->plainTextToken)
        ->postJson(route('api.v1.devices.store'), ['type' => 'fcm', 'endpoint' => 'tok'])->assertOk();

    $token->accessToken->delete();

    expect(Device::query()->count())->toBe(0);
});
