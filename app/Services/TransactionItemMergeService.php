<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionItemMergeService
{
    private const AMOUNT_COMPARISON_EPSILON = 0.0001;

    /**
     * Merge transaction items for a given transaction if the user's
     * auto_merge_standard_transaction_items setting is enabled.
     *
     * Only processes standard transactions that are not schedules or budgets.
     */
    public function mergeIfEnabled(Transaction $transaction): void
    {
        $transaction->loadMissing('user');

        if (! $transaction->user->auto_merge_standard_transaction_items) {
            return;
        }

        if (! $this->isMergeCandidate($transaction)) {
            return;
        }

        $this->mergeTransactionItems($transaction);
    }

    /**
     * Merge transaction items unconditionally (for legacy cleanup commands).
     *
     * Items are mergeable when they share the same category_id, the same set of
     * tags (or both have no tags), and have an empty comment. The amounts of
     * mergeable items are summed into a single item.
     *
     * Before persisting any changes the method validates that the sum of all
     * item amounts is preserved.
     *
     * @return int The number of duplicate items that were removed.
     */
    public function mergeTransactionItems(Transaction $transaction): int
    {
        if (! $this->isMergeCandidate($transaction)) {
            return 0;
        }

        $transaction->load(['transactionItems', 'transactionItems.tags']);

        $items = $transaction->transactionItems;

        if ($items->count() <= 1) {
            return 0;
        }

        // Separate items eligible for merging (empty comment) from those that must stay untouched
        $mergeable = $items->filter(fn ($item) => empty($item->comment));
        $nonMergeable = $items->filter(fn ($item) => ! empty($item->comment));

        if ($mergeable->count() <= 1) {
            return 0;
        }

        // Build a merge key: category_id + pipe + sorted comma-separated tag IDs
        $groups = [];
        foreach ($mergeable as $item) {
            $tagIds = $item->tags->pluck('id')->sort()->values()->implode(',');
            $key = $item->category_id . '|' . $tagIds;
            $groups[$key][] = $item;
        }

        // Identify groups with more than one item that can actually be reduced
        $hasWork = false;
        foreach ($groups as $groupItems) {
            if (count($groupItems) > 1) {
                $hasWork = true;
                break;
            }
        }

        if (! $hasWork) {
            return 0;
        }

        // Validate amount preservation before making any changes
        $originalTotal = $items->sum('amount');

        $newTotal = 0.0;
        foreach ($groups as $groupItems) {
            $newTotal += array_sum(array_map(fn ($i) => (float) $i->amount, $groupItems));
        }
        $newTotal += $nonMergeable->sum('amount');

        if (abs($originalTotal - $newTotal) > self::AMOUNT_COMPARISON_EPSILON) {
            throw new RuntimeException(
                sprintf(
                    'Transaction item merge aborted: amount mismatch for transaction %d (original %.4f vs new %.4f).',
                    $transaction->id,
                    $originalTotal,
                    $newTotal,
                )
            );
        }

        // Apply changes inside a DB transaction
        $removedCount = 0;

        DB::transaction(function () use ($groups, &$removedCount): void {
            foreach ($groups as $groupItems) {
                if (count($groupItems) <= 1) {
                    continue;
                }

                $totalAmount = array_sum(
                    array_map(fn ($i) => (float) $i->amount, $groupItems)
                );

                // Keep the first item and update its amount
                $keepItem = $groupItems[0];
                $keepItem->amount = $totalAmount;
                $keepItem->save();

                // Delete the rest (detach tags first to avoid FK violations)
                for ($i = 1; $i < count($groupItems); $i++) {
                    $groupItems[$i]->tags()->detach();
                    $groupItems[$i]->delete();
                    $removedCount++;
                }
            }
        });

        return $removedCount;
    }

    /**
     * Determine whether a transaction's items are eligible for merging.
     */
    private function isMergeCandidate(Transaction $transaction): bool
    {
        return $transaction->isStandard()
            && ! $transaction->schedule
            && ! $transaction->budget;
    }
}
