<?php

use App\Events\AiDocumentProcessedEvent;
use App\Jobs\AiProcessingJob;
use App\Listeners\SendAiDocumentProcessedNotification;
use App\Mail\AiDocumentProcessed;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\AiProviderConfig;
use App\Models\AiUserSettings;
use App\Models\Transaction;
use App\Models\TransactionOrigin;
use App\Models\User;
use App\Services\AiExtractionSchemaValidator;
use App\Services\AiPromptBuilder;
use App\Services\CategoryLearningService;
use App\Services\PayeeCategoryStatsService;
use App\Services\ProcessDocumentService;
use App\Services\TextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTestTransactions;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create(['language' => 'en']);
    AiUserSettings::factory()->enabled()->create(['user_id' => $this->user->id]);
    AiProviderConfig::factory()->for($this->user)->create();
    $this->account = AccountEntity::factory()->asAccount($this->user)->create(['active' => true, 'name' => 'Visa card']);
    $this->payee = AccountEntity::factory()->asPayee($this->user)->create(['active' => true, 'name' => 'Parking Kft']);

    // Runs the real process() with the AI replaced by a fixed extraction payload
    $this->process = function (array $payload, array $attributes = []) {
        $document = AiDocument::factory()->for($this->user)->create([
            'status' => 'ready_for_processing',
            'source_type' => 'manual_upload',
        ] + $attributes);
        AiDocumentFile::factory()->create([
            'ai_document_id' => $document->id,
            'file_path' => 'ai_documents/test/input.txt',
            'file_name' => 'input.txt',
            'file_type' => 'txt',
        ]);

        $textExtractor = $this->createStub(TextExtractionService::class);
        $textExtractor->method('extractFromFile')->willReturn('Receipt text');

        $service = new class (
            $textExtractor,
            new CategoryLearningService(),
            $this->createStub(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder(),
        ) extends ProcessDocumentService {
            public array $payload = [];

            protected function requestMainExtractionPayload(AiProviderConfig $config, AiDocument $document, string $prompt): array
            {
                return $this->payload;
            }
        };
        $service->payload = $payload + [
            'transaction_type' => 'withdrawal',
            'account' => 'Visa card',
            'account_from' => null,
            'account_to' => null,
            'payee' => 'Parking Kft',
            'date' => '2026-03-01',
            'amount' => 1500,
            'currency' => 'HUF',
            'transaction_items' => [],
        ];

        $service->process($document);

        return $document->fresh();
    };
});

it('stores the document kind and moves the document to review when nothing matches', function () {
    $document = ($this->process)(['document_kind' => 'bank_notification', 'transaction_time' => '10:30:45']);

    expect($document->status)->toBe('ready_for_review')
        ->and($document->document_kind)->toBe('bank_notification')
        ->and($document->processed_transaction_data['raw']['transaction_time'])->toBe('10:30')
        ->and($document->status_changed_at)->not->toBeNull();
});

it('drops unusable optional fields instead of failing the extraction', function () {
    $document = ($this->process)(['document_kind' => 'something else', 'transaction_time' => 'noon', 'card_last_digits' => 1234]);

    expect($document->status)->toBe('ready_for_review')
        ->and($document->document_kind)->toBeNull()
        ->and($document->processed_transaction_data['raw']['transaction_time'])->toBeNull()
        ->and($document->processed_transaction_data['raw']['card_last_digits'])->toBe('1234');
});

it('closes a document with the same content as a duplicate, silently', function () {
    Mail::fake();
    AiDocument::factory()->for($this->user)->finalized()->create(['content_hash' => str_repeat('b', 64)]);

    $document = ($this->process)([], ['content_hash' => str_repeat('b', 64)]);

    expect($document->status)->toBe('duplicate');
    expect(TransactionOrigin::count())->toBe(0);

    (new SendAiDocumentProcessedNotification())->handle(new AiDocumentProcessedEvent($document));
    Mail::assertNothingSent();
});

it('sends the processed notification for a document waiting for review', function () {
    Mail::fake();
    $document = ($this->process)([]);

    (new SendAiDocumentProcessedNotification())->handle(new AiDocumentProcessedEvent($document));

    Mail::assertSent(AiDocumentProcessed::class);
});

it('closes a notification of a purchase already recorded, linking it and leaving the transaction alone', function () {
    $transaction = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 1500, '2026-03-02');
    $before = $transaction->fresh()->toArray();

    $document = ($this->process)(['document_kind' => 'bank_notification']);

    expect($document->status)->toBe('duplicate');
    $origin = TransactionOrigin::sole();
    expect($origin->transaction_id)->toBe($transaction->id)
        ->and($origin->relation)->toBe('duplicate_of')
        ->and($origin->origin_type)->toBe('ai_document')
        ->and($origin->origin_id)->toBe($document->id)
        ->and($origin->user_id)->toBe($this->user->id)
        ->and($transaction->fresh()->toArray())->toEqual($before);
});

it('keeps the second parking notification of a day in review', function () {
    $first = ($this->process)(['document_kind' => 'bank_notification', 'transaction_time' => '10:00']);
    $transaction = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 1500, '2026-03-01');
    $transaction->forceFill(['ai_document_id' => $first->id])->save();

    $second = ($this->process)(['document_kind' => 'bank_notification', 'transaction_time' => '12:00']);

    expect($second->status)->toBe('ready_for_review')
        ->and(TransactionOrigin::count())->toBe(0);
});

