<?php

namespace App\Services\AutoRecord;

use App\Enums\AiDocumentStatus;
use App\Models\AiDocument;
use App\Models\Transaction;
use App\Models\TransactionOrigin;
use App\Models\TransactionSchedule;
use App\Services\Import\ImportDuplicateDetectionService;
use App\Services\ProcessingHistoryRecorder;
use App\Services\TransactionCreationService;

/**
 * Turns a document that passed every gate into a normal transaction. Goes through TransactionCreationService,
 * so TransactionCreated fires like for a manual entry. No CategoryLearning is written: nobody confirmed the
 * category. Callers run it under the per-user decision lock and inside a DB transaction.
 *
 * When exactly one active schedule of the payee is due around the document's date (same window and amount
 * tolerance as duplicate detection), its occurrence is skipped, as the recorded transaction replaces it.
 */
class AutoRecorder
{
    public const string STEP_SCHEDULE_SKIP = 'schedule_skip';

    public function __construct(
        private readonly TransactionCreationService $creationService,
        private readonly ImportDuplicateDetectionService $scheduleMatcher,
        private readonly ProcessingHistoryRecorder $historyRecorder,
    ) {
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

        $reasons = $result->reasons;
        $skipNote = $this->skipMatchingSchedule($document, $result);
        if ($skipNote !== null) {
            $reasons[] = $skipNote;
        }

        TransactionOrigin::record(
            $document->user,
            $transaction,
            TransactionOrigin::RELATION_CREATED,
            $document,
            __('Recorded automatically: :reasons', ['reasons' => implode(' ', $reasons)]),
        );

        $document->transitionTo(AiDocumentStatus::AutoRecorded);

        return $transaction;
    }

    /**
     * Skips the next occurrence of the one schedule this document stands in for, and logs the decision in the
     * document's processing history. Ambiguous matches are left alone: skipping is not undoable.
     *
     * @return string|null The note for the origin reason, or null when no schedule was involved
     */
    private function skipMatchingSchedule(AiDocument $document, GateResult $result): ?string
    {
        $draft = $result->draft;
        $candidates = $this->scheduleMatcher->strictScheduleCandidates($document->user, [
            'date' => $draft['date'],
            'amount' => (string) $draft['config']['amount_from'],
            'config_type' => 'standard',
            'transaction_type' => $draft['transaction_type'],
            'matched_payee' => ['id' => $result->payee->id],
        ]);

        if ($candidates === []) {
            return null;
        }

        $context = ['payee' => $result->payee->name, 'document_date' => $draft['date'], 'candidates' => count($candidates)];

        if (count($candidates) > 1) {
            $this->historyRecorder->appendLocalProcessingHistory($document, self::STEP_SCHEDULE_SKIP, $context, [
                'outcome' => 'Not skipped, more than one scheduled transaction matches',
            ]);

            return __('Several scheduled transactions match, none was skipped.');
        }

        $transactionId = $candidates[0]['transaction_id'];
        $schedule = TransactionSchedule::query()->where('transaction_id', $transactionId)->firstOrFail();
        $from = $schedule->next_date->toDateString();
        $skipped = $schedule->skipNextInstance();

        $this->historyRecorder->appendLocalProcessingHistory($document, self::STEP_SCHEDULE_SKIP, $context + [
            'scheduled_transaction_id' => $transactionId,
            'next_date' => $from,
        ], [
            'outcome' => $skipped ? 'Next occurrence skipped' : 'Skipping failed',
            'new_next_date' => $schedule->next_date?->toDateString() ?? 'none',
        ]);

        return $skipped
            ? __('Scheduled transaction #:id skipped (next date :from).', ['id' => $transactionId, 'from' => $from])
            : null;
    }
}
