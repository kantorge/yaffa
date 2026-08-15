<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionItemMergeService
{
    /** Matches transaction_items.amount's DECIMAL(12,4) scale. */
    private const AMOUNT_SCALE = 4;

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

        // Validate amount preservation before making any changes, using exact decimal
        // arithmetic on the raw DECIMAL strings (not the float-cast attribute) to avoid
        // reintroducing float drift into the very comparison meant to detect it.
        $originalTotal = $this->sumRawAmounts($items);

        // Cache each group's sum here so the merge step below can reuse it instead of
        // recomputing the same bcmath sum a second time.
        $groupSums = [];
        $newTotal = '0';
        foreach ($groups as $key => $groupItems) {
            $groupSums[$key] = $this->sumRawAmounts(collect($groupItems));
            $newTotal = bcadd($newTotal, $groupSums[$key], self::AMOUNT_SCALE);
        }
        $newTotal = bcadd($newTotal, $this->sumRawAmounts($nonMergeable), self::AMOUNT_SCALE);

        if (bccomp($originalTotal, $newTotal, self::AMOUNT_SCALE) !== 0) {
            throw new RuntimeException(
                sprintf(
                    'Transaction item merge aborted: amount mismatch for transaction %d (original %s vs new %s).',
                    $transaction->id,
                    $originalTotal,
                    $newTotal,
                )
            );
        }

        // Apply changes inside a DB transaction
        $removedCount = 0;

        DB::transaction(function () use ($groups, $groupSums, &$removedCount): void {
            foreach ($groups as $key => $groupItems) {
                if (count($groupItems) <= 1) {
                    continue;
                }

                // Keep the first item and update its amount, reusing the same exact
                // bcmath sum already used to validate amount preservation above.
                $keepItem = $groupItems[0];
                $keepItem->amount = $groupSums[$key];
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
     * Sum a collection of transaction items' raw (pre-float-cast) DECIMAL amounts
     * using bcmath, so the result is exact rather than IEEE-754-approximate.
     */
    private function sumRawAmounts(iterable $items): string
    {
        $sum = '0';
        foreach ($items as $item) {
            $sum = bcadd($sum, (string) $item->getRawOriginal('amount'), self::AMOUNT_SCALE);
        }

        return $sum;
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
