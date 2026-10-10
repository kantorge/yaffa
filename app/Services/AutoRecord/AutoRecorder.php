<?php

namespace App\Services\AutoRecord;

use App\Enums\AiDocumentStatus;
use App\Models\AiDocument;
use App\Models\Transaction;
use App\Models\TransactionOrigin;
use App\Services\TransactionCreationService;

/**
 * Turns a document that passed every gate into a normal transaction. Goes through TransactionCreationService,
 * so TransactionCreated fires like for a manual entry. No CategoryLearning is written: nobody confirmed the
 * category. Callers run it under the per-user decision lock and inside a DB transaction.
 */
class AutoRecorder
{
    public function __construct(private readonly TransactionCreationService $creationService)
    {
    }

    public function record(AiDocument $document, GateResult $result): Transaction
    {
        $draft = $result->draft;
        $isWithdrawal = $draft['transaction_type'] === 'withdrawal';
        $amount = $draft['config']['amount_from'];
        $payeeId = $result->payee->id;

        $templateDraft = $result->template?->draft;

        $created = $this->creationService->create('standard', [
            'action' => 'create',
            'config_type' => 'standard',
            'transaction_type' => $draft['transaction_type'],
            'date' => $draft['date'],
            'comment' => $templateDraft['comment'] ?? null,
            'reconciled' => false,
            'schedule' => false,
            'ai_document_id' => $document->id,
            'config' => [
                'account_from_id' => $isWithdrawal ? $result->accountId : $payeeId,
                'account_to_id' => $isWithdrawal ? $payeeId : $result->accountId,
                'amount_from' => $amount,
                'amount_to' => $amount,
            ],
            'items' => $result->items,
        ], $document->user);

        /** @var Transaction $transaction */
        $transaction = $created['transaction'];

        TransactionOrigin::record(
            $document->user,
            $transaction,
            TransactionOrigin::RELATION_CREATED,
            $document,
            __('Recorded automatically: :reasons', ['reasons' => implode(' ', $result->reasons)]),
        );

        $document->transitionTo(AiDocumentStatus::AutoRecorded);

        return $transaction;
    }
}
