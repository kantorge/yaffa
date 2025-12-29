<?php

namespace App\Http\Controllers\API;

use App\Events\TransactionCreated;
use App\Events\TransactionDeleted;
use App\Events\TransactionUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Http\Traits\CurrencyTrait;
use App\Models\Account;
use App\Models\ReceivedMail;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use App\Services\CategoryService;
use App\Services\TransactionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionApiController extends Controller
{
    use CurrencyTrait;

    private CategoryService $categoryService;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
        $this->categoryService = new CategoryService();
    }

    /**
     * Change the reconciled flag of a transaction to a new value.
     *
     * @param Transaction $transaction
     * @param string $newState
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function reconcile(Transaction $transaction, string $newState): JsonResponse
    {
        /**
         * @put('/api/transaction/{transaction}/reconciled/{newState}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $this->authorize('update', $transaction);

        $transaction->reconciled = boolval($newState);
        $transaction->save();

        return response()->json([], Response::HTTP_OK);
    }

    public function getItem(Transaction $transaction): JsonResponse
    {
        /**
         * @get('/api/transaction/{transaction}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $transaction->loadDetails();

        return response()->json(
            [
                'transaction' => $transaction,
            ],
            Response::HTTP_OK
        );
    }

    public function getScheduledItems(string $type, Request $request): JsonResponse
    {
        /**
         * @get('/api/transactions/get_scheduled_items/{type}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */

        // Return empty response if categories are required, but not set or empty
        if ($request->has('category_required')
            && (!$request->has('categories') || !$request->input('categories'))) {
            return response()->json([], Response::HTTP_OK);
        }

        // Get list of requested categories
        // Ensure, that child categories are loaded for all parents
        $categories = $this->categoryService->getChildCategories($request);

        // Get the account selection properties
        $accountSelection = $request->get('accountSelection');
        $accountEntity = $request->get('accountEntity');

        // Get all standard transactions
        $standardTransactions = Transaction::with([
            'config',
            'config.accountFrom',
            'config.accountTo',
            'transactionType',
            'transactionSchedule',
            'transactionItems',
            'transactionItems.category',
            'transactionItems.tags',
        ])
            ->where('user_id', $request->user()->id)
            ->byScheduleType($type)
            ->byType('standard')
            // Optionally add account filter
            ->when($accountSelection === 'selected', function ($query) use ($accountEntity) {
                $query->whereHasMorph(
                    'config',
                    [TransactionDetailStandard::class],
                    function (Builder $query) use ($accountEntity) {
                        $query->where('account_from_id', $accountEntity);
                        $query->orWhere('account_to_id', $accountEntity);
                    }
                );
            })
            // Optionally exclude transactions with a specified account
            ->when($accountSelection === 'none', function ($query) {
                return $query->where(function ($query) {

                    return $query
                        // Withdrawal with empty account_from_id
                        ->where(function ($query) {
                            $query->where('transaction_type_id', config('transaction_types')['withdrawal']['id'])
                                ->whereHasMorph(
                                    'config',
                                    TransactionDetailStandard::class,
                                    fn ($query) => $query->whereNull('account_from_id')
                                );
                        })
                        // Or deposit with empty account_to_id
                        ->orWhere(function ($query) {
                            $query->where('transaction_type_id', config('transaction_types')['deposit']['id'])
                                ->whereHasMorph(
                                    'config',
                                    TransactionDetailStandard::class,
                                    fn ($query) => $query->whereNull('account_to_id')
                                );
                        });
                });
            })
            // Optionally add category filter
            ->when($categories->count() > 0, function ($query) use ($categories) {
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
            $investmentTransactions = Transaction::with([
                'config',
                'config.account',
                'config.investment',
                'transactionType',
                'transactionSchedule',
            ])
                ->where('user_id', $request->user()->id)
                ->byScheduleType($type)
                ->byType('investment')
                // Optionally add account filter
                ->when($accountSelection === 'selected', function ($query) use ($accountEntity) {
                    $query->whereHasMorph(
                        'config',
                        [TransactionDetailInvestment::class],
                        function (Builder $query) use ($accountEntity) {
                            $query->where('account_id', $accountEntity);
                        }
                    );
                })
                // Investment transactions always have an account, so the 'none' account selection is not relevant
                ->get();
        }

        return response()->json(
            [
                'transactions' => $standardTransactions->concat($investmentTransactions),
            ],
            Response::HTTP_OK
        );
    }

    public function findTransactions(Request $request): JsonResponse
    {
        /**
         * @get('/api/transactions')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */

        // A request without any search criteria will return an empty response to avoid loading all transactions
        if (!$request->hasAny([
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

        $user = $request->user();

        // Check if only count is requested
        $onlyCount = $request->has('only_count');

        // Get standard transactions matching any provided criteria
        $standardQuery = Transaction::where('user_id', $user->id)
            ->byScheduleType('none')
            ->byType('standard')
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

        // Get investment transactions matching any provided criteria
        // This part of the query is run only if relevant search criteria is provided, and no other search criteria is provided
        if ($request->hasAny(['date_from', 'date_to','accounts'])
            && !($request->hasAny(['categories', 'payees', 'tags']))) {
            $investmentQuery = Transaction::where('user_id', $user->id)
                ->byScheduleType('none')
                ->byType('investment')
                ->when($request->has('date_from'), function ($query) use ($request) {
                    $query->where('date', '>=', $request->get('date_from'));
                })
                ->when($request->has('date_to'), function ($query) use ($request) {
                    $query->where('date', '<=', $request->get('date_to'));
                })
                ->when($request->has('accounts') && $request->get('accounts'), function ($query) use ($request) {
                    $query->whereIn('config_id', function ($query) use ($request) {
                        $query->select('id')
                            ->from('transaction_details_investment')
                            ->whereIn('account_id', $request->get('accounts'));
                    });
                });
        } else {
            $investmentQuery = Transaction::where('user_id', $user->id)  // User ID is used for security reasons
                ->byScheduleType('none')->byType('investment') // Pretend that we are searching for investment transactions
                ->whereRaw('1 = 0'); // Make sure that the query returns no results
        }

        // Return only count of transactions if requested
        if ($onlyCount) {
            $count = $standardQuery->count() + $investmentQuery->count();

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
                'config.accountFrom',
                'config.accountTo',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
            ])
            ->get()
            ->loadMorph(
                'config.accountFrom',
                [
                    Account::class => ['config', 'config.currency'],
                ]
            )
            ->loadMorph(
                'config.accountTo',
                [
                    Account::class => ['config', 'config.currency'],
                ]
            );

        $investmentTransactions = $investmentQuery
            ->with([
                'config',
                'config.account',
                'config.account.config',
                'config.account.config.currency',
                'config.investment',
                'transactionType',
                'transactionSchedule',
            ])
            ->get();

        $transactions = $standardTransactions->concat($investmentTransactions);

        // We need to load the currency rates for the transactions
        // TODO: should this be done even more generally?

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRatesMap = $this->allCurrencyRatesByMonth();

        // Loop through all transactions and add the currency rate to the base currency
        // Also, calculate the amount in the base currency for the transaction and all its items, if applicable
        $transactions->map(function ($transaction) use ($baseCurrency, $allRatesMap) {
            $transaction->currencyRateToBase = $this->getLatestRateFromMap(
                $transaction->currency_id,
                $transaction->date,
                $allRatesMap,
                $baseCurrency->id
            ) ?? 1;

            // Extend the optional amount_to and amount_from fields in the config
            if ($transaction->config->amount_to) {
                $transaction->config->amount_to_base = $transaction->config->amount_to * $transaction->currencyRateToBase;
            }
            if ($transaction->config->amount_from) {
                $transaction->config->amount_from_base = $transaction->config->amount_from * $transaction->currencyRateToBase;
            }

            // Extend the amount field in the items
            if ($transaction->transactionItems) {
                $transaction->transactionItems->map(function ($item) use ($transaction) {
                    $item->amount_in_base = $item->amount * $transaction->currencyRateToBase;
                });
            }

            return $transaction;
        });

        return response()->json(
            [
                'data' => $transactions,
            ],
            Response::HTTP_OK
        );
    }

    public function storeStandard(TransactionRequest $request): JsonResponse
    {
        /**
         * @post('/api/transactions/standard')
         * @name('api.transactions.storeStandard')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated, $request) {
            // Create the configuration first
            $transactionDetails = TransactionDetailStandard::create($validated['config']);

            $transaction = new Transaction($validated);
            $transaction->user_id = $request->user()->id;
            $transaction->config()->associate($transactionDetails);
            $transaction->push();

            // Transfer transactions (type 3) should NOT have transaction items
            if ($transaction->transaction_type_id != 3) {
                $transactionItems = $this->processTransactionItem($validated['items'], $transaction->id);

                // Handle default payee amount, if present, by adding amount as an item
                if (array_key_exists('remaining_payee_default_amount', $validated)
                    && $validated['remaining_payee_default_amount'] > 0) {
                    $newItem = TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'amount' => $validated['remaining_payee_default_amount'],
                        'category_id' => $validated['remaining_payee_default_category_id'],
                    ]);
                    $transactionItems[] = $newItem;
                }

                $transaction->transactionItems()->saveMany($transactionItems);
            }

            $transaction->push();

            if ($transaction->schedule || $transaction->budget) {
                $transactionSchedule = new TransactionSchedule(['transaction_id' => $transaction->id]);
                $transactionSchedule->fill($validated['schedule_config']);
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            return $transaction;
        });

        $this->handleSourceTransactionUpdates($validated);

        // Save reference to incoming mail, if finalizing a transaction from email
        if ($validated['action'] === 'finalize' && $validated['source_id']) {
            $mail = ReceivedMail::find($validated['source_id']);
            $mail->transaction_id = $transaction->id;
            $mail->handled = true;
            $mail->save();
        }

        // Create notification only if invoked from standalone view (not modal)
        // TODO: can this be done in a better way, so that the controller is not aware of the caller context?
        if (!$validated['fromModal']) {
            self::addMessage('Transaction added (#' . $transaction->id . ')', 'success', '', '', true);
        }

        // Generate an event for the new transaction
        event(new TransactionCreated($transaction));

        // Ensure that the transaction is loaded with all relations
        $transaction->loadDetails();

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    public function storeInvestment(TransactionRequest $request): JsonResponse
    {
        /**
         * @post('/api/transactions/investment')
         * @name('api.transactions.storeInvestment')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $validated = $request->validated();

        \Log::info('storeInvestment called', ['transaction_type_id' => $validated['transaction_type_id']]);

        // Special handling for Interest ReInvest (transaction_type_id = 13)
        if ($validated['transaction_type_id'] == 13) {
            \Log::info('Redirecting to handleInterestReInvestTransaction');
            return $this->handleInterestReInvestTransaction($validated, $request);
        }

        $transaction = DB::transaction(function () use ($validated, $request) {
            // Create the configuration first
            $transactionDetails = TransactionDetailInvestment::create($validated['config']);

            $transaction = new Transaction($validated);
            $transaction->user_id = $request->user()->id;
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

        $this->handleSourceTransactionUpdates($validated);

        // Create notification only if invoked from standalone view (not modal)
        // TODO: can this be done in a better way, so that the Controller is not aware of the caller context?
        if (!$validated['fromModal']) {
            self::addMessage('Transaction added (#' . $transaction->id . ')', 'success', '', '', true);
        }

        // Generate an event for the new transaction
        event(new TransactionCreated($transaction));

        // Ensure that the transaction is loaded with all relations
        $transaction->loadDetails();

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    public function updateStandard(TransactionRequest $request, Transaction $transaction): JsonResponse
    {
        /**
         * @patch('/api/transactions/standard/{transaction}')
         * @name('api.transactions.updateStandard')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $validated = $request->validated();

        // Define a variable to keep track of changes
        $attributeChanges = [];

        // Load all relevant relations
        $transaction->load([
            'transactionItems',
            'transactionSchedule'
        ]);

        $transaction->fill($validated);
        $transaction->config->fill($validated['config']);

        // Store the original values of the changed attributes
        $changedAttributes = $transaction->getDirty();
        foreach ($changedAttributes as $key => $value) {
            $attributeChanges['transaction'][$key] = $transaction->getOriginal($key);
        }

        $changedAttributes = $transaction->config->getDirty();
        foreach ($changedAttributes as $key => $value) {
            $attributeChanges['config'][$key] = $transaction->config->getOriginal($key);
        }

        if ($transaction->schedule || $transaction->budget) {
            // At this point, the schedule or budget flag cannot be changed,
            // so we can safely assume that the schedule exists
            $transaction->transactionSchedule->fill($validated['schedule_config']);

            // Store changes to schedule_config
            $changedAttributes = $transaction->transactionSchedule->getDirty();
            foreach ($changedAttributes as $key => $value) {
                $attributeChanges['schedule_config'][$key] = $transaction->transactionSchedule->getOriginal($key);
            }

            // Ensure that the date of the transaction is not set
            $transaction->date = null;
        }

        // Replace exising transaction items with new array
        $transaction->transactionItems()->delete();

        // Transfer transactions (type 3) should NOT have transaction items
        if ($transaction->transaction_type_id != 3) {
            $transactionItems = $this->processTransactionItem($validated['items'], $transaction->id);

            // Handle default payee amount, if present, by adding amount as an item
            if (array_key_exists('remaining_payee_default_amount', $validated)
                && $validated['remaining_payee_default_amount'] > 0) {
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
        }
        // Transaction items are not stored as changes, as they are not triggering updates to monthly summaries

        // Save entire transaction
        $transaction->push();

        // Create notification only if invoked from standalone view (not modal)
        // TODO: can this be done in a better way, so that the Controller is not aware of the caller context?
        if (!$validated['fromModal']) {
            self::addMessage('Transaction updated (#' . $transaction->id . ')', 'success', '', '', true);
        }

        // Generate an event for the updated transaction
        event(new TransactionUpdated($transaction, $attributeChanges));

        // Ensure that the transaction is loaded with all relations
        $transaction->loadDetails();

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    // TODO: unify the update methods, account for the differences in the update process
    public function updateInvestment(TransactionRequest $request, Transaction $transaction): JsonResponse
    {
        /**
         * @patch('/api/transactions/investment/{transaction}')
         * @name('api.transactions.updateInvestment')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $validated = $request->validated();

        // Define a variable to keep track of changes
        $attributeChanges = [];

        $transaction->fill($validated);
        $transaction->config->fill($validated['config']);

        // Store the original values of the changed attributes
        $changedAttributes = $transaction->getDirty();
        foreach ($changedAttributes as $key => $value) {
            $attributeChanges['transaction'][$key] = $transaction->getOriginal($key);
        }

        $changedAttributes = $transaction->config->getDirty();
        foreach ($changedAttributes as $key => $value) {
            $attributeChanges['config'][$key] = $transaction->config->getOriginal($key);
        }

        if ($transaction->schedule) {
            // At this point, the schedule or budget flag cannot be changed,
            // so we can safely assume that the schedule exists
            $transaction->transactionSchedule->fill($validated['schedule_config']);

            // Store changes to schedule_config
            $changedAttributes = $transaction->transactionSchedule->getDirty();
            foreach ($changedAttributes as $key => $value) {
                $attributeChanges['schedule_config'][$key] = $transaction->transactionSchedule->getOriginal($key);
            }

            // Ensure that the date of the transaction is not set
            $transaction->date = null;
        }

        // Save entire transaction
        $transaction->push();

        // Create notification only if invoked from standalone view (not modal)
        // TODO: can this be done in a better way, so that the Controller is not aware of the caller context?
        if (!$validated['fromModal']) {
            self::addMessage('Transaction updated (#' . $transaction->id . ')', 'success', '', '', true);
        }

        // Generate an event for the updated transaction
        event(new TransactionUpdated($transaction, $attributeChanges));

        // Ensure that the transaction is loaded with all relations
        $transaction->loadDetails();

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    private function processTransactionItem($transactionItems, $transactionId): array
    {
        $processedTransactionItems = [];
        foreach ($transactionItems as $item) {
            // Ignore item, if amount is missing
            if (!array_key_exists('amount', $item) || $item['amount'] === null) {
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
                        self::addMessage('Tag added (' . $newTag->name . ')', 'success', '', '', true);
                    }

                    $newItem->tags()->attach($newTag);
                }
            }

            $processedTransactionItems[] = $newItem;
        }

        return $processedTransactionItems;
    }

    public function skipScheduleInstance(Transaction $transaction): JsonResponse
    {
        /**
         * @patch('/api/transactions/{transaction}/skip')
         * @name('api.transactions.skipScheduleInstance')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $transaction->loadDetails();
        $transaction->transactionSchedule->skipNextInstance();

        return response()->json(
            [
                'transaction' => $transaction,
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Transaction $transaction
     * @return JsonResponse
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        /**
         * @delete('/api/transactions/{transaction}')
         * @name('api.transactions.destroy')
         * @middlewares('web', 'auth', 'verified')
         */

        // Load the details of the transaction for the event
        $transaction->loadDetails();

        $transaction->delete();

        event(new TransactionDeleted($transaction));

        return response()->json(
            [
                'transaction' => $transaction,
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Handle additional updates to a source transaction
     *
     * @param array $validated
     *
     */
    private function handleSourceTransactionUpdates(array $validated): void
    {
        // Adjust source transaction schedule, if entering schedule instance
        if ($validated['action'] === 'enter') {
            $sourceTransaction = Transaction::find($validated['id'])
                ->load(['transactionSchedule']);

            $originalScheduleConfig = $sourceTransaction->transactionSchedule->attributesToArray();

            $sourceTransaction->transactionSchedule->skipNextInstance();

            // This also triggers a TransactionUpdated event for the source transaction
            event(new TransactionUpdated($sourceTransaction, [
                'schedule_config' => $originalScheduleConfig,
            ]));

            return;
        }

        // Adjust source transaction schedule, if creating a new schedule clone
        if ($validated['action'] === 'replace') {
            $sourceTransaction = Transaction::find($validated['id'])
                ->load(['transactionSchedule']);

            $originalScheduleConfig = $sourceTransaction->transactionSchedule->attributesToArray();

            $sourceTransaction->transactionSchedule->fill($validated['original_schedule_config']);
            $sourceTransaction->push();

            // This also triggers a TransactionUpdated event for the source transaction
            event(new TransactionUpdated($sourceTransaction, [
                'schedule_config' => $originalScheduleConfig,
            ]));
        }
    }

    /**
     * Handle Interest ReInvest transactions by creating two separate transactions:
     * 1. Interest yield transaction (for the dividend/interest income)
     * 2. Buy transaction (for reinvesting at price 1)
     */
    private function handleInterestReInvestTransaction(array $validated, $request): JsonResponse
    {
        $transactionService = new TransactionService();
        
        \Log::info('handleInterestReInvestTransaction called', ['validated' => $validated]);
        
        return DB::transaction(function () use ($validated, $request, $transactionService) {
            // Get the currency from the account
            $account = \App\Models\Account::find($validated['config']['account_id']);
            $currencyId = $account->config->currency_id;
            
            \Log::info('Creating Interest transaction', ['currencyId' => $currencyId]);
            
            // 1. Create Interest Yield transaction
            $interestConfig = TransactionDetailInvestment::create([
                'account_id' => $validated['config']['account_id'],
                'investment_id' => $validated['config']['investment_id'],
                'price' => null,
                'quantity' => null,
                'commission' => $validated['config']['commission'] ?? 0,
                'tax' => $validated['config']['tax'] ?? 0,
                'dividend' => $validated['config']['dividend'],
            ]);
            
            // Calculate cashflow: dividend - tax - commission
            $interestCashflow = $validated['config']['dividend'] 
                - ($validated['config']['tax'] ?? 0) 
                - ($validated['config']['commission'] ?? 0);
            
            \Log::info('Interest cashflow calculated', ['cashflow' => $interestCashflow]);
            
            $interestTransaction = new Transaction([
                'user_id' => $request->user()->id,
                'date' => $validated['date'],
                'transaction_type_id' => 11, // Interest yield
                'config_type' => 'investment',
                'config_id' => $interestConfig->id,
                'schedule' => $validated['schedule'] ?? false,
                'budget' => $validated['budget'] ?? false,
                'reconciled' => $validated['reconciled'] ?? false,
                'comment' => ($validated['comment'] ?? '') . ' (Interest)',
            ]);
            $interestTransaction->push();
            
            // Set cashflow after push since these fields aren't fillable
            $interestTransaction->currency_id = $currencyId;
            $interestTransaction->cashflow_value = $interestCashflow;
            $interestTransaction->saveQuietly();
            
            \Log::info('Interest transaction created', ['id' => $interestTransaction->id, 'cashflow' => $interestCashflow]);
            
            // Don't fire event - we already set cashflow manually
            // event(new TransactionCreated($interestTransaction));
            
            // Recalculate monthly summaries once for both transactions at the end
            
            \Log::info('Creating Buy transaction');
            
            // 2. Create Buy transaction
            $buyConfig = TransactionDetailInvestment::create([
                'account_id' => $validated['config']['account_id'],
                'investment_id' => $validated['config']['investment_id'],
                'price' => 1,
                'quantity' => $validated['config']['dividend'], // shares = dividend amount
                'commission' => null,
                'tax' => null,
                'dividend' => null,
            ]);
            
            // Calculate cashflow: -1 * price * quantity
            $buyCashflow = -1 * 1 * $validated['config']['dividend'];
            
            \Log::info('Buy cashflow calculated', ['cashflow' => $buyCashflow]);
            
            $buyTransaction = new Transaction([
                'user_id' => $request->user()->id,
                'date' => $validated['date'],
                'transaction_type_id' => 4, // Buy
                'config_type' => 'investment',
                'config_id' => $buyConfig->id,
                'schedule' => $validated['schedule'] ?? false,
                'budget' => $validated['budget'] ?? false,
                'reconciled' => $validated['reconciled'] ?? false,
                'comment' => ($validated['comment'] ?? '') . ' (Buy)',
            ]);
            $buyTransaction->push();
            
            // Set cashflow after push since these fields aren't fillable
            $buyTransaction->currency_id = $currencyId;
            $buyTransaction->cashflow_value = $buyCashflow;
            $buyTransaction->saveQuietly();
            
            \Log::info('Buy transaction created', ['id' => $buyTransaction->id, 'cashflow' => $buyCashflow]);
            
            // Don't fire event - we already set cashflow manually
            // event(new TransactionCreated($buyTransaction));
            
            // Skip recalculateMonthlySummaries for now - it's causing timeouts
            // The summaries will be recalculated by the normal flow later
            // $transactionService->recalculateMonthlySummaries($interestTransaction);
            // $transactionService->recalculateMonthlySummaries($buyTransaction);
            
            \Log::info('Skipping monthly summary recalculation to avoid timeout');
            
            // Create notification
            if (!$validated['fromModal']) {
                self::addMessage(
                    'Interest ReInvest created two transactions: Interest yield (#' . $interestTransaction->id . ') and Buy (#' . $buyTransaction->id . ')',
                    'success',
                    '',
                    '',
                    true
                );
            }
            
            \Log::info('Handler complete', [
                'interest_id' => $interestTransaction->id,
                'buy_id' => $buyTransaction->id,
            ]);
            
            // Return the Buy transaction as the "main" one (it has the shares)
            $buyTransaction->loadDetails();
            return response()->json([
                'transaction' => $buyTransaction,
                'interest_transaction' => $interestTransaction,
            ]);
        });
    }
}
