<?php

use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();

    $this->user = User::factory()->create(['language' => 'en']);
    AiUserSettings::factory()->enabled()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user, ['*']);

    $this->processedData = [
        'transaction_type' => 'withdrawal',
        'config_type' => 'standard',
        'config' => [],
        'transaction_items' => [],
    ];
    $this->chatHistory = [
        [
            'timestamp' => now()->toIso8601String(),
            'step' => 'main_extraction',
            'prompt' => 'Prompt text',
            'response' => 'Raw response',
        ],
    ];

    $this->makeDocument = fn (string $status) => AiDocument::factory()->for($this->user)->create([
        'status' => $status,
        'custom_prompt' => 'Original prompt',
        'processed_transaction_data' => $this->processedData,
        'ai_chat_history' => $this->chatHistory,
        'processed_at' => now(),
    ]);
});

it('rejects reprocessing a finalized document', function () {
    $document = ($this->makeDocument)('finalized');

    $this
        ->postJson("/api/v1/documents/{$document->id}/reprocess")
        ->assertUnprocessable()
        ->assertJsonPath('error', 'Document cannot be reprocessed from current status');

    $document->refresh();
    expect($document->status)->toBe('finalized')
        ->and($document->processed_transaction_data)->toEqual($this->processedData)
        ->and($document->ai_chat_history)->toEqual($this->chatHistory)
        ->and($document->processed_at)->not->toBeNull();

    Bus::assertNotDispatched(AiProcessingJob::class);
});

it('rejects setting a finalized document back to ready_for_processing via PATCH without saving anything', function () {
    $document = ($this->makeDocument)('finalized');

    $this
        ->patchJson("/api/v1/documents/{$document->id}", [
            'status' => 'ready_for_processing',
            'custom_prompt' => 'Sneaky new prompt',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error', 'Document cannot be reprocessed from current status');

    $document->refresh();
    expect($document->status)->toBe('finalized')
        ->and($document->custom_prompt)->toBe('Original prompt')
        ->and($document->processed_transaction_data)->toEqual($this->processedData)
        ->and($document->ai_chat_history)->toEqual($this->chatHistory);
});

it('still allows reprocessing from a reprocessable status', function (string $status) {
    $document = ($this->makeDocument)($status);

    $this
        ->postJson("/api/v1/documents/{$document->id}/reprocess")
        ->assertOk()
        ->assertJsonPath('status', 'ready_for_processing');

    expect($document->refresh()->status)->toBe('ready_for_processing');
    Bus::assertDispatched(AiProcessingJob::class);
})->with(['ready_for_review', 'processing_failed']);

it('still allows setting a reprocessable document back to ready_for_processing via PATCH', function (string $status) {
    $document = ($this->makeDocument)($status);

    $this
        ->patchJson("/api/v1/documents/{$document->id}", [
            'status' => 'ready_for_processing',
            'custom_prompt' => 'New prompt',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'ready_for_processing')
        ->assertJsonPath('custom_prompt', 'New prompt');

    $document->refresh();
    expect($document->status)->toBe('ready_for_processing')
        ->and($document->custom_prompt)->toBe('New prompt');
})->with(['ready_for_review', 'processing_failed']);

it('still allows updating only the custom prompt of a finalized document', function () {
    $document = ($this->makeDocument)('finalized');

    $this
        ->patchJson("/api/v1/documents/{$document->id}", ['custom_prompt' => 'New prompt'])
        ->assertOk()
        ->assertJsonPath('status', 'finalized')
        ->assertJsonPath('custom_prompt', 'New prompt');

    $document->refresh();
    expect($document->status)->toBe('finalized')
        ->and($document->custom_prompt)->toBe('New prompt');
});
