<?php

namespace App\Services;

use App\Components\FlashMessages;
use App\Enums\AiDocumentStatus;
use App\Events\TransactionCreated;
use App\Events\TransactionUpdated;
use App\Models\AiDocument;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Recurr\Exception\InvalidArgument;
use Recurr\Exception\InvalidWeekday;
use RuntimeException;

/**
 * Creates a standard or investment transaction from validated TransactionRequest data, including its
 * config, items, schedule, source schedule updates and AI document finalization, and dispatches
 * TransactionCreated. Every path that creates a transaction should go through here, so the event (and
 * with it currency_id, cashflow_value and the summaries) is never skipped.
 */
class TransactionCreationService
{
    use FlashMessages;

    public function __construct(
        private TransactionItemMergeService $mergeService,
    ) {
    }

    /**
     * @param 'standard'|'investment' $configType
     *
     * @return array{transaction: Transaction, category_learning_summary: ?array}
     *
     * @throws ValidationException
     */
    public function create(string $configType, array $validated, User $user): array
    {
        $transaction = DB::transaction(function () use ($configType, $validated, $user): Transaction {
            // Claim the AI document first, so its row lock is held until the transaction is committed
            $this->markAiDocumentFinalized($validated, $user);

            // Create the configuration first
            $transactionDetails = $configType === 'investment'
                ? TransactionDetailInvestment::create($validated['config'])
                : TransactionDetailStandard::create($validated['config']);

            $transaction = new Transaction($validated);
            $transaction->user_id = $user->id;
            $transaction->config()->associate($transactionDetails);
            $transaction->push();

            if ($configType === 'standard') {
                $transactionItems = $this->createItems($validated, $transaction->id, $user);
                $transaction->transactionItems()->saveMany($transactionItems);
                $transaction->push();
            }

            if ($transaction->schedule) {
                $transactionSchedule = new TransactionSchedule(['transaction_id' => $transaction->id]);
                $transactionSchedule->fill($validated['schedule_config']);
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            // Runs in the same transaction as the transaction/schedule creation above,
            // so a failed catch-up (see handleSourceTransactionUpdates()) rolls back
            // the newly created transaction too, rather than leaving it committed
            // alongside a source schedule that never actually caught up.
            $this->handleSourceTransactionUpdates($validated, $user);

            return $transaction;
        });

        if ($configType === 'standard') {
            $this->mergeService->mergeIfEnabled($transaction);
        }

        $categoryLearningSummary = $this->finalizeAiDocument($validated, $transaction, $user);

        if (! empty($validated['transaction_template_id'])) {
            $user->transactionTemplates()->find($validated['transaction_template_id'])?->recordUse();
        }

        event(new TransactionCreated($transaction));

        return [
            'transaction' => $transaction,
            'category_learning_summary' => $categoryLearningSummary,
        ];
    }

    /**
     * Create the items of a standard transaction from validated `items`, plus the remaining payee
     * default amount as an extra item, if present. Shared with the update path. Tags are looked up and
     * created among the owner's own tags only, so another user's tag ID can never be attached.
     *
     * @return TransactionItem[]
     */
    public function createItems(array $validated, int $transactionId, User $owner): array
    {
        $transactionItems = [];
        foreach ($validated['items'] as $item) {
            // Ignore item, if amount is missing
            if (!array_key_exists('amount', $item) || $item['amount'] === null) {
                continue;
            }

            $newItem = TransactionItem::create(
                array_merge(
                    $item,
                    ['transaction_id' => $transactionId]
                )
            );

            // Create new tags and attach any tags
            if (array_key_exists('tags', $item)) {
                foreach ($item['tags'] as $tag) {
                    $newTag = $owner->tags()->firstOrCreate(
                        ['id' => $tag],
                        ['name' => $tag]
                    );

                    // Confirm to user if item was currently created
                    if ($newTag->wasRecentlyCreated) {
                        self::addMessage('Tag added (' . $newTag->name . ')', 'success', '', '', true);
                    }

                    $newItem->tags()->attach($newTag);
                }
            }

            $transactionItems[] = $newItem;
        }

        // Handle default payee amount, if present, by adding amount as an item
        if (array_key_exists('remaining_payee_default_amount', $validated)
            && $validated['remaining_payee_default_amount'] > 0) {
            $transactionItems[] = TransactionItem::create([
                'transaction_id' => $transactionId,
                'amount' => $validated['remaining_payee_default_amount'],
                'category_id' => $validated['remaining_payee_default_category_id'],
            ]);
        }

        return $transactionItems;
    }

    /**
     * Handle additional updates to a source transaction
     */
    private function handleSourceTransactionUpdates(array $validated, User $user): void
    {
        // Adjust source transaction schedule, if entering schedule instance
        // The reference is passed as the ID
        if ($validated['action'] === 'enter') {
            $sourceTransaction = Transaction::query()
                ->where('id', $validated['id'])
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->load(['transactionSchedule']);

            Gate::forUser($user)->authorize('update', $sourceTransaction);

            $originalScheduleConfig = $sourceTransaction->transactionSchedule->attributesToArray();

            if ($validated['catch_up_schedule'] ?? false) {
                if (!$sourceTransaction->transactionSchedule->catchUpToDate()) {
                    throw new RuntimeException(__('Unable to catch up the schedule to the current date.'));
                }
            } else {
                $sourceTransaction->transactionSchedule->skipNextInstance();
            }

            // This also triggers a TransactionUpdated event for the source transaction
            event(new TransactionUpdated($sourceTransaction, [
                'schedule_config' => $originalScheduleConfig,
            ]));

            return;
        }

        // Adjust source transaction schedule, if creating a new schedule clone
        if ($validated['action'] === 'replace') {
            $sourceTransaction = Transaction::query()
                ->where('id', $validated['id'])
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->load(['transactionSchedule']);

            Gate::forUser($user)->authorize('update', $sourceTransaction);

            $originalScheduleConfig = $sourceTransaction->transactionSchedule->attributesToArray();

            $sourceTransaction->transactionSchedule->fill($validated['original_schedule_config']);

            // next_date isn't necessarily present in original_schedule_config (the
            // "close out the old schedule" flow always omits/nulls it), so a stale
            // value from before this pattern change can survive the fill() above.
            // Since next_date is trusted verbatim wherever a transaction is recorded
            // (see TransactionSchedule::occursOn()), clear it here if it no longer
            // matches the (possibly just-changed) recurrence rule.
            $nextDate = $sourceTransaction->transactionSchedule->next_date;
            if ($nextDate) {
                try {
                    if (!$sourceTransaction->transactionSchedule->occursOn($nextDate)) {
                        $sourceTransaction->transactionSchedule->next_date = null;
                    }
                } catch (InvalidArgument|InvalidWeekday|Exception) {
                    $sourceTransaction->transactionSchedule->next_date = null;
                }
            }

            $sourceTransaction->push();

            // This also triggers a TransactionUpdated event for the source transaction
            event(new TransactionUpdated($sourceTransaction, [
                'schedule_config' => $originalScheduleConfig,
            ]));
        }
    }

    /**
     * Lock the AI document being finalized and mark it finalized. Must run inside the DB transaction
     * that creates the transaction: a concurrent finalize or reprocess of the same document waits for
     * the lock, and a document that is no longer ready for review rolls the whole creation back, so it
     * can never produce a second transaction or end up finalized after being reset for reprocessing.
     *
     * @throws ValidationException
     */
    private function markAiDocumentFinalized(array $validated, User $user): void
    {
        if (($validated['action'] ?? null) !== 'finalize' || empty($validated['ai_document_id'] ?? null)) {
            return;
        }

        $aiDocument = AiDocument::query()
            ->whereKey($validated['ai_document_id'])
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        // Deleted since validation; finalizeAiDocument() skips it the same way
        if (! $aiDocument) {
            return;
        }

        if ($aiDocument->status !== AiDocumentStatus::ReadyForReview->value) {
            throw ValidationException::withMessages([
                'ai_document_id' => __('Document cannot be finalized from current status'),
            ]);
        }

        $aiDocument->status = AiDocumentStatus::Finalized->value;
        if (! $aiDocument->processed_at) {
            $aiDocument->processed_at = now();
        }
        $aiDocument->save();
    }

    /**
     * Link the AI document finalized by markAiDocumentFinalized() to the created transaction, and
     * update category learning from the accepted recommendations.
     */
    private function finalizeAiDocument(array $validated, Transaction $transaction, User $user): ?array
    {
        if (($validated['action'] ?? null) !== 'finalize' || empty($validated['ai_document_id'] ?? null)) {
            Log::debug('Skipping AI document finalization due to missing or invalid action or AI document ID', [
                'action' => $validated['action'] ?? null,
                'ai_document_id' => $validated['ai_document_id'] ?? null,
            ]);
            return null;
        }

        $aiDocument = AiDocument::query()
            ->where('id', $validated['ai_document_id'])
            ->where('user_id', $user->id)
            ->first();

        // Silently return if the AI document is not found
        if (! $aiDocument) {
            Log::debug('AI document not found for finalization', [
                'ai_document_id' => $validated['ai_document_id'],
                'user_id' => $user->id,
            ]);
            return null;
        }

        if ($transaction->ai_document_id !== $aiDocument->id) {
            $transaction->ai_document_id = $aiDocument->id;
            // The update of the reference should not trigger update-based events
            $transaction->saveQuietly();
        }

        // Update CategoryLearning for accepted recommendations if there are any
        if (! empty($validated['items']) && is_array($validated['items'])) {
            return $this->updateCategoryLearning($transaction, $user, $validated['items']);
        }

        return [
            'created' => 0,
            'incremented' => 0,
            'updated' => 0,
        ];
    }

    /**
     * Update CategoryLearning from user-submitted transaction items.
     */
    private function updateCategoryLearning(
        Transaction $transaction,
        User $user,
        array $submittedItems = []
    ): array {
        $summary = [
            'created' => 0,
            'incremented' => 0,
            'updated' => 0,
        ];

        // Only applicable for standard transactions with items
        if ($transaction->config_type !== 'standard') {
            return $summary;
        }

        $learningService = new CategoryLearningService($user);

        // Process each submitted item where learning is enabled
        foreach ($submittedItems as $submittedItem) {
            // Learning is enabled by default, skip only if explicitly disabled
            if (! ($submittedItem['learnRecommendation'] ?? true)) {
                continue;
            }

            $categoryId = $submittedItem['category_id'] ?? null;
            $description = $submittedItem['description'] ?? null;

            // Need both category and description to learn
            if (! $categoryId || ! $description) {
                continue;
            }

            // Use service method to record the learning
            $result = $learningService->recordCategorySelection($description, (int) $categoryId);

            if (array_key_exists($result, $summary)) {
                $summary[$result]++;
            }
        }

        return $summary;
    }
}
