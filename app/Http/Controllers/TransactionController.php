<?php

namespace App\Http\Controllers;

use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;
use Exception;

class TransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function create(string $type): View|RedirectResponse
    {
        /**
         * @get('/transactions/create/{type}
         * @name('transaction.create')
         * @middlewares('web', 'auth', 'verified')
         */

        // Sanity check for necessary assets
        if (AccountEntity::active()->accounts()->count() === 0) {
            $this->addMessage(
                __('Before creating a transaction, please add at least one account. This can be a bank account, a wallet, etc.'),
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
     * @param Transaction $transaction
     * @param string $action
     * @return View
     */
    public function openTransaction(Transaction $transaction, string $action): View
    {
        /**
         * @get('/transactions/{transaction}/{action}')
         * @name('transaction.open')
         * @middlewares('web', 'auth', 'verified')
         */

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
     * @param Transaction $transaction
     * @return RedirectResponse
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        /**
         * @delete('/transactions/{transaction}')
         * @name('transactions.destroy')
         * @middlewares('web', 'auth', 'verified')
         */
        $transaction->delete();

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

    public function createFromDraft(Request $request)
    {
        /**
         * @post('/transactions/create-from-draft')
         * @name('transactions.createFromDraft')
         * @middlewares('web', 'auth', 'verified')
         */

        $transactionData = json_decode($request->input('transaction'), true);

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
        if (! array_key_exists('config', $transactionData)) {
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

        return view('transactions.form', [
            'transaction' => $transaction,
            'action' => 'finalize',
            'type' => 'standard', // TODO: Make this dynamic to support investments
            'source_id' => $request->input('mail_id'),
        ]);
    }
}
