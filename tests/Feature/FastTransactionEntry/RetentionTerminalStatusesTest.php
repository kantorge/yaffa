<?php

use App\Mail\AiDocumentsAwaitingAction;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\Transaction;
use App\Models\TransactionOrigin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesTestTransactions;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
    AiUserSettings::factory()->create(['user_id' => $this->user->id, 'document_retention_days' => 90]);

    $this->oldDocument = fn (string $status) => AiDocument::factory()->for($this->user)->create([
        'status' => $status,
        'created_at' => now()->subDays(100),
        'updated_at' => now()->subDays(100),
    ]);
});

it('deletes every terminal status after the retention period', function (string $status) {
    $document = ($this->oldDocument)($status);

    $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

    expect(AiDocument::find($document->id))->toBeNull();
    Mail::assertNothingSent();
})->with(['finalized', 'auto_recorded', 'duplicate', 'dismissed']);

it('keeps and reminds about the open statuses', function (string $status) {
    $document = ($this->oldDocument)($status);

    $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

    expect(AiDocument::find($document->id))->not->toBeNull();
    Mail::assertSent(AiDocumentsAwaitingAction::class);
})->with(['ready_for_review', 'awaiting_itemization', 'processing_failed', 'processing', 'ready_for_processing']);

it('keeps the created origin without its document, deletes the link rows, and never deletes the transaction', function () {
    $transaction = $this->createStandardTransaction(
        $this->user,
        AccountEntity::factory()->asAccount($this->user)->create()->id,
        AccountEntity::factory()->asPayee($this->user)->create()->id,
        10,
        '2026-03-01'
    );
    $document = ($this->oldDocument)('auto_recorded');
    $transaction->forceFill(['ai_document_id' => $document->id])->save();
    $created = TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_CREATED, $document, 'reason');
    TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_DUPLICATE_OF, $document);
    TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_CONFLICTS_WITH, $document);

    $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

    expect(AiDocument::find($document->id))->toBeNull()
        ->and(TransactionOrigin::count())->toBe(1)
        ->and($created->fresh()->origin_id)->toBeNull()
        ->and($created->fresh()->decision_reason)->toBe('reason')
        ->and(Transaction::find($transaction->id)->ai_document_id)->toBeNull();
});
