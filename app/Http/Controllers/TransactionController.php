<?php

namespace App\Http\Controllers;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\TransactionDraftService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

#[Middleware('auth')]
#[Middleware('verified')]
class TransactionController extends Controller
{
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

            return to_route('investments.create');
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
    #[Authorize('view', 'transaction')]
    public function openTransaction(Transaction $transaction, string $action): View
    {
        /**
         * @get("/transactions/{transaction}/{action}")
         * @name("transaction.open")
         * @middlewares("web", "auth", "verified")
         */

        // Authorize user for transaction
        // Validate if action is supported
        $availableActions = ['clone', 'create', 'edit', 'enter', 'finalize', 'replace', 'show', 'template'];
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
            // Reset schedule flag
            $transaction->schedule = false;

            // Date is next schedule date
            $transaction->date = $transaction->transactionSchedule->next_date;
        }

        // Saving as a template: a template has no date and cannot be a schedule
        if ($action === 'template') {
            $transaction->date = null;
            $transaction->schedule = false;
            $transaction->reconciled = false;
            $transaction->setRelation('transactionSchedule', null);
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

    #[Authorize('update', 'transaction')]
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

    public function createFromDraft(Request $request, TransactionDraftService $draftService): View
    {
        /**
         * @post("/transactions/create-from-draft")
         * @name("transactions.createFromDraft")
         * @middlewares("web", "auth", "verified")
         */

        $transactionData = json_decode($request->input('transaction'), true) ?? [];
        $configType = $transactionData['config_type'] ?? 'standard';

        $transaction = $draftService->toUnsavedTransaction($transactionData, $request->user());

        $aiDocumentId = $request->input('ai_document_id');
        $templateId = $request->user()->transactionTemplates()->whereKey($request->input('transaction_template_id'))->value('id');

        return view('transactions.form', [
            'transaction' => $transaction,
            'action' => 'finalize',
            'type' => $configType === 'investment' ? 'investment' : 'standard',
            'ai_document_id' => $aiDocumentId,
            'transaction_template_id' => $templateId,
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
}
