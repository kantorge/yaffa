<?php

namespace App\Http\Controllers\API;

use App\Casts\MoneyCast;
use App\Enums\TransactionType;
use App\Events\TransactionCreated;
use App\Events\TransactionDeleted;
use App\Events\TransactionUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\FindTransactionsRequest;
use App\Http\Requests\API\GetScheduledItemsRequest;
use App\Http\Requests\TransactionRequest;
use App\Http\Traits\CurrencyTrait;
use App\Models\Account;
use App\Models\AiDocument;
use App\Models\Budget;
use App\Models\Currency;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use App\Models\User;
use App\Services\CategoryLearningService;
use App\Services\CategoryService;
use App\Services\TransactionItemMergeService;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Recurr\Exception\InvalidArgument;
use Recurr\Exception\InvalidWeekday;
use RuntimeException;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
    'getItem', 'getScheduledItems', 'findTransactions',
])]
#[Middleware('abilities:write', only: [
    'reconcile', 'storeStandard', 'storeInvestment', 'updateStandard',
    'updateInvestment', 'skipScheduleInstance', 'destroy',
])]
class TransactionApiController extends Controller
{
    use CurrencyTrait;

    private CategoryService $categoryService;

    public function __construct(
        private TransactionItemMergeService $mergeService,
    ) {
        $this->categoryService = new CategoryService();
    }

    /**
     * Reconcile a transaction
     *
     * Accepts { reconciled: true|false } in the request body to mark the
     * transaction as reconciled or unreconciled.
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'transaction')]
    public function reconcile(Request $request, Transaction $transaction): JsonResponse
    {
        $validated = $request->validate([
            'reconciled' => ['required', 'boolean'],
        ]);

        $transaction->reconciled = $validated['reconciled'];
        $transaction->save();

        return response()->json([
            'transaction' => $transaction,
        ], Response::HTTP_OK);
    }

    /**
     * Get a transaction
     */
    #[Authorize('view', 'transaction')]
    public function getItem(Transaction $transaction): JsonResponse
    {
        $transaction->loadDetails();

        return response()->json(
            [
                'transaction' => $transaction,
            ],
            Response::HTTP_OK
        );
    }

