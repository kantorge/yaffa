<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Investment;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionType;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionSchedule;
use Illuminate\Support\Facades\DB;
use JavaScript;

class TransactionController extends Controller
{
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
        //set action for future usage
        $action = 'create';

        //get all categories for reference
        $categories = Category::all();
        $categories->sortBy('full_name');

        $baseTransactionData = [
            'transactionType' => 'withdrawal',
            'assets' => [
                'categories' => $categories->keyBy('id')->pluck('full_name','id')->toArray(),
            ]
        ];

        $transaction = new Transaction([
            'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id
        ]);


        JavaScript::put(['baseTransactionData' => $baseTransactionData]);

        return view('transactions.form_standard', [
            'transaction' => $transaction,
            'action' => $action,
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

            $transactionItems = [];
            foreach ($validated['transactionItems'] as $item) {
                //ignore item, if amount is missing
                if (is_null($item['amount'])) {
                    continue;
                }

                $newItem = TransactionItem::create(
                    array_merge(
                        $item,
                        ['transaction_id' => $transaction->id]
                    )
                );

                //create and attach tags
                if (array_key_exists('tags', $item)) {
                    foreach ($item['tags'] as $tag) {
                        $newTag = Tag::firstOrCreate(
                            ['id' => $tag],
                            ['name' => $tag]
                        );

                        $newItem->tags()->attach($newTag);
                    }
                }

                $transactionItems[]= $newItem;
            }

            //handle default payee amount, if present, by adding amount as an item
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

        self::addSimpleSuccessMessage('Transaction added');

        return $this->redirectSelector($request->get('callback'), $transaction);
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
        //set action for future usage
        $action = 'edit';

        $transaction
            ->load([
                'config',
                'config.accountFrom',
                'config.accountTo',
                'transactionSchedule',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
            ]);

        //check if payee data needs to be filled
        if ($transaction->config->accountFrom->config_type == 'payee') {
            $payee = AccountEntity::find($transaction->config->account_from_id)
                ->load([
                    'config',
                    'config.category'
                ]);
        } elseif ($transaction->config->accountTo->config_type == 'payee') {
            $payee = AccountEntity::find($transaction->config->account_to_id)
                ->load([
                    'config',
                    'config.category'
                ]);
        } else {
            $payee = null;
        }

        $baseTransactionData = [
            'from' => [
                'amount' => $transaction->config->amount_from,
            ],
            'to' => [
                'amount' => $transaction->config->amount_to,
            ],
            'payeeCategory' => [
                'id' => ($payee ? $payee->id : null),
                'text' => ($payee && $payee->config->category ? $payee->config->category->full_name : null),
            ],
            'transactionType' => $transaction->transactionType->name,
        ];

        JavaScript::put(['baseTransactionData' => $baseTransactionData]);

        return view('transactions.form_standard', [
            'transaction' => $transaction,
            'action' => $action,
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

        $transactionItems = [];
        foreach ($validated['transactionItems'] as $item) {
            if (is_null($item['amount'])) {
                continue;
            }

            $newItem = TransactionItem::create(
                array_merge(
                    $item,
                    ['transaction_id' => $transaction->id]
                )
            );

            if (array_key_exists('tags', $item)) {
                foreach ($item['tags'] as $tag) {
                    $newTag = Tag::firstOrCreate(
                        ['id' => $tag],
                        ['name' => $tag]
                    );

                    $newItem->tags()->attach($newTag);
                }
            }

            $transactionItems[]= $newItem;
        }

        //handle default payee amount, if present, by adding amount as an item
        if ($validated['remaining_payee_default_amount'] > 0) {
            $newItem = TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'amount' => $validated['remaining_payee_default_amount'],
                    'category_id' => $validated['remaining_payee_default_category_id'],
                ]);
            $transactionItems[]= $newItem;
        }

        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->saveMany($transactionItems);

        $transaction->push();

        self::addSimpleSuccessMessage('Transaction updated');

        $this->redirectSelector($validated['callback'], $transaction);
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
     * Show the form for cloning selected resource. (Load model, but remove ID)
     *
     * @param  Transaction $transaction
     * @return \Illuminate\Http\Response
     */
    public function cloneStandard(Transaction $transaction)
    {
        //set action for future usage
        $action = 'clone';

        $transaction->load(
            [
                'config',
                'config.accountFrom',
                'config.accountTo',
                'transactionSchedule',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
            ]
        );

        //remove Id, so item is considered a new transaction
        $transaction->id = null;

        //check if payee data needs to be filled
        if ($transaction->config->accountFrom->config_type == 'payee') {
            $payee = \App\Models\AccountEntity::
                find($transaction->config->account_from_id)
                ->load([
                    'config',
                    'config.category'
                ]);
        } elseif ($transaction->config->accountTo->config_type == 'payee') {
            $payee = \App\Models\AccountEntity::
                find($transaction->config->account_to_id)
                ->load([
                    'config',
                    'config.category'
                ]);
        } else {
            $payee = null;
        }

        $baseTransactionData = [
            'from' => [
                'amount' => $transaction->config->amount_from,
            ],
            'to' => [
                'amount' => $transaction->config->amount_to,
            ],
            'payeeCategory' => [
                'id' => ($payee ? $payee->id : null),
                'text' => ($payee ? $payee->config->category->full_name : null),
            ],
            'transactionType' => $transaction->transactionType->name,
        ];

        JavaScript::put(['baseTransactionData' => $baseTransactionData]);

        return view('transactions.form_standard', [
            'transaction' => $transaction,
            'action' => $action,
        ]);
    }

    /**
     * Show the form for cloning selected resource. (Load model, but remove ID)
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
        //set action for future usage
        $action = 'enter';

        //get all categories for reference
        $categories = Category::all();
        $categories->sortBy('full_name');

        $transaction->load(
            [
                'config',
                'config.accountFrom',
                'config.accountTo',
                'transactionSchedule',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
            ]
        );

        //remove Id, so item is considered a new transaction
        $transaction->id = null;

        //reset schedule and budget flags
        $transaction->schedule = 0;
        $transaction->budget = 0;

        //date is next date
        $transaction->date = $transaction->transactionSchedule->next_date;

        $baseTransactionData = [
            'transactionType' => $transaction->transactionType->name,
            'assets' => [
                'categories' => $categories->keyBy('id')->pluck('full_name','id')->toArray(),
            ]
        ];

        JavaScript::put(['baseTransactionData' => $baseTransactionData]);

        return view('transactions.form_standard', [
            'transaction' => $transaction,
            'action' => $action,
        ]);
    }
}
