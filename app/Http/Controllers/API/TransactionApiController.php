<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Http\Traits\CurrencyTrait;
use App\Models\AccountEntity;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionApiController extends Controller
{

    use CurrencyTrait;

    private $allAccounts;

    private $allAccountCurrencies;

    private $allTags;

    private $allCategories;

    private $baseCurrency;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    public function reconcile(Transaction $transaction, $newState)
    {
        /**
         * @put('/api/transaction/{transaction}/reconciled/{newState}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $this->authorize('update', $transaction);

        $transaction->reconciled = $newState;
        $transaction->save();

        return response()->json(
            [
                'success' => true,
            ],
            Response::HTTP_OK
        );
    }

    public function getItem(Transaction $transaction)
    {
        /**
         * @get('/api/transaction/{transaction}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $transaction->loadStandardDetails();

        return response()->json(
            [
                'transaction' => $transaction,
            ],
            Response::HTTP_OK
        );
    }

    public function getScheduledItems(string $type, Request $request)
    {
        /**
         * @get('/api/transactions/get_scheduled_items/{type}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */

        // Return empty response if categories are required, but not set or empty
        if($request->has('category_required')) {
            if (! $request->has('categories') || ! $request->input('categories')) {
                return response()->json([], Response::HTTP_OK);
            }
        }

        // Get all accounts and payees so their name can be reused
        $this->allAccounts = AccountEntity::where('user_id', Auth::user()->id)
            ->pluck('name', 'id')
            ->all();

        // Get all tags
        $this->allTags = Auth::user()->tags->pluck('name', 'id')->all();

        // Get all categories
        $this->allCategories = Auth::user()->categories->pluck('full_name', 'id')->all();

        // Get all currencies
        $this->allAccountCurrencies = Auth::user()->accounts()
            ->with([
                'config',
                'config.currency',
            ])
            ->get();

        // Load the base currency for the user
        $this->baseCurrency = $this->getBaseCurrency();

        // Get list of requested categories
        // Ensure, that child categories are loaded for all parents
        $categories = $this->getChildCategories($request);

        // Get all standard transactions
        $standardTransactions = Transaction::with(
            [
                'config',
                'transactionType',
                'transactionSchedule',
                'transactionItems',
                'transactionItems.tags',
            ]
        )
        ->where('user_id', Auth::user()->id)
        ->byScheduleType($type)
        ->where(
            'config_type',
            '=',
            'transaction_detail_standard'
        )
        ->when($categories->count() > 0, function($query) use ($categories) {
            $query->whereHas('transactionItems', function ($query) use ($categories) {
                $query->whereIn('category_id', $categories->pluck('id'));
            });
        })
        ->get();

        // Return empty collection if categories are required
        if ($request->has('category_required')) {
            $investmentTransactions = new Collection();
        } else {
            // Get all investment transactions
            $investmentTransactions = Transaction::with(
                [
                    'config',
                    'config.investment',
                    'transactionType',
                    'transactionSchedule',
                ]
            )
            ->where('user_id', Auth::user()->id)
            ->byScheduleType($type)
            ->where(
                'config_type',
                '=',
                'transaction_detail_investment'
            )
            ->get();
        }

        // Unify and merge two transaction types
        $transactions = $standardTransactions
            ->map(function ($transaction) {
                $commonData = $this->transformDataCommon($transaction);
                $baseData = $this->transformDataStandard($transaction);
                $currency = $this->getCurrency($transaction);

                return array_merge($commonData, $baseData, $currency);
            })
            ->merge($investmentTransactions
                ->map(function ($transaction) {
                    $commonData = $this->transformDataCommon($transaction);
                    $baseData = $this->transformDataInvestment($transaction);
                    $currency = $this->getCurrency($transaction);

                    return array_merge($commonData, $baseData, $currency);
                }));

        return response()->json(
            [
                'transactions' => $transactions->values(),
            ],
            Response::HTTP_OK
        );
    }

    private function transformDataCommon(Transaction $transaction)
    {
        // Prepare schedule related data if schedule is set
        $schedule = null;
        if ($transaction->transactionSchedule) {
            $schedule = [
                'start_date' => $transaction->transactionSchedule->start_date->toISOString(),
                'next_date' => ($transaction->transactionSchedule->next_date ? $transaction->transactionSchedule->next_date->toISOString() : null),
                'end_date' => ($transaction->transactionSchedule->end_date ? $transaction->transactionSchedule->end_date->toISOString() : null),
                'frequency' => $transaction->transactionSchedule->frequency,
                'count' => $transaction->transactionSchedule->count,
                'interval' => $transaction->transactionSchedule->interval,
            ];
        }

        return [
            'id' => $transaction->id,
            'date' => $transaction->date,  // Change compared to schedule controller
            'transaction_type' => $transaction->transactionType->toArray(),
            'config_type' => $transaction->config_type,
            'schedule_config' => $schedule,
            'schedule' => $transaction->schedule,
            'budget' => $transaction->budget,
            'comment' => $transaction->comment,
            'reconciled' => $transaction->reconciled,
        ];
    }

    private function transformDataStandard(Transaction $transaction)
    {
        $transactionArray = $transaction->toArray();

        $itemTags = [];
        $itemCategories = [];
        foreach ($transactionArray['transaction_items'] as $item) {
            if (isset($item['tags'])) {
                foreach ($item['tags'] as $tag) {
                    $itemTags[$tag['id']] = $this->allTags[$tag['id']];
                }
            }
            if (isset($item['category_id'])) {
                $itemCategories[$item['category_id']] = $this->allCategories[$item['category_id']];
            }
        }

        return [
            'transaction_operator' => $transaction->transactionType->amount_operator,
            'config' => [
                'account_from_id' => $transaction->config->account_from_id,
                'account_from' => [
                    'name' => $this->allAccounts[$transaction->config->account_from_id] ?? null,
                    'id' => $transaction->config->account_from_id,
                ],
                'account_to_id' => $transaction->config->account_to_id,
                'account_to' => [
                    'name' => $this->allAccounts[$transaction->config->account_to_id] ?? null,
                    'id' => $transaction->config->account_to_id,
                ],
                'amount_from' => $transaction->config->amount_from,
                'amount_to' => $transaction->config->amount_to,
            ],
            'tags' => array_values($itemTags),
            'categories' => array_values($itemCategories),
        ];
    }

    private function transformDataInvestment(Transaction $transaction)
    {
        $amount = $transaction->cashflowValue();

        return [
            'transaction_operator' => $transaction->transactionType->amount_operator,
            'quantity_operator' => $transaction->transactionType->quantity_operator,
            'config' => [
                'account_from_id' => $transaction->config->account_id,
                'account_from' => [
                    'name' => $this->allAccounts[$transaction->config->account_id],
                    'id' => $transaction->config->account_id,
                ],
                'account_to_id' => $transaction->config->investment_id,
                'account_to' => [
                    'name' => $transaction->config->investment->name,
                    'id' => $transaction->config->investment_id,
                ],
                'amount_from' => ($amount > 0 ? $amount : 0),
                'amount_to' => ($amount > 0 ? $amount : 0),
            ],
            'tags' => [],

            'investment_name' => $transaction->config->investment->name,
            'quantity' => $transaction->config->quantity,
            'price' => $transaction->config->price,
        ];
    }

    private function getCurrency(Transaction $transaction)
    {
        $currency = null;

        if ($transaction->transactionType->type === 'standard') {
            if ($transaction->transactionType->name === 'withdrawal') {
                $currency = $this->allAccountCurrencies->find($transaction->config->account_from_id);
            } elseif ($transaction->transactionType->name === 'deposit') {
                $currency = $this->allAccountCurrencies->find($transaction->config->account_to_id);
            } elseif ($transaction->transactionType->name === 'transfer') {
                $currency = $this->allAccountCurrencies->find($transaction->config->account_to_id);
            }
        } elseif ($transaction->transactionType->type === 'investment') {
            $currency = $this->allAccountCurrencies->find($transaction->config->account_id);
        }

        return [
            'currency' => $currency?->config->currency ?? $this->baseCurrency,
        ];
    }

    public function findTransactions(Request $request)
    {
        /**
         * @get('/api/transactions')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        // Check if only count is requested
        $onlyCount = $request->has('only_count');

        if (! $request->hasAny([
            'date_from',
            'date_to',
            'accounts',
            'categories',
            'payees',
            'tags',
        ])) {
            return response()->json(
                [
                    'data' => [],
                    'count' => 0,
                ],
                Response::HTTP_OK
            );
        }

        $user = Auth::user();

        // Get standard transactions matching any provided criteria
        $standardQuery = Transaction::where('user_id', $user->id)
            ->byScheduleType('none')
            ->where('config_type', 'transaction_detail_standard')
            ->when($request->has('date_from'), function ($query) use ($request) {
                $query->where('date', '>=', $request->get('date_from'));
            })
            ->when($request->has('date_to'), function ($query) use ($request) {
                $query->where('date', '<=', $request->get('date_to'));
            })
            ->when($request->has('accounts') && $request->get('accounts'), function ($query) use ($request) {
                $query->whereIn('config_id', function ($query) use ($request) {
                    $query->select('id')
                        ->from('transaction_details_standard')
                        ->whereIn('account_from_id', $request->get('accounts'))
                        ->orWhereIn('account_to_id', $request->get('accounts'));
                });
            })
            ->when($request->has('payees') && $request->get('payees'), function ($query) use ($request) {
                $query->whereIn('config_id', function ($query) use ($request) {
                    $query->select('id')
                        ->from('transaction_details_standard')
                        ->whereIn('account_from_id', $request->get('payees'))
                        ->orWhereIn('account_to_id', $request->get('payees'));
                });
            })
            ->when($request->has('categories') && $request->get('categories'), function ($query) use ($request) {
                $query->whereIn('id', function ($query) use ($request) {
                    $query->select('transaction_id')
                        ->from('transaction_items')
                        ->whereIn('category_id', $request->get('categories'));
                });
            })
            ->when($request->has('tags') && $request->get('tags'), function ($query) use ($request) {
                $query->whereIn('id', function ($query) use ($request) {
                    $query->select('transaction_id')
                        ->from('transaction_items')
                        ->whereIn('id', function ($query) use ($request) {
                            $query->select('transaction_item_id')
                                ->from('transaction_items_tags')
                                ->whereIn('tag_id', $request->get('tags'));
                        });
                });
            });

        // Return only count of transactions if requested
        if ($onlyCount) {
            $count = $standardQuery->count();

            return response()->json(
                [
                    'data' => [],
                    'count' => $count,
                ],
                Response::HTTP_OK
            );
        }

        $standardTransactions = $standardQuery
            ->with([
                'config',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
            ])
            ->get()
            ->loadMorph('config', [
                TransactionDetailStandard::class => ['config'],
            ]);

        // Preprocess data

        // Get all accounts and payees so their name can be reused
        $this->allAccounts = AccountEntity::where('user_id', Auth::user()->id)
            ->pluck('name', 'id')
            ->all();

        $this->allAccountCurrencies = Auth::user()->accounts()
            ->with([
                'config',
                'config.currency',
            ])
            ->get();

        // Get all tags
        $this->allTags = Auth::user()->tags->pluck('name', 'id')->all();

        // Get all categories
        $this->allCategories = Auth::user()->categories->pluck('full_name', 'id')->all();

        $transactions = $standardTransactions
            ->map(function ($transaction) {
                $commonData = $this->transformDataCommon($transaction);
                $baseData = $this->transformDataStandard($transaction);
                $currency = $this->getCurrency($transaction);

                return array_merge($commonData, $baseData, $currency);
            });

        $data = $transactions
            ->sortByDesc('transactionType')
            ->sortBy('start_date')
            ->values();

        return response()->json(
            [
                'data' => $data,
            ],
            Response::HTTP_OK
        );
    }

    public function storeStandard(TransactionRequest $request)
    {
        /**
         * @post('/api/transactions/standard')
         * @name('api.transactions.storeStandard')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated) {
            $transaction = Transaction::make($validated);
            $transaction->user_id = Auth::user()->id;
            $transaction->save();

            $transactionDetails = TransactionDetailStandard::create($validated['config']);
            $transaction->config()->associate($transactionDetails);

            $transactionItems = $this->processTransactionItem($validated['items'], $transaction->id);

            // Handle default payee amount, if present, by adding amount as an item
            if (array_key_exists('remaining_payee_default_amount', $validated) && $validated['remaining_payee_default_amount'] > 0) {
                $newItem = TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'amount' => $validated['remaining_payee_default_amount'],
                    'category_id' => $validated['remaining_payee_default_category_id'],
                ]);
                $transactionItems[] = $newItem;
            }

            $transaction->transactionItems()->saveMany($transactionItems);

            $transaction->push();

            if ($transaction->schedule || $transaction->budget) {
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

        // Adjust source transaction schedule, if entering schedule instance
        if ($validated['action'] === 'enter') {
            $sourceTransaction = Transaction::find($validated['id'])
                ->load(['transactionSchedule']);
            $sourceTransaction->transactionSchedule->skipNextInstance();
        }

        // Adjust source transaction schedule, if creating a new schedule clone
        if ($validated['action'] === 'replace') {
            $sourceTransaction = Transaction::find($validated['id'])
                ->load(['transactionSchedule']);

            $sourceTransaction->transactionSchedule->fill($validated['original_schedule_config']);

            $sourceTransaction->push();
        }

        // Create notification only if invoked from standalone view (not modal)
        // TODO: can this be done in a better way?
        if (! $validated['fromModal']) {
            self::addMessage('Transaction added (#'.$transaction->id.')', 'success', '', '', true);
        }

        // Load the transaction type relation, as it might be needed by the client
        $transaction->load(['transactionType']);

        return response()->json(
            [
                'transaction' => $transaction,
            ]
        );
    }

    public function updateStandard(TransactionRequest $request, Transaction $transaction)
    {
        /**
         * @patch('/api/transactions/standard/{transaction}')
         * @name('api.transactions.updateStandard')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
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
            $transactionItems[] = $newItem;
        }

        $transaction->transactionItems()->saveMany($transactionItems);

        // Save entire transaction
        $transaction->push();

        // Create notification only if invoked from standalone view (not modal)
        // TODO: can this be done in a better way?
        if (! $validated['fromModal']) {
            self::addMessage('Transaction updated (#'.$transaction->id.')', 'success', '', '', true);
        }

        // Not needed for the store procedure, but can be required for the client
        $transaction->load(['transactionType']);

        return response()->json(
            [
                'transaction' => $transaction,
            ]
        );
    }

    private function processTransactionItem($transactionItems, $transactionId)
    {
        $processedTransactionItems = [];
        foreach ($transactionItems as $item) {
            // Ignore item, if amount is missing
            if (! array_key_exists('amount', $item) || is_null($item['amount'])) {
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
                        self::addMessage('Tag added ('.$newTag->name.')', 'success', '', '', true);
                    }

                    $newItem->tags()->attach($newTag);
                }
            }

            $processedTransactionItems[] = $newItem;
        }

        return $processedTransactionItems;
    }

    public function skipScheduleInstance(Transaction $transaction)
    {
        /**
         * @patch('/api/transactions/{transaction}/skip')
         * @name('api.transactions.skipScheduleInstance')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $transaction->transactionSchedule->skipNextInstance();

        return response()->json(
            [
                'transaction' => $transaction,
            ]
        );
    }

    private function getChildCategories(Request $request)
    {
        $categories = collect();

        if ($request->missing('categories')) {
            return $categories;
        }

        $requestedCategories = Auth::user()
            ->categories()
            ->whereIn('id', $request->get('categories'))
            ->get();

        $requestedCategories->each(function ($category) use (&$categories) {
            if ($category->parent_id === null) {
                $children = Auth::user()
                    ->categories()
                    ->where('parent_id', '=', $category->id)
                    ->get();
                $categories = $categories->merge($children);
            }

            $categories->push($category);
        });

        return $categories->unique('id');
    }
}
