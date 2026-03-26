<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionType;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Events\TransactionCreated;
use App\Events\TransactionDeleted;
use App\Events\TransactionUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Http\Traits\CurrencyTrait;
use App\Models\Account;
use App\Models\AiDocument;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\CategoryLearningService;
use App\Services\TransactionItemMergeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionApiController extends Controller implements HasMiddleware
{
    use CurrencyTrait;

    private CategoryService $categoryService;

    public function __construct(
        private TransactionItemMergeService $mergeService,
    ) {
        $this->categoryService = new CategoryService();
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    /**
     * V1: PATCH /api/v1/transactions/{transaction}/reconciliation
     * Accepts { reconciled: true|false } in request body.
     *
     * @throws AuthorizationException
     */
    public function reconcile(Request $request, Transaction $transaction): JsonResponse
    {
        Gate::authorize('update', $transaction);

        $validated = $request->validate([
            'reconciled' => ['required', 'boolean'],
        ]);

        $transaction->reconciled = $validated['reconciled'];
        $transaction->save();

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * V1: GET /api/v1/transactions/scheduled-items?type=...
     */

    public function getItem(Transaction $transaction): JsonResponse
    {
        /**
         * @get("/api/v1/transactions/{transaction}")
         * @name("api.v1.transactions.show")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        Gate::authorize('view', $transaction);

        $transaction->loadDetails();

        return response()->json(
            [
                'transaction' => $transaction,
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Get scheduled transactions filtered by schedule type and optional criteria.
     */
    public function getScheduledItems(Request $request): JsonResponse
    {
        $type = $request->query('type', 'any');

        /**
         * @get("/api/v1/transactions/scheduled-items?type=...")
         * @middlewares("api", "auth:sanctum", "verified")
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
            'currency',
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
                            $query->where('transaction_type', TransactionType::WITHDRAWAL->value)
                                ->whereHasMorph(
                                    'config',
                                    TransactionDetailStandard::class,
                                    fn ($query) => $query->whereNull('account_from_id')
                                );
                        })
                        // Or deposit with empty account_to_id
                        ->orWhere(function ($query) {
                            $query->where('transaction_type', TransactionType::DEPOSIT->value)
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
                'currency',
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

    /**
     * Search transactions by date range and related entities.
     */
    public function findTransactions(Request $request): JsonResponse
    {
        /**
         * @get("/api/transactions")
         * @middlewares("api", "auth:sanctum", "verified")
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
                'currency',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
                'transactionItems.category.parent',
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
                'currency',
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
            if ($transaction->config instanceof TransactionDetailStandard) {
                if ($transaction->config->amount_to) {
                    $transaction->config->amount_to_base = $transaction->config->amount_to * $transaction->currencyRateToBase;
                }
                if ($transaction->config->amount_from) {
                    $transaction->config->amount_from_base = $transaction->config->amount_from * $transaction->currencyRateToBase;
                }
            }

            // Extend the amount field in the items
            $transaction->transactionItems->map(function ($item) use ($transaction) {
                $item->amount_in_base = $item->amount * $transaction->currencyRateToBase;
            });

            return $transaction;
        });

        return response()->json(
            [
                'data' => $transactions,
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Create a standard transaction.
     */
    public function storeStandard(TransactionRequest $request): JsonResponse
    {
        /**
         * @post("/api/v1/transactions/standard")
         * @name("api.v1.transactions.store-standard")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated, $request) {
            // Create the configuration first
            $transactionDetails = TransactionDetailStandard::create($validated['config']);

            $transaction = new Transaction($validated);
            $transaction->user_id = $request->user()->id;
            $transaction->config()->associate($transactionDetails);
            $transaction->push();

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

            $transaction->push();

            if ($transaction->schedule || $transaction->budget) {
                $transactionSchedule = new TransactionSchedule(['transaction_id' => $transaction->id]);
                $transactionSchedule->fill($validated['schedule_config']);
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            return $transaction;
        });

        $this->mergeService->mergeIfEnabled($transaction);

        $this->handleSourceTransactionUpdates($validated);

        $this->finalizeAiDocument($validated, $transaction, $request->user());

        // Generate an event for the new transaction
        event(new TransactionCreated($transaction));

        // Ensure that the transaction is loaded with all relations
        $transaction->loadDetails();

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    /**
     * Create an investment transaction.
     */
    public function storeInvestment(TransactionRequest $request): JsonResponse
    {
        /**
         * @post("/api/v1/transactions/investment")
         * @name("api.v1.transactions.store-investment")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        $validated = $request->validated();

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

        $this->finalizeAiDocument($validated, $transaction, $request->user());

        // Generate an event for the new transaction
        event(new TransactionCreated($transaction));

        // Ensure that the transaction is loaded with all relations
        $transaction->loadDetails();

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    /**
     * Update an existing standard transaction.
     */
    public function updateStandard(TransactionRequest $request, Transaction $transaction): JsonResponse
    {
        /**
         * @patch("/api/v1/transactions/standard/{transaction}")
         * @name("api.v1.transactions.update-standard")
         * @middlewares("api", "auth:sanctum", "verified")
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
        // Transaction items are not stored as changes, as they are not triggering updates to monthly summaries

        // Save entire transaction
        $transaction->push();

        $this->mergeService->mergeIfEnabled($transaction);

        // Generate an event for the updated transaction
        event(new TransactionUpdated($transaction, $attributeChanges));

        // Ensure that the transaction is loaded with all relations
        $transaction->loadDetails();

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    /**
     * Update an existing investment transaction.
     */
    public function updateInvestment(TransactionRequest $request, Transaction $transaction): JsonResponse
    {
        /**
         * @patch("/api/v1/transactions/investment/{transaction}")
         * @name("api.v1.transactions.update-investment")
         * @middlewares("api", "auth:sanctum", "verified")
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

    /**
     * Skip the next scheduled occurrence of a transaction.
     */
    public function skipScheduleInstance(Transaction $transaction): JsonResponse
    {
        /**
         * @patch("/api/v1/transactions/{transaction}/skip")
         * @name("api.v1.transactions.skip")
         * @middlewares("api", "auth:sanctum", "verified")
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
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        /**
         * @delete("/api/v1/transactions/{transaction}")
         * @name("api.v1.transactions.destroy")
         * @middlewares("web", "auth", "verified")
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
     */
    private function handleSourceTransactionUpdates(array $validated): void
    {
        // Adjust source transaction schedule, if entering schedule instance
        // The reference is passed as the ID
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
     * Finalize an AI document after transaction creation.
     */
    private function finalizeAiDocument(array $validated, Transaction $transaction, User $user): void
    {
        if ($validated['action'] !== 'finalize' || empty($validated['ai_document_id'])) {
            Log::debug('Skipping AI document finalization due to missing or invalid action or AI document ID', [
                'action' => $validated['action'] ?? null,
                'ai_document_id' => $validated['ai_document_id'] ?? null,
            ]);
            return;
        }

        $aiDocument = AiDocument::query()
            ->where('id', $validated['ai_document_id'])
            ->where('user_id', $user->id)
            ->first();

        // Silently return if the AI document is not found
        if (! $aiDocument) {
            Log::debug('AI document not found for finalization', [
                'ai_document_id' => $validated['ai_document_id'],
                'user_id' => $user->id,
            ]);
            return;
        }

        $aiDocument->status = 'finalized';
        if (! $aiDocument->processed_at) {
            $aiDocument->processed_at = now();
        }
        $aiDocument->save();

        if ($transaction->ai_document_id !== $aiDocument->id) {
            $transaction->ai_document_id = $aiDocument->id;
            // The update of the reference should not trigger update-based events
            $transaction->saveQuietly();
        }

        // Update CategoryLearning for accepted recommendations if there are any
        if (! empty($validated['items']) && is_array($validated['items'])) {
            $this->updateCategoryLearning($transaction, $user, $validated['items']);
        }
    }

    /**
     * Update CategoryLearning from user-submitted transaction items.
     */
    private function updateCategoryLearning(
        Transaction $transaction,
        User $user,
        array $submittedItems = []
    ): void {
        // Only applicable for standard transactions with items
        if ($transaction->config_type !== 'standard') {
            return;
        }

        $learningService = new CategoryLearningService($user);

        // Process each submitted item where learning is enabled
        foreach ($submittedItems as $submittedItem) {
            // Learning is enabled by default, skip only if explicitly disabled
            if (! ($submittedItem['learnRecommendation'] ?? true)) {
                continue;
            }

            $categoryId = $submittedItem['category_id'] ?? null;
            $description = $submittedItem['description'] ?? null;

            // Need both category and description to learn
            if (! $categoryId || ! $description) {
                continue;
            }

            // Use service method to record the learning
            $learningService->recordCategorySelection($description, (int) $categoryId);
        }
    }
}
