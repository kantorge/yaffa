<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    private const STANDARD_VIEW = 'transactions.form_standard';
    private const INVESTMENT_VIEW = 'transactions.form_investment';

    private const STANDARD_RELATIONS = [
        'config',
        'config.accountFrom',
        'config.accountTo',
        'transactionSchedule',
        'transactionType',
        'transactionItems',
        'transactionItems.tags',
        'transactionItems.category',
    ];

    private const INVESTMENT_RELATIONS = [
        'config',
        'config.account',
        'config.investment',
        'transactionSchedule',
        'transactionType',
    ];

    public function createStandard()
    {
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

    public function storeStandard(TransactionRequest $request)
    {
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated) {
            $transaction = Transaction::create($validated);

            $transactionDetails = TransactionDetailStandard::create($validated['config']);
            $transaction->config()->associate($transactionDetails);

            if ($transaction->schedule || $transaction->budget) {
                $transactionSchedule = new TransactionSchedule(
                    [
                        'transaction_id' => $transaction->id,
                    ]
                );
                $transactionSchedule->fill($validated['schedule_config']);
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            $transactionItems = $this->processTransactionItem($validated['items'], $transaction->id);

            // Handle default payee amount, if present, by adding amount as an item
            if (array_key_exists('remaining_payee_default_amount', $validated) && $validated['remaining_payee_default_amount'] > 0) {
                $newItem = TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'amount' => $validated['remaining_payee_default_amount'],
                        'category_id' => $validated['remaining_payee_default_category_id'],
                    ]);
                $transactionItems[]= $newItem;
            }

            $transaction->transactionItems()->saveMany($transactionItems);

            $transaction->push();

            return $transaction;
        });

        // Adjust source transaction schedule, if needed
        if ($validated['action'] === 'enter') {
            $sourceTransaction = Transaction::find($validated['id'])
                ->load(['transactionSchedule']);
            $sourceTransaction->transactionSchedule->skipNextInstance();
        }

        self::addMessage('Transaction added (#'. $transaction->id .')', 'success', '', '', true);

        return response()->json(
            [
                'transaction_id' => $transaction->id,
            ]
        );
    }

    public function storeInvestment(TransactionRequest $request)
    {
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated) {
            $transaction = Transaction::create($validated);

            $transactionDetails = TransactionDetailInvestment::create($validated['config']);
            $transaction->config()->associate($transactionDetails);

            if ($transaction->schedule) {
                $transactionSchedule = new TransactionSchedule(
                    [
                        'transaction_id' => $transaction->id,
                    ]
                );
                $transactionSchedule->fill($validated['schedule_config']);
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            $transaction->push();

            return $transaction;
        });

        self::addMessage('Transaction added (#'. $transaction->id .')', 'success', '', '', true);

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
     * @param App\Model\Transaction $transaction
     * @param string $action
     * @return view
     */
    public function openStandard(Transaction $transaction, string $action)
    {
        // Load all relevant relations
        $transaction->load(self::STANDARD_RELATIONS);

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

    public function updateStandard(TransactionRequest $request, Transaction $transaction)
    {
        $validated = $request->validated();

        // Load all relevant relations
        $transaction->load(['transactionItems']);

        $transaction->fill($validated);
        $transaction->config->fill($validated['config']);

        if ($transaction->schedule || $transaction->budget) {
            $transaction->transactionSchedule->fill($validated['schedule_config']);
        }

        // Replace exising transaction items with new array
        $transaction->transactionItems()->delete();

        $transactionItems = $this->processTransactionItem($validated['items'], $transaction->id);

        // Handle default payee amount, if present, by adding amount as an item
        if (array_key_exists('remaining_payee_default_amount', $validated) && $validated['remaining_payee_default_amount'] > 0) {
            $newItem = TransactionItem::create(
                [
                    'transaction_id' => $transaction->id,
                    'amount' => $validated['remaining_payee_default_amount'],
                    'category_id' => $validated['remaining_payee_default_category_id'],
                ]
            );
            $transactionItems[]= $newItem;
        }

        $transaction->transactionItems()->saveMany($transactionItems);

        // Save entire transaction
        $transaction->push();

        self::addMessage('Transaction updated (#'. $transaction->id .')', 'success', '', '', true);

        return response()->json(
            [
                'transaction_id' => $transaction->id,
            ]
        );
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

        self::addMessage('Transaction updated (#'. $transaction->id .')', 'success', '', '', true);

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
        return redirect()->back();
    }

    public function skipScheduleInstance(Transaction $transaction)
    {
        $transaction->transactionSchedule->skipNextInstance();
        self::addSimpleSuccessMessage('Transaction schedule instance skipped');
        return redirect()->back();
    }

    private function processTransactionItem($transactionItems, $transactionId)
    {
        $processedTransactionItems = [];
        foreach ($transactionItems as $item) {
            // Ignore item, if amount is missing
            if (!array_key_exists('amount', $item) || is_null($item['amount'])) {
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
                    $newTag = Tag::firstOrCreate(
                        ['id' => $tag],
                        ['name' => $tag]
                    );

                    // Confirm to user if item was currently created
                    if ($newTag->wasRecentlyCreated) {
                        self::addMessage('Tag added ('. $newTag->name .')', 'success', '', '', true);
                    }

                    $newItem->tags()->attach($newTag);
                }
            }

            $processedTransactionItems[]= $newItem;
        }

        return $processedTransactionItems;
    }
}