it('keeps a same-event match with another open document in review', function () {
    $first = ($this->process)(['document_kind' => 'bank_notification', 'transaction_time' => '10:00']);
    $second = ($this->process)(['document_kind' => 'bank_notification', 'transaction_time' => '10:04']);

    expect($first->status)->toBe('ready_for_review')
        ->and($second->status)->toBe('ready_for_review');
});

it('keeps a near match with a different amount in review', function () {
    $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 1500, '2026-03-01');

    $document = ($this->process)(['amount' => 1425]);

    expect($document->status)->toBe('ready_for_review');
});

it('computes the same content hash for the same text, and a different one for other text', function () {
    Storage::fake('local');
    Bus::fake();
    Sanctum::actingAs($this->user, ['*']);

    $upload = fn (string $text) => AiDocument::find(
        $this->postJson('/api/v1/documents', ['text_input' => $text])->assertCreated()->json('id')
    );

    $first = $upload('same content');

    expect($first->content_hash)->toBe(AiDocument::hashFiles([hash('sha256', 'same content')]))
        ->and($upload('same content')->content_hash)->toBe($first->content_hash)
        ->and($upload('other content')->content_hash)->not->toBe($first->content_hash);
});

it('dismisses a document waiting for review or failed, and rejects other statuses', function () {
    Sanctum::actingAs($this->user, ['*']);

    foreach (['ready_for_review', 'processing_failed'] as $status) {
        $document = AiDocument::factory()->for($this->user)->create(['status' => $status]);

        $this->postJson("/api/v1/documents/{$document->id}/dismiss")->assertOk()->assertJsonPath('status', 'dismissed');
        expect($document->fresh()->status)->toBe('dismissed');
    }

    foreach (['finalized', 'duplicate', 'auto_recorded', 'dismissed', 'processing', 'ready_for_processing', 'awaiting_itemization'] as $status) {
        $document = AiDocument::factory()->for($this->user)->create(['status' => $status]);

        $this->postJson("/api/v1/documents/{$document->id}/dismiss")->assertUnprocessable();
        expect($document->fresh()->status)->toBe($status);
    }
});

it('does not let one user dismiss another user\'s document', function () {
    $document = AiDocument::factory()->create(['status' => 'ready_for_review']);
    Sanctum::actingAs($this->user, ['*']);

    $this->postJson("/api/v1/documents/{$document->id}/dismiss")->assertForbidden();
});

it('reprocesses a dismissed document, but not a duplicate or an auto-recorded one', function () {
    Bus::fake();
    Sanctum::actingAs($this->user, ['*']);

    $dismissed = AiDocument::factory()->for($this->user)->dismissed()->create();
    $this->postJson("/api/v1/documents/{$dismissed->id}/reprocess")->assertOk();
    expect($dismissed->fresh()->status)->toBe('ready_for_processing');
    Bus::assertDispatched(AiProcessingJob::class);

    foreach ([AiDocument::factory()->for($this->user)->duplicate()->create(), AiDocument::factory()->for($this->user)->autoRecorded()->create()] as $document) {
        $this->postJson("/api/v1/documents/{$document->id}/reprocess")->assertUnprocessable();
        $this->patchJson("/api/v1/documents/{$document->id}", ['status' => 'ready_for_processing'])->assertUnprocessable();
        expect($document->fresh()->status)->not->toBe('ready_for_processing');
    }
});

it('skips the processing job for a document linked to a transaction', function () {
    foreach (['duplicate', 'auto_recorded'] as $status) {
        $document = AiDocument::factory()->for($this->user)->create(['status' => $status]);

        (new AiProcessingJob($document))->handle(
            Mockery::mock(ProcessDocumentService::class)->shouldNotReceive('process')->getMock(),
            app(App\Services\AiUserSettingsResolver::class)
        );

        expect($document->fresh()->status)->toBe($status);
    }
});

it('counts only documents that are not in a terminal status in the summary', function () {
    Sanctum::actingAs($this->user, ['*']);
    foreach (['ready_for_review', 'processing_failed', 'finalized', 'duplicate', 'dismissed', 'auto_recorded'] as $status) {
        AiDocument::factory()->for($this->user)->create(['status' => $status]);
    }

    $this->getJson('/api/v1/documents/summary')
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('ready_for_review', 1)
        ->assertJsonPath('processing_failed', 1);
});

it('keeps an AiDocument\'s created origin without it and deletes the link rows', function () {
    $document = AiDocument::factory()->for($this->user)->create();
    $transaction = $this->createStandardTransaction($this->user, $this->account->id, $this->payee->id, 10, '2026-03-01');
    $created = TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_CREATED, $document, 'why');
    TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_DUPLICATE_OF, $document);
    TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_CONFLICTS_WITH, $document);

    $document->delete();

    expect(TransactionOrigin::count())->toBe(1);
    $created->refresh();
    expect($created->origin_id)->toBeNull()
        ->and($created->origin_type)->toBe('ai_document')
        ->and($created->decision_reason)->toBe('why');
    expect(Transaction::find($transaction->id))->not->toBeNull();
});
