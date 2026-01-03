<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
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

        // Sanity check for necessary assets: account is needed for any transactions
        if ($request->user()->accounts()->active()->count() === 0) {
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
        if ($type === 'investment' && $request->user()->investments()->active()->count() === 0) {
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
}
