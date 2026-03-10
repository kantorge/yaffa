<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Investment;
use App\Models\TransactionDetailInvestment;
use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

class TransactionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
        ];
    }

    public function create(Request $request, string $type): View|RedirectResponse
    {
        /**
         * @get("/transactions/create/{type}")
         * @name("transaction.create")
         * @middlewares("web", "auth", "verified")
         */

        // Sanity check for necessary assets: account is needed for any transactions
        if (AccountEntity::query()->where('user_id', $request->user()->id)->accounts()->active()->count() === 0) {
            $this->addMessage(
                __('transaction.requirement.account'),
                'info',
                __('No accounts found'),
                'info-circle'
            );

            return to_route('account-entity.create', ['type' => 'account']);
        }

        // Sanity check: an investment is needed for investment transactions
        // (Note, we don't check that the investment is in the right currency etc. here,)
        if ($type === 'investment' && Investment::query()->where('user_id', $request->user()->id)->active()->count() === 0) {
            $this->addMessage(
                __('transaction.requirement.investment'),
                'info',
                __('No investments found'),
                'info-circle'
            );

            return to_route('investment.create');
        }

        return view('transactions.form', [
            'transaction' => null,
            'action' => 'create',
            'type' => $type,
        ]);
    }

    /**
     * Show the form with data of selected transaction
     * Actual behavior is controlled by action
     *
     * @throws AuthorizationException
     */
    public function openTransaction(Transaction $transaction, string $action): View
    {
        /**
         * @get("/transactions/{transaction}/{action}")
         * @name("transaction.open")
         * @middlewares("web", "auth", "verified")
         */

        // Authorize user for transaction
        Gate::authorize('view', $transaction);

        // Validate if action is supported
        $availableActions = ['clone', 'create', 'edit', 'enter', 'finalize', 'replace', 'show'];
        if (!in_array($action, $availableActions)) {
            abort(404);
        }

        // Load all relevant relations
        $transaction->loadDetails();
        $this->enrichTransactionItemNamesForDisplay($transaction, $transaction->user_id);

        // Show is routed to special view
        if ($action === 'show') {
            JavaScript::put([
                'transaction' => $transaction,
            ]);
            return view('transactions.show');
        }

        // Adjust date and schedule settings, if entering a recurring item
        if ($action === 'enter') {
            // Reset schedule and budget flags
            $transaction->schedule = false;
            $transaction->budget = false;

            // Date is next schedule date
            $transaction->date = $transaction->transactionSchedule->next_date;
        }

        // Pass transaction data to view as JavaScript object
        JavaScript::put([
            'transaction' => $transaction,
        ]);

        return view('transactions.form', [
            'transaction' => $transaction,
            'action' => $action,
            'type' => $transaction->config_type,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws AuthorizationException
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        /**
         * @delete("/transactions/{transaction}")
         * @name("transactions.destroy")
         * @middlewares("web", "auth", "verified")
         */

        // Authorize user for transaction
        Gate::authorize('forceDelete', $transaction);

        // Remove the transaction and its config
        $transaction->delete();
        $transaction->config()->delete();

        self::addMessage('Transaction #' . $transaction->id . ' deleted', 'success', '', '', true);

        return redirect()->back();
    }

    public function skipScheduleInstance(Transaction $transaction): RedirectResponse
    {
        /**
         * @patch("/transactions/{transaction}/skip")
         * @name("transactions.skipScheduleInstance")
         * @middlewares("web", "auth", "verified")
         */
        $transaction->transactionSchedule->skipNextInstance();
        self::addSimpleSuccessMessage(__('Transaction schedule instance skipped'));

        return redirect()->back();
    }

    public function createFromDraft(Request $request): View
    {
        /**
         * @post("/transactions/create-from-draft")
         * @name("transactions.createFromDraft")
         * @middlewares("web", "auth", "verified")
         */

        $transactionData = json_decode($request->input('transaction'), true) ?? [];
        $configType = $transactionData['config_type'] ?? 'standard';

        // Make a new transaction from the draft
        $transaction = new Transaction($transactionData);

        // Set the transaction type enum value
        $transaction->transaction_type = TransactionTypeEnum::tryFrom($transactionData['transaction_type']) ?? ($configType === 'investment' ? TransactionTypeEnum::BUY : TransactionTypeEnum::WITHDRAWAL);

        // Ensure that a config relation exists, even if it's empty
        if (! array_key_exists('config', $transactionData)) {
            $transactionData['config'] = [];
        }
        if ($configType === 'investment') {
            $transaction->setRelation('config', new TransactionDetailInvestment($transactionData['config']));
        } else {
            $transaction->setRelation('config', new TransactionDetailStandard($transactionData['config']));

            $transaction->setRelation(
                'transactionItems',
                $this->buildDraftTransactionItems($transactionData, $request->user()->id)
            );

            // Try to add relation for account and payee, if they exist
            if (($transactionData['config']['account_from_id'] ?? null) !== null) {
                $transaction->config->setRelation('account_from', AccountEntity::find($transactionData['config']['account_from_id']));
            }
            if (($transactionData['config']['account_to_id'] ?? null) !== null) {
                $transaction->config->setRelation('account_to', AccountEntity::find($transactionData['config']['account_to_id']));
            }
        }

        // Ensure that the transaction is basic
        $transaction->schedule = false;
        $transaction->budget = false;
        $transaction->reconciled = false;

        $aiDocumentId = $request->input('ai_document_id');

        return view('transactions.form', [
            'transaction' => $transaction,
            'action' => 'finalize',
            'type' => $configType === 'investment' ? 'investment' : 'standard',
            'ai_document_id' => $aiDocumentId,
        ]);
    }

    private function enrichTransactionItemNamesForDisplay(Transaction $transaction, int $userId): void
    {
        if (! $transaction->isStandard() || ! $transaction->relationLoaded('transactionItems')) {
            return;
        }

        $categoryIds = $transaction->transactionItems
            ->pluck('category_id')
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return;
        }

        $categoriesById = Category::query()
            ->with('parent')
            ->where('user_id', $userId)
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        $transaction->transactionItems->each(function (TransactionItem $item) use ($categoriesById): void {
            $category = $categoriesById->get($item->category_id);
            $item->setAttribute('category_full_name', $category?->full_name);
        });
    }

    /**
     * @param array<string, mixed> $transactionData
     *
     * @return Collection<int, TransactionItem>
     */
    private function buildDraftTransactionItems(array $transactionData, int $userId): Collection
    {
        $items = collect($transactionData['transaction_items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->values();

        if ($items->isEmpty()) {
            return collect();
        }

        $categoryIds = $items
            ->flatMap(fn (array $item): array => [
                $item['category_id'] ?? null,
                $item['recommended_category_id'] ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        $categoriesById = Category::query()
            ->with('parent')
            ->where('user_id', $userId)
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        return $items->map(function (array $itemData) use ($categoriesById): TransactionItem {
            $categoryId = $itemData['category_id'] ?? null;
            $recommendedCategoryId = $itemData['recommended_category_id'] ?? null;

            if (! array_key_exists('category_full_name', $itemData) || empty($itemData['category_full_name'])) {
                $itemData['category_full_name'] = $categoryId
                    ? $categoriesById->get($categoryId)?->full_name
                    : null;
            }

            if (! array_key_exists('recommended_category_full_name', $itemData) || empty($itemData['recommended_category_full_name'])) {
                $itemData['recommended_category_full_name'] = $recommendedCategoryId
                    ? $categoriesById->get($recommendedCategoryId)?->full_name
                    : null;
            }

            $transactionItem = new TransactionItem([
                'category_id' => $categoryId,
                'amount' => $itemData['amount'] ?? 0,
                'comment' => $itemData['comment'] ?? null,
            ]);

            // Preserve AI-context attributes so the standalone finalize form can render AI recommendation controls.
            $transactionItem->setAttribute('category_full_name', $itemData['category_full_name'] ?? null);
            $transactionItem->setAttribute('recommended_category_id', $recommendedCategoryId);
            $transactionItem->setAttribute('recommended_category_full_name', $itemData['recommended_category_full_name'] ?? null);
            $transactionItem->setAttribute('description', $itemData['description'] ?? null);
            $transactionItem->setAttribute('match_type', $itemData['match_type'] ?? null);
            $transactionItem->setAttribute('confidence_score', $itemData['confidence_score'] ?? null);

            return $transactionItem;
        });
    }
}
