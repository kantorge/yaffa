<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;
use Exception;

class TransactionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
    }

    public function create(Request $request, string $type): View|RedirectResponse
    {
        /**
         * @get('/transactions/create/{type}
         * @name('transaction.create')
         * @middlewares('web', 'auth', 'verified')
         */

        // Sanity check for necessary assets
        if ($request->user()->accounts()->active()->count() === 0) {
            $this->addMessage(
                __('transaction.requirement.account'),
                'info',
                __('No accounts found'),
                'info-circle'
            );

            return redirect()->route('account-entity.create', ['type' => 'account']);
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
         * @get('/transactions/{transaction}/{action}')
         * @name('transaction.open')
         * @middlewares('web', 'auth', 'verified')
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
            'type' => $transaction->transactionType->type,
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
         * @delete('/transactions/{transaction}')
         * @name('transactions.destroy')
         * @middlewares('web', 'auth', 'verified')
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
         * @patch('/transactions/{transaction}/skip')
         * @name('transactions.skipScheduleInstance')
         * @middlewares('web', 'auth', 'verified')
         */
        $transaction->transactionSchedule->skipNextInstance();
        self::addSimpleSuccessMessage(__('Transaction schedule instance skipped'));

        return redirect()->back();
    }

    public function createFromDraft(Request $request): View
    {
        /**
         * @post('/transactions/create-from-draft')
         * @name('transactions.createFromDraft')
         * @middlewares('web', 'auth', 'verified')
         */

        $transactionData = json_decode($request->input('transaction'), true) ?? [];

        // Make a new transaction from the draft
        $transaction = new Transaction($transactionData);

        // Try to add relation for transaction type, if it exists
        try {
            $transaction->transaction_type = [
                'name' => $transactionData['transaction_type']['name'],
            ];
        } catch (Exception $e) {
            $transaction->transaction_type = [
                'name' => 'withdrawal',
            ];
        }

        // Ensure that a config relation exists, even if it's empty
        if (!array_key_exists('config', $transactionData)) {
            $transactionData['config'] = [];
        }
        $transaction->setRelation('config', new TransactionDetailStandard($transactionData['config']));

        // Try to add relation for account and payee, if they exist
        if ($transactionData['config']['account_from_id'] ?? null !== null) {
            $transaction->config->setRelation('account_from', AccountEntity::find($transactionData['config']['account_from_id']));
        }
        if ($transactionData['config']['account_to_id'] ?? null !== null) {
            $transaction->config->setRelation('account_to', AccountEntity::find($transactionData['config']['account_to_id']));
        }

        // Ensure that the transaction is basic
        $transaction->schedule = false;
        $transaction->budget = false;
        $transaction->reconciled = false;

        $sourceId = $request->input('mail_id');

        return view('transactions.form', [
            'transaction' => $transaction,
            'action' => 'finalize',
            'type' => 'standard',
            'source_id' => $sourceId,
        ]);
    }

    /**
     * Display batch entry form for investment transactions
     */
    public function batchEntryInvestment(AccountEntity $account): View|RedirectResponse
    {
        /**
         * @get('/account/{account}/batch-entry/investment')
         * @name('account.batch-entry.investment')
         * @middlewares('web', 'auth', 'verified')
         */

        // Verify the account belongs to the user
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        // Verify this is actually an account (not a payee)
        if ($account->config_type !== 'account') {
            $this->addMessage(
                __('This feature is only available for accounts'),
                'error',
                __('Invalid account type'),
                'exclamation-triangle'
            );
            return redirect()->route('account.history', $account);
        }

        // Get all investments currently held in this account with their quantities
        $investmentData = $account->config->getAssociatedInvestmentsAndQuantity();

        // Load full investment models with latest prices and last commission
        $investments = collect();
        foreach ($investmentData as $data) {
            if ($data->quantity > 0) {
                $investment = \App\Models\Investment::find($data->investment_id);
                if ($investment) {
                    $investment->current_quantity = $data->quantity;
                    $investment->latest_price = $investment->getLatestPrice();

                    // Get last commission for this investment in this account
                    $lastTransaction = Transaction::whereHasMorph(
                        'config',
                        [\App\Models\TransactionDetailInvestment::class],
                        function ($query) use ($account, $investment) {
                            $query->where('account_id', $account->id)
                                ->where('investment_id', $investment->id)
                                ->whereNotNull('commission');
                        }
                    )
                        ->where('schedule', false)
                        ->latest('date')
                        ->first();

                    $investment->last_commission = $lastTransaction?->config->commission ?? 0;

                    $investments->push($investment);
                }
            }
        }

        return view('transactions.batch-entry-investment', [
            'account' => $account,
            'investments' => $investments,
        ]);
    }

    /**
     * Store batch of investment transactions
     */
    public function storeBatchEntryInvestment(Request $request, AccountEntity $account): RedirectResponse
    {
        /**
         * @post('/account/{account}/batch-entry/investment')
         * @name('account.batch-entry.investment.store')
         * @middlewares('web', 'auth', 'verified')
         */

        // Verify the account belongs to the user
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'transactions' => 'required|array',
            'transactions.*.investment_id' => 'required|exists:investments,id',
            'transactions.*.quantity' => 'required|numeric',
            'transactions.*.price' => 'required|numeric|min:0',
            'transactions.*.commission' => 'nullable|numeric',
            'transactions.*.tax' => 'nullable|numeric',
            'transactions.*.transaction_type' => 'required|in:buy,sell',
        ]);

        $createdCount = 0;

        foreach ($validated['transactions'] as $transactionData) {
            // Skip if quantity is 0
            if ($transactionData['quantity'] === 0) {
                continue;
            }

            // Get transaction type
            $transactionType = \App\Models\TransactionType::where(
                'name',
                ucfirst($transactionData['transaction_type'])
            )->first();

            if (!$transactionType) {
                continue;
            }

            // Create transaction detail
            $transactionDetail = \App\Models\TransactionDetailInvestment::create([
                'account_id' => $account->id,
                'investment_id' => $transactionData['investment_id'],
                'quantity' => abs($transactionData['quantity']),
                'price' => $transactionData['price'],
                'commission' => $transactionData['commission'] ?? 0,
                'tax' => $transactionData['tax'] ?? 0,
            ]);

            // Calculate cashflow
            $cashflow = (abs($transactionData['quantity']) * $transactionData['price'])
                + ($transactionData['commission'] ?? 0)
                + ($transactionData['tax'] ?? 0);

            if ($transactionData['transaction_type'] === 'buy') {
                $cashflow = -$cashflow;
            }

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'date' => $validated['date'],
                'transaction_type_id' => $transactionType->id,
                'config_type' => 'investment',
                'config_id' => $transactionDetail->id,
                'schedule' => false,
                'budget' => false,
                'reconciled' => false,
                'cashflow_value' => $cashflow,
            ]);

            $createdCount++;
        }

        $this->addMessage(
            __('Created :count investment transactions', ['count' => $createdCount]),
            'success',
            __('Batch entry completed'),
            'check-circle'
        );

        return redirect()->route('account.history', $account);
    }

    /**
     * Display batch reconciliation form for investment account
     */
    public function batchReconcileInvestment(AccountEntity $account): View|RedirectResponse
    {
        /**
         * @get('/account/{account}/batch-reconcile/investment')
         * @name('account.batch-reconcile.investment')
         * @middlewares('web', 'auth', 'verified')
         */

        // Verify the account belongs to the user
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        // Verify this is actually an account (not a payee)
        if ($account->config_type !== 'account') {
            $this->addMessage(
                __('This feature is only available for accounts'),
                'error',
                __('Invalid account type'),
                'exclamation-triangle'
            );
            return redirect()->route('account.history', $account);
        }

        // Get all investments that have ever been in this account
        $investmentIds = \App\Models\TransactionDetailInvestment::where('account_id', $account->id)
            ->distinct()
            ->pluck('investment_id');

        $investments = \App\Models\Investment::whereIn('id', $investmentIds)
            ->get()
            ->map(function ($investment) use ($account) {
                $investment->current_quantity = $investment->getCurrentQuantityForAccount($account->id);
                $investment->latest_price = $investment->getLatestPrice();
                return $investment;
            });

        return view('transactions.batch-reconcile-investment', [
            'account' => $account,
            'investments' => $investments,
        ]);
    }

    /**
     * Store batch reconciliation adjustments
     */
    public function storeBatchReconcileInvestment(Request $request, AccountEntity $account): RedirectResponse
    {
        /**
         * @post('/account/{account}/batch-reconcile/investment')
         * @name('account.batch-reconcile.investment.store')
         * @middlewares('web', 'auth', 'verified')
         */

        // Verify the account belongs to the user
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'reconciliations' => 'required|array',
            'reconciliations.*.investment_id' => 'required|exists:investments,id',
            'reconciliations.*.current_quantity' => 'required|numeric',
            'reconciliations.*.statement_quantity' => 'required|numeric',
            'reconciliations.*.price' => 'nullable|numeric|min:0',
        ]);

        $adjustedCount = 0;
        $reconciledCount = 0;
        $pricesAdded = 0;

        foreach ($validated['reconciliations'] as $reconciliationData) {
            $investment = \App\Models\Investment::find($reconciliationData['investment_id']);
            if (!$investment) {
                continue;
            }

            $currentQty = $reconciliationData['current_quantity'];
            $statementQty = $reconciliationData['statement_quantity'];
            $price = $reconciliationData['price'] ?? 0;

            // Add price to history if provided
            if ($price > 0) {
                $existingPrice = \App\Models\InvestmentPrice::where('investment_id', $investment->id)
                    ->where('date', $validated['date'])
                    ->first();

                if (!$existingPrice) {
                    \App\Models\InvestmentPrice::create([
                        'investment_id' => $investment->id,
                        'date' => $validated['date'],
                        'price' => $price,
                    ]);
                    $pricesAdded++;
                }
            }

            // Check if quantities match
            if ($currentQty === $statementQty) {
                // Mark all Buy/Sell transactions as reconciled
                $updated = Transaction::whereHasMorph(
                    'config',
                    [\App\Models\TransactionDetailInvestment::class],
                    function ($query) use ($account, $investment) {
                        $query->where('account_id', $account->id)
                            ->where('investment_id', $investment->id);
                    }
                )
                    ->whereIn('transaction_type_id', [4, 5]) // Buy or Sell
                    ->where('date', '<=', $validated['date'])
                    ->where('reconciled', false)
                    ->update(['reconciled' => true]);

                if ($updated > 0) {
                    $reconciledCount++;
                }
            } else {
                // Create adjustment transaction
                $difference = $statementQty - $currentQty;
                $transactionTypeName = $difference > 0 ? 'Add shares' : 'Remove shares';

                $transactionType = \App\Models\TransactionType::where('name', $transactionTypeName)->first();
                if (!$transactionType) {
                    continue;
                }

                // Create transaction detail
                $transactionDetail = \App\Models\TransactionDetailInvestment::create([
                    'account_id' => $account->id,
                    'investment_id' => $investment->id,
                    'quantity' => abs($difference),
                    'price' => null,
                    'commission' => null,
                    'tax' => null,
                ]);

                // Create transaction
                Transaction::create([
                    'user_id' => Auth::id(),
                    'date' => $validated['date'],
                    'transaction_type_id' => $transactionType->id,
                    'config_type' => 'investment',
                    'config_id' => $transactionDetail->id,
                    'schedule' => false,
                    'budget' => false,
                    'reconciled' => false,
                    'cashflow_value' => 0,
                    'comment' => 'RECONCILE ERROR - TO CHECK',
                ]);

                $adjustedCount++;
            }
        }

        $messages = [];
        if ($reconciledCount > 0) {
            $messages[] = __('Reconciled :count investments', ['count' => $reconciledCount]);
        }
        if ($adjustedCount > 0) {
            $messages[] = __('Created :count adjustment transactions', ['count' => $adjustedCount]);
        }
        if ($pricesAdded > 0) {
            $messages[] = __('Added :count prices', ['count' => $pricesAdded]);
        }

        $this->addMessage(
            implode('. ', $messages),
            'success',
            __('Batch reconciliation completed'),
            'check-circle'
        );

        return redirect()->route('account.history', $account);
    }

    /**
     * Get quantities as of a specific date for batch reconciliation
     */
    public function getBatchReconcileQuantities(Request $request, AccountEntity $account)
    {
        /**
         * @post('/account/{account}/batch-reconcile/investment/quantities')
         * @name('account.batch-reconcile.investment.quantities')
         * @middlewares('web', 'auth', 'verified')
         */

        // Verify the account belongs to the user
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'investment_ids' => 'required|array',
            'investment_ids.*' => 'exists:investments,id',
        ]);

        $quantities = [];
        foreach ($validated['investment_ids'] as $investmentId) {
            $investment = \App\Models\Investment::find($investmentId);
            if ($investment) {
                $quantities[$investmentId] = $investment->getCurrentQuantityForAccount(
                    $account->id,
                    $validated['date']
                );
            }
        }

        return response()->json([
            'success' => true,
            'quantities' => $quantities,
        ]);
    }
}
