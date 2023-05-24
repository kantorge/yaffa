<?php

namespace App\Http\Controllers;

use App\Models\AccountEntity;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

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
        if (AccountEntity::active()->where('config_type', '=', 'account')->count() === 0) {
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
}