    /**
     * List scheduled transactions
     *
     * Returns scheduled transactions filtered by schedule type and optional
     * criteria such as account selection and categories.
     */
    public function getScheduledItems(GetScheduledItemsRequest $request): JsonResponse
    {
        // Only 'schedule' and 'none' remain meaningful now that the budget flag is gone (FR-1);
        // anything else (including the old 'any') is treated as 'none'.
        $type = $request->query('type', 'none');

        // Return empty response if categories are required, but not set or empty
        if ($request->has('category_required')
            && (!$request->has('categories') || !$request->input('categories'))) {
            return response()->json([], Response::HTTP_OK);
        }

        // Get list of requested categories
        // Ensure, that child categories are loaded for all parents
        $categories = $this->categoryService->getChildCategories($request);

        // Get the account selection properties
        $accountSelection = $request->query('accountSelection');
        $accountEntity = $request->query('accountEntity');

        // Category/tag breakdown per item is only rendered by the account-show and
        // schedules-report tables (via processTransaction()'s transaction_items -> categories/tags
        // derivation); the dashboard ScheduleCalendar widget never reads transaction_items, so it
        // opts out to skip these joins.
        $standardTransactionRelations = [
            'config',
            'config.accountFrom',
            'config.accountTo',
            'currency',
            'transactionSchedule',
        ];

        if (!$request->has('includeItemDetails') || $request->boolean('includeItemDetails')) {
            $standardTransactionRelations[] = 'transactionItems';
            $standardTransactionRelations[] = 'transactionItems.category';
            $standardTransactionRelations[] = 'transactionItems.tags';
        }

        // Get all standard transactions
        $standardTransactions = Transaction::with($standardTransactionRelations)
            ->where('user_id', $request->user()->id)
            ->where('schedule', $type === 'schedule')
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

        // Return empty collection if categories are required, or a category filter is active -
        // investment transactions structurally have no categorized items, so they can never match
        // a category filter (mirrors the same exclusion in TransactionApiController::findTransactions()).
        if ($request->has('category_required') || $categories->count() > 0) {
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
                ->where('schedule', $type === 'schedule')
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

        // FR-6: the merged schedules-report listing opts in explicitly via includeBudgets=1 - no
        // other caller (dashboard ScheduleCalendar, account show) requests it, so their type=schedule
        // fetches never see a Budget row, which they aren't shaped to handle (no transaction_schedule).
        $budgetRows = new Collection();

        if ($request->boolean('includeBudgets')) {
            // Budget::currency() lazy-loads account->config->currency (or user->baseCurrency(),
            // which re-queries regardless of eager-loading since it builds a fresh relation
            // query rather than reading a loaded collection) per row - eager-load the
            // account-scoped path and reuse the already-cached base currency for the
            // account-agnostic one instead of calling ->currency() per row for those.
            $baseCurrency = $this->getBaseCurrency();

            $budgetRows = Budget::with(['category', 'account.config.currency'])
                ->where('user_id', $request->user()->id)
                ->where('active', true)
                ->when($accountSelection === 'selected' && $accountEntity, fn ($query) => $query->where('account_id', $accountEntity))
                ->when($accountSelection === 'none', fn ($query) => $query->whereNull('account_id'))
                ->when($categories->count() > 0, fn ($query) => $query->whereIn('category_id', $categories->pluck('id')))
                ->get()
                ->map(fn (Budget $budget) => [
                    'id' => $budget->id,
                    'row_type' => 'budget',
                    'transaction_type' => $budget->transaction_type->value,
                    // Manually-built array, not a model's own toArray()/toJson() - stringify to
                    // match MoneyCast::serialize()'s decimal-string wire format explicitly.
                    'amount' => (string) $budget->amount->getAmount(),
                    'comment' => $budget->comment,
                    'category_id' => $budget->category_id,
                    // Shaped like Transaction::categories (built from transaction_items) so the
                    // shared category column renderer works unchanged for a Budget row too.
                    'categories' => [$budget->category],
                    'account_id' => $budget->account_id,
                    'transaction_currency' => ($budget->account_id ? $budget->currency() : null) ?? $baseCurrency,
                    // Synthetic, schedule-shaped period definition - a Budget has no next_date/
                    // automatic_recording (FR-4), which render blank via the same convention an
                    // empty category cell already uses (FR-6).
                    'transaction_schedule' => [
                        'start_date' => $budget->start_date->toDateString(),
                        'end_date' => $budget->end_date?->toDateString(),
                        'next_date' => null,
                        'frequency' => $budget->frequency,
                        'interval' => $budget->interval,
                        'count' => $budget->count,
                        'active' => $budget->active,
                    ],
                ]);
        }

        return response()->json(
            [
                'transactions' => $standardTransactions->concat($investmentTransactions)->concat($budgetRows),
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Search transactions
     *
     * Searches transactions by date range and related entities such as
     * accounts, categories, payees, and tags.
     */
    public function findTransactions(FindTransactionsRequest $request): JsonResponse
    {
        // A request without any search criteria will return an empty response to avoid loading all transactions
        if (!$request->hasAny([
            'date_from',
            'date_to',
            'accounts',
            'categories',
            'payees',
            'tags',
            'types',
            'investments',
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

        // Get list of requested categories
        // This also ensures that child categories are loaded for all parents
        $categories = $this->categoryService->getChildCategories($request);

        // Get standard transactions matching any provided criteria
        $standardQuery = Transaction::where('user_id', $user->id)
            ->where('schedule', false)
            ->byType('standard')
            ->when($request->has('date_from'), function ($query) use ($request) {
                $query->where('date', '>=', $request->validated('date_from'));
            })
            ->when($request->has('date_to'), function ($query) use ($request) {
                $query->where('date', '<=', $request->validated('date_to'));
            })
            ->when($request->has('accounts') && $request->validated('accounts'), function ($query) use ($request) {
                $query->whereIn('config_id', function ($query) use ($request) {
                    $query->select('id')
                        ->from('transaction_details_standard')
                        ->whereIn('account_from_id', $request->validated('accounts'))
                        ->orWhereIn('account_to_id', $request->validated('accounts'));
                });
            })
            ->when($request->has('payees') && $request->validated('payees'), function ($query) use ($request) {
                $query->whereIn('config_id', function ($query) use ($request) {
                    $query->select('id')
                        ->from('transaction_details_standard')
                        ->whereIn('account_from_id', $request->validated('payees'))
                        ->orWhereIn('account_to_id', $request->validated('payees'));
                });
            })
            ->when($request->has('categories') && $request->validated('categories'), function ($query) use ($categories) {
                $query->whereIn('id', function ($query) use ($categories) {
                    $query->select('transaction_id')
                        ->from('transaction_items')
                        ->whereIn('category_id', $categories->pluck('id'));
                });
            })
            ->when($request->has('tags') && $request->validated('tags'), function ($query) use ($request) {
                $query->whereIn('id', function ($query) use ($request) {
                    $query->select('transaction_id')
                        ->from('transaction_items')
                        ->whereIn('id', function ($query) use ($request) {
                            $query->select('transaction_item_id')
                                ->from('transaction_items_tags')
                                ->whereIn('tag_id', $request->validated('tags'));
                        });
                });
            })
            ->when($request->has('types') && $request->validated('types'), function ($query) use ($request) {
                $query->whereIn('transaction_type', $request->validated('types'));
            })
            // Investments are an investment-only concept: a standard transaction can never match one
            ->when($request->has('investments') && $request->validated('investments'), function ($query) {
                $query->whereRaw('1 = 0');
            });

        // Get investment transactions matching any provided criteria
        // This part of the query is run only if relevant search criteria is provided, and no other search criteria is provided
        if ($request->hasAny(['date_from', 'date_to', 'accounts', 'types', 'investments'])
            && !($request->hasAny(['categories', 'payees', 'tags']))) {
            $investmentQuery = Transaction::where('user_id', $user->id)
                ->where('schedule', false)
                ->byType('investment')
                ->when($request->has('date_from'), function ($query) use ($request) {
                    $query->where('date', '>=', $request->validated('date_from'));
                })
                ->when($request->has('date_to'), function ($query) use ($request) {
                    $query->where('date', '<=', $request->validated('date_to'));
                })
                ->when($request->has('accounts') && $request->validated('accounts'), function ($query) use ($request) {
                    $query->whereIn('config_id', function ($query) use ($request) {
                        $query->select('id')
                            ->from('transaction_details_investment')
                            ->whereIn('account_id', $request->validated('accounts'));
                    });
                })
                ->when($request->has('types') && $request->validated('types'), function ($query) use ($request) {
                    $query->whereIn('transaction_type', $request->validated('types'));
                })
                ->when($request->has('investments') && $request->validated('investments'), function ($query) use ($request) {
                    $query->whereIn('config_id', function ($query) use ($request) {
                        $query->select('id')
                            ->from('transaction_details_investment')
                            ->whereIn('investment_id', $request->validated('investments'));
                    });
                });
        } else {
            $investmentQuery = Transaction::where('user_id', $user->id)  // User ID is used for security reasons
                ->where('schedule', false)->byType('investment') // Pretend that we are searching for investment transactions
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

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRatesMap = $this->allCurrencyRatesByMonth();

        // Loop through all transactions and add the currency rate to the base currency
        // Also, calculate the amount in the base currency for the transaction and all its items, if applicable
        $transactions->map(function ($transaction) use ($baseCurrency, $allRatesMap) {
            // Keep the exact decimal rate for Money arithmetic below; currencyRateToBase
            // itself stays a plain numeric API field, as it always has been.
            $exactRate = $this->getLatestRateFromMap(
                $transaction->currency_id,
                $transaction->date,
                $allRatesMap,
                $baseCurrency->id
            ) ?? '1';
            $transaction->currencyRateToBase = (float) $exactRate;

            // Extend the optional amount_to and amount_from fields in the config
            if ($transaction->config instanceof TransactionDetailStandard) {
                if (! $transaction->config->amount_to->isZero()) {
                    $transaction->config->amount_to_base = $this->convertToBase(
                        $transaction->config->amount_to,
                        $baseCurrency,
                        $exactRate
                    );
                }
                if (! $transaction->config->amount_from->isZero()) {
                    $transaction->config->amount_from_base = $this->convertToBase(
                        $transaction->config->amount_from,
                        $baseCurrency,
                        $exactRate
                    );
                }
            }

            // Extend the amount field in the items
            $transaction->transactionItems->map(function ($item) use ($exactRate, $baseCurrency) {
                $item->amount_in_base = $this->convertToBase($item->amount, $baseCurrency, $exactRate);
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
     * Convert a Money amount into the user's base currency at the given rate, returning
     * a decimal string (matching the wire format of the cast-backed money fields this
     * value sits alongside in the response). Reuses the source amount's own scale for the
     * converted value, since no persisted column governs this transient, derived field.
     */
    private function convertToBase(Money $amount, Currency $baseCurrency, string $rate): string
    {
        $scale = $amount->getAmount()->getScale();
        $targetCurrency = MoneyCast::currencyFor($baseCurrency, $scale);

        return (string) $amount->convertedTo($targetCurrency, $rate, roundingMode: RoundingMode::HalfUp)->getAmount();
    }

    /**
     * Create a standard transaction
     */
    public function storeStandard(TransactionRequest $request): JsonResponse
    {
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

            if ($transaction->schedule) {
                $transactionSchedule = new TransactionSchedule(['transaction_id' => $transaction->id]);
                $transactionSchedule->fill($validated['schedule_config']);
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            // Runs in the same transaction as the transaction/schedule creation above,
            // so a failed catch-up (see handleSourceTransactionUpdates()) rolls back
            // the newly created transaction too, rather than leaving it committed
            // alongside a source schedule that never actually caught up.
            $this->handleSourceTransactionUpdates($validated, $request->user());

            return $transaction;
        });

        $this->mergeService->mergeIfEnabled($transaction);

        $categoryLearningSummary = $this->finalizeAiDocument($validated, $transaction, $request->user());

        // Generate an event for the new transaction
        event(new TransactionCreated($transaction));

        // Ensure that the transaction is loaded with all relations
        $transaction->loadDetails();

        return response()->json([
            'transaction' => $transaction,
            'category_learning_summary' => $categoryLearningSummary,
        ]);
    }

    /**
     * Create an investment transaction
     */
    public function storeInvestment(TransactionRequest $request): JsonResponse
    {
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

            // Runs in the same transaction as the transaction/schedule creation above,
            // so a failed catch-up (see handleSourceTransactionUpdates()) rolls back
            // the newly created transaction too, rather than leaving it committed
            // alongside a source schedule that never actually caught up.
            $this->handleSourceTransactionUpdates($validated, $request->user());

            return $transaction;
        });

        $categoryLearningSummary = $this->finalizeAiDocument($validated, $transaction, $request->user());

        // Generate an event for the new transaction
        event(new TransactionCreated($transaction));

        // Ensure that the transaction is loaded with all relations
        $transaction->loadDetails();

        return response()->json([
            'transaction' => $transaction,
            'category_learning_summary' => $categoryLearningSummary,
        ]);
    }

    /**
     * Update a standard transaction
     */
    #[Authorize('update', 'transaction')]
    public function updateStandard(TransactionRequest $request, Transaction $transaction): JsonResponse
    {
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

        if ($transaction->schedule) {
            // At this point, the schedule flag cannot be changed,
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
     * Update an investment transaction
     */
    #[Authorize('update', 'transaction')]
    public function updateInvestment(TransactionRequest $request, Transaction $transaction): JsonResponse
    {
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
            // At this point, the schedule flag cannot be changed,
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
     * Skip a scheduled transaction
     *
     * Skips the next scheduled occurrence of a recurring transaction.
     */
    #[Authorize('update', 'transaction')]
    public function skipScheduleInstance(Transaction $transaction): JsonResponse
    {
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
     * Delete a transaction
     */
    #[Authorize('delete', 'transaction')]
    public function destroy(Transaction $transaction): JsonResponse
    {
        // Authorize the deletion of the transaction for the owner
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
    private function handleSourceTransactionUpdates(array $validated, User $user): void
    {
        // Adjust source transaction schedule, if entering schedule instance
        // The reference is passed as the ID
        if ($validated['action'] === 'enter') {
            $sourceTransaction = Transaction::query()
                ->where('id', $validated['id'])
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->load(['transactionSchedule']);

            Gate::authorize('update', $sourceTransaction);

            $originalScheduleConfig = $sourceTransaction->transactionSchedule->attributesToArray();

            if ($validated['catch_up_schedule'] ?? false) {
                if (!$sourceTransaction->transactionSchedule->catchUpToDate()) {
                    throw new RuntimeException(__('Unable to catch up the schedule to the current date.'));
                }
            } else {
                $sourceTransaction->transactionSchedule->skipNextInstance();
            }

            // This also triggers a TransactionUpdated event for the source transaction
            event(new TransactionUpdated($sourceTransaction, [
                'schedule_config' => $originalScheduleConfig,
            ]));

            return;
        }

        // Adjust source transaction schedule, if creating a new schedule clone
        if ($validated['action'] === 'replace') {
            $sourceTransaction = Transaction::query()
                ->where('id', $validated['id'])
                ->where('user_id', $user->id)
                ->firstOrFail()
                ->load(['transactionSchedule']);

            Gate::authorize('update', $sourceTransaction);

            $originalScheduleConfig = $sourceTransaction->transactionSchedule->attributesToArray();

            $sourceTransaction->transactionSchedule->fill($validated['original_schedule_config']);

            // next_date isn't necessarily present in original_schedule_config (the
            // "close out the old schedule" flow always omits/nulls it), so a stale
            // value from before this pattern change can survive the fill() above.
            // Since next_date is trusted verbatim wherever a transaction is recorded
            // (see TransactionSchedule::occursOn()), clear it here if it no longer
            // matches the (possibly just-changed) recurrence rule.
            $nextDate = $sourceTransaction->transactionSchedule->next_date;
            if ($nextDate) {
                try {
                    if (!$sourceTransaction->transactionSchedule->occursOn($nextDate)) {
                        $sourceTransaction->transactionSchedule->next_date = null;
                    }
                } catch (InvalidArgument|InvalidWeekday|Exception) {
                    $sourceTransaction->transactionSchedule->next_date = null;
                }
            }

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
    private function finalizeAiDocument(array $validated, Transaction $transaction, User $user): ?array
    {
        if (($validated['action'] ?? null) !== 'finalize' || empty($validated['ai_document_id'] ?? null)) {
            Log::debug('Skipping AI document finalization due to missing or invalid action or AI document ID', [
                'action' => $validated['action'] ?? null,
                'ai_document_id' => $validated['ai_document_id'] ?? null,
            ]);
            return null;
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
            return null;
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
            return $this->updateCategoryLearning($transaction, $user, $validated['items']);
        }

        return [
            'created' => 0,
            'incremented' => 0,
            'updated' => 0,
        ];
    }

    /**
     * Update CategoryLearning from user-submitted transaction items.
     */
    private function updateCategoryLearning(
        Transaction $transaction,
        User $user,
        array $submittedItems = []
    ): array {
        // Only applicable for standard transactions with items
        if ($transaction->config_type !== 'standard') {
            return [
                'created' => 0,
                'incremented' => 0,
                'updated' => 0,
            ];
        }

        $learningService = new CategoryLearningService($user);
        $summary = [
            'created' => 0,
            'incremented' => 0,
            'updated' => 0,
        ];

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
            $result = $learningService->recordCategorySelection($description, (int) $categoryId);

            if (array_key_exists($result, $summary)) {
                $summary[$result]++;
            }
        }

        return $summary;
    }
}
