<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionSchedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    private const STANDARD_VIEW = 'transactions.form_standard';

    private const INVESTMENT_VIEW = 'transactions.form_investment';

    private const INVESTMENT_RELATIONS = [
        'config',
        'config.account',
        'config.investment',
        'transactionSchedule',
        'transactionType',
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function createStandard()
    {
        // Sanity check for necessary assets
        if (\App\Models\AccountEntity::active()->where('config_type', '=', 'account')->count() === 0) {
            $this->addMessage(
                'Before creating a transaction, please add at least one account. This can be a bank account, a wallet, etc.',
                'info',
                'No accounts found',
                'info-circle'
            );

            return redirect()->route('account-entity.create', ['type' => 'account']);
        }

        return view(self::STANDARD_VIEW, [
            'transaction' => null,
            'action' => 'create',
        ]);
    }

    public function createInvestment()
    {
        return view(self::INVESTMENT_VIEW, [
            'transaction' => null,
            'action' => 'create',
        ]);
    }

    public function storeInvestment(TransactionRequest $request)
    {
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated) {
            $transaction = Transaction::make($validated);
            $transaction->user_id = Auth::user()->id;

            $transactionDetails = TransactionDetailInvestment::create($validated['config']);
            $transaction->config()->associate($transactionDetails);

            $transaction->push();

            if ($transaction->schedule) {
                $transactionSchedule = new TransactionSchedule(
                    [
                        'transaction_id' => $transaction->id,
                    ]
                );
                $transactionSchedule->fill($validated['schedule_config']);
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            return $transaction;
        });

        // Adjust source transaction schedule, if needed
        if ($validated['action'] === 'enter') {
            $sourceTransaction = Transaction::find($validated['id'])
                ->load(['transactionSchedule']);
            $sourceTransaction->transactionSchedule->skipNextInstance();
        }

        self::addMessage('Transaction added (#'.$transaction->id.')', 'success', '', '', true);

        return response()->json(
            [
                'transaction_id' => $transaction->id,
            ]
        );
    }

    /**
     * Show the form with data of selected transaction
     * Actual behavior is controlled by action
     *
     * @param  App\Model\Transaction  $transaction
     * @param  string  $action
     * @return view
     */
    public function openStandard(Transaction $transaction, string $action)
    {
        // Load all relevant relations
        $transaction->loadStandardDetails();

        // Show is routed to special view, and also further data is needed
        if ($action === 'show') {
            return view('transactions.show_standard', [
                'transaction' => $transaction,
            ]);
        }

        // Adjust date and schedule settings, if entering a recurring item
        if ($action === 'enter') {
            // Reset schedule and budget flags
            $transaction->schedule = false;
            $transaction->budget = false;

            // Date is next schedule date
            $transaction->date = $transaction->transactionSchedule->next_date->format('Y-m-d');
        }

        return view(self::STANDARD_VIEW, [
            'transaction' => $transaction,
            'action' => $action,
        ]);
    }

    public function openInvestment(Transaction $transaction, string $action)
    {
        $transaction->load(self::INVESTMENT_RELATIONS);

        // Adjust date and schedule settings, if entering a recurring item
        if ($action === 'enter') {
            // Reset schedule and budget flags
            $transaction->schedule = false;
            $transaction->budget = false;

            // Date is next schedule date
            $transaction->date = $transaction->transactionSchedule->next_date->format('Y-m-d');
        }

        return view(self::INVESTMENT_VIEW, [
            'transaction' => $transaction,
            'action' => $action,
        ]);
    }

    public function updateInvestment(TransactionRequest $request, Transaction $transaction)
    {
        $validated = $request->validated();

        $transaction->fill($validated);
        $transaction->config->fill($validated['config']);

        if ($transaction->schedule) {
            $transaction->transactionSchedule->fill($validated['schedule_config']);
        }

        $transaction->push();

        self::addMessage('Transaction updated (#'.$transaction->id.')', 'success', '', '', true);

        return response()->json(
            [
                'transaction_id' => $transaction->id,
            ]
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        self::addMessage('Transaction #'.$transaction->id.' deleted', 'success', '', '', true);

        return redirect()->back();
    }

    public function skipScheduleInstance(Transaction $transaction)
    {
        $transaction->transactionSchedule->skipNextInstance();
        self::addSimpleSuccessMessage('Transaction schedule instance skipped');

        return redirect()->back();
    }
}
