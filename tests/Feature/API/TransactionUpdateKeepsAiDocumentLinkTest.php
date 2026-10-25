<?php

use App\Models\AiDocument;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('keeps the AI document link when a transaction is edited', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $document = AiDocument::factory()->create(['user_id' => $user->id, 'status' => 'finalized']);
    $transaction = Transaction::factory()
        ->withdrawal($user)
        ->create(['user_id' => $user->id, 'ai_document_id' => $document->id]);
    $transaction->loadMissing(['config', 'transactionItems']);

    $this->patchJson(route('api.v1.transactions.update-standard', $transaction), [
        'action' => 'edit',
        // The edit form always posts a null document id
        'ai_document_id' => null,
        'transaction_type' => $transaction->transaction_type->value,
        'config_type' => $transaction->config_type,
        'date' => $transaction->date?->format('Y-m-d'),
        'comment' => 'edited',
        'reconciled' => $transaction->reconciled,
        'schedule' => $transaction->schedule,
        'config' => [
            'account_from_id' => $transaction->config->account_from_id,
            'account_to_id' => $transaction->config->account_to_id,
            'amount_from' => 10,
            'amount_to' => 10,
        ],
        'items' => $transaction->transactionItems->map(fn ($item) => [
            'amount' => 10,
            'category_id' => $item->category_id,
            'comment' => $item->comment,
            'tags' => [],
        ])->values()->all(),
    ])->assertOk();

    expect($transaction->fresh())
        ->comment->toBe('edited')
        ->ai_document_id->toBe($document->id);
});
