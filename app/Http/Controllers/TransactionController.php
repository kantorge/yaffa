<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionType;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionSchedule;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    private const STANDARD_VIEW = "transactions.form_standard";

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

    private function redirectSelector(string $action, Transaction $transaction)
    {
        switch ($action) {
            case 'newStandard':
                $route = redirect()
                    ->route('transactions.createStandard');
                break;
            case 'newInvestment':
                $route = redirect()
                    ->route('transactions.createInvestment');
                break;
            case 'cloneInvestment':
                $route = redirect()
                    ->route(
                        'transactions.cloneInvestment',
                        [
                            'transaction' => $transaction
                        ]
                    );
                break;
            case 'cloneStandard':
                $route = redirect()
                    ->route(
                        'transactions.cloneStandard',
                        [
                            'transaction' => $transaction
                        ]
                    );
                break;
            case 'returnToAccount':
                switch ($transaction->transactionType->name) {
                    case 'withdrawal':
                    case 'transfer':
                        $account = $transaction->config->account_from_id;
                        break;
                    case 'deposit':
                        $account = $transaction->config->account_to_id;
                        break;
                    //investments
                    default:
                        $account = $transaction->config->account_id;
                }

                $route = redirect()
                    ->route(
                        'account.history',
                        [
                            'account' => $account
                        ]
                    );
                break;

            //returnToDashboard
            default:
                $route = redirect()->route('home');
        }

        return $route;
    }

    public function createStandard()
    {
        return view(self::STANDARD_VIEW, [
            'transaction' => null,
            'action' => 'create',
        ]);
    }

    public function createInvestment()
    {
        $transaction = new Transaction([
            'transaction_type_id' => TransactionType::where('name', 'buy')->first()->id
        ]);

        //get all accounts
        $allAccounts = AccountEntity::where('config_type', 'account')->pluck('name', 'id')->all();

        return view('transactions.form_investment', [
            'allAccounts' => $allAccounts,
            'transaction' => $transaction,
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
                $transactionSchedule = TransactionSchedule::create(
                    [
                        'transaction_id' => $transaction->id,
                        'start_date' => $validated['schedule_start'],
                        'next_date' => $validated['schedule_next'],
                        'end_date' => $validated['schedule_end'],
                        'frequency' => $validated['schedule_frequency'],
                        'interval' => $validated['schedule_interval'],
                        'count' => $validated['schedule_count'],
                    ]
                );
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            $transactionItems = $this->processTransactionItem($validated['items'], $transaction->id);

            // Handle default payee amount, if present, by adding amount as an item
            if ($validated['remaining_payee_default_amount'] > 0) {
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
                $transactionSchedule = TransactionSchedule::create(
                    [
                        'transaction_id' => $transaction->id,
                        'start_date' => $validated['schedule_start'],
                        'next_date' => $validated['schedule_next'],
                        'end_date' => $validated['schedule_end'],
                        'frequency' => $validated['schedule_frequency'],
                        'interval' => $validated['schedule_interval'],
                        'count' => $validated['schedule_count'],
                    ]
                );
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            $transaction->push();

            return $transaction;
        });

        self::addSimpleSuccessMessage('Transaction added');

        return $this->redirectSelector($request->get('callback'), $transaction);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param App\Model\Transaction $transaction
     * @return view
     */
    public function editStandard(Transaction $transaction)
    {
        // Load all relevant relations
        $transaction->load(self::STANDARD_RELATIONS);

        return view(self::STANDARD_VIEW, [
            'transaction' => $transaction,
            'action' => 'edit',
        ]);
    }

    public function editInvestment(Transaction $transaction)
    {
        $transaction->load(
            [
                'config',
                'config.account',
                'config.investment',
                'transactionSchedule',
                'transactionType',
            ]
        );

        //get all accounts
        $allAccounts = AccountEntity::where('config_type', 'account')->pluck('name', 'id')->all();

        return view('transactions.form_investment', [
            'allAccounts' => $allAccounts,
            'transaction' => $transaction,
        ]);
    }

    public function updateStandard(TransactionRequest $request, Transaction $transaction)
    {
        $validated = $request->validated();

        $transaction->fill($validated);
        $transaction->config->fill($validated['config']);

        if ($transaction->schedule || $transaction->budget) {
            $transaction->transactionSchedule()->start_date = $validated['schedule_start'];
            $transaction->transactionSchedule()->next_date = $validated['schedule_next'];
            $transaction->transactionSchedule()->end_date = $validated['schedule_end'];
            $transaction->transactionSchedule()->frequency = $validated['frequency'];
            $transaction->transactionSchedule()->interval = $validated['interval'];
            $transaction->transactionSchedule()->count = $validated['count'];
        }

        $transactionItems = $this->processTransactionItem($validated['items'], $transaction->id);

        //handle default payee amount, if present, by adding amount as an item
        if ($validated['remaining_payee_default_amount'] > 0) {
            $newItem = TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'amount' => $validated['remaining_payee_default_amount'],
                    'category_id' => $validated['remaining_payee_default_category_id'],
                ]);
            $transactionItems[]= $newItem;
        }

        // Replace exising transaction items with new array
        $transaction->transactionItems()->delete();
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
            $transaction->transactionSchedule()->start_date = $validated['schedule_start'];
            $transaction->transactionSchedule()->next_date = $validated['schedule_next'];
            $transaction->transactionSchedule()->end_date = $validated['schedule_end'];
            $transaction->transactionSchedule()->frequency = $validated['frequency'];
            $transaction->transactionSchedule()->interval = $validated['interval'];
            $transaction->transactionSchedule()->count = $validated['count'];
        }

        $transaction->push();

        self::addSimpleSuccessMessage('Transaction updated');

        return redirect("home");
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

    /**
     * Show the form for cloning selected resource.
     * (Load model, but remove ID)
     *
     * @param  Transaction $transaction
     * @return \Illuminate\Http\Response
     */
    public function cloneStandard(Transaction $transaction)
    {
        // Load all relevant relations
        $transaction->load(self::STANDARD_RELATIONS);

        // Remove Id, so transaction is considered a new transaction
        $transaction->id = null;

        return view(self::STANDARD_VIEW, [
            'transaction' => $transaction,
            'action' => 'clone',
        ]);
    }

    /**
     * Show the form for saving selected resource.
     * (Load model, but remove ID and set date based on schedule)
     *
     * @param  Transaction $transaction
     * @return \Illuminate\Http\Response
     */
    public function cloneInvestment(Transaction $transaction)
    {
        //set action for future usage
        $action = 'clone';

        $transaction->load(
            [
                'config',
                'config.account',
                'config.investment',
                'transactionSchedule',
                'transactionType',
            ]
        );

        //remove Id, so item is considered a new transaction
        $transaction->id = null;

        //get all accounts
        $allAccounts = AccountEntity::where('config_type', 'account')->pluck('name', 'id')->all();

        return view('transactions.form_investment', [
            'allAccounts' => $allAccounts,
            'transaction' => $transaction,
            'action' => $action,
        ]);
    }

    public function enterWithEditStandard(Transaction $transaction)
    {
        // Load all relevant relations
        $transaction->load(self::STANDARD_RELATIONS);

        // Rremove Id, so transaction is considered a new transaction
        $transaction->id = null;

        // Reset schedule and budget flags
        $transaction->schedule = false;
        $transaction->budget = false;

        // Date is next schedule date
        $transaction->date = $transaction->transactionSchedule->next_date;

        return view(self::STANDARD_VIEW, [
            'transaction' => $transaction,
            'action' => 'enter',
        ]);
    }

    private function processTransactionItem($transactionItems, $transactionId)
    {
        $processedTransactionItems = [];
        foreach ($transactionItems as $item) {
            // Ignore item, if amount is missing
            if (is_null($item['amount'])) {
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
