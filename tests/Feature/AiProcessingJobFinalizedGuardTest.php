<?php

use App\Events\AiDocumentProcessedEvent;
use App\Events\AiDocumentProcessingFailedEvent;
use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
use App\Services\ProcessDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('leaves a finalized document untouched', function (bool $aiEnabled) {
    Event::fake([
        AiDocumentProcessedEvent::class,
        AiDocumentProcessingFailedEvent::class,
    ]);

    $user = User::factory()->create();
    AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => $aiEnabled]);

    $processedData = [
        'transaction_type' => 'withdrawal',
        'config_type' => 'standard',
        'config' => [],
        'transaction_items' => [],
    ];
    $document = AiDocument::factory()->for($user)->create([
        'status' => 'finalized',
        'processed_transaction_data' => $processedData,
    ]);

    $service = Mockery::mock(ProcessDocumentService::class);
    $service->shouldNotReceive('process');

    (new AiProcessingJob($document))->handle($service, app(AiUserSettingsResolver::class));

    $document->refresh();
    expect($document->status)->toBe('finalized')
        ->and($document->processed_transaction_data)->toEqual($processedData);

    Event::assertNotDispatched(AiDocumentProcessedEvent::class);
    Event::assertNotDispatched(AiDocumentProcessingFailedEvent::class);
})->with([
    'AI enabled' => true,
    'AI disabled' => false,
]);
