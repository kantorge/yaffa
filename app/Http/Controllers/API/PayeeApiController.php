<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Requests\AccountEntityRequest;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Services\PayeeCategoryStatsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayeeApiController extends Controller implements HasMiddleware
{
    public function __construct(private PayeeCategoryStatsService $payeeCategoryStatsService)
    {
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    /**
     * Get a list of payees with optional search and contextual filters.
     */
    public function getList(Request $request): JsonResponse
    {
        /**
         * @get("/api/v1/payees")
         * @name("api.v1.payees.index")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        if ($request->get('q')) {
            $payees = $request->user()
                ->payees()
                ->when($request->missing('withInactive'), function ($query) {
                    $query->active();
                })
                ->when($request->get('q'), function ($query) use ($request) {
                    $query->where('name', 'LIKE', '%' . $request->get('q') . '%');
                })
                ->orderBy('name')
                ->take(10)
                ->get();
        } elseif ($request->get('account_entity_id')) {
            // Account and transaction type is expected to be present
            $accountId = $request->get('account_entity_id');

            $accountDirection = ($request->get('account_type') === 'from' ? 'to' : 'from');
            $payeeDirection = ($request->get('account_type') === 'from' ? 'from' : 'to');

            $transactionType = $request->get('transaction_type', null);
            if ($transactionType !== null && TransactionTypeEnum::tryFrom($transactionType) === null) {
                // If transaction type is provided but not valid, return a bad request response
                return response()->json(
                    [
                        'message' => 'The transaction_type parameter is required and must be valid.',
                    ],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $payeeIds = DB::table('transactions')
                ->join(
                    'transaction_details_standard',
                    'transaction_details_standard.id',
                    '=',
                    'transactions.config_id'
                )
                ->join(
                    'account_entities',
                    'account_entities.id',
                    '=',
                    "transaction_details_standard.account_{$payeeDirection}_id"
                )
                ->select('account_entities.id')
                ->when($request->missing('withInactive'), function ($query) {
                    $query->where('account_entities.active', true);
                })
                ->where('transactions.user_id', $request->user()->id)
                ->where('account_entities.user_id', $request->user()->id)
                ->where(
                    // TODO: fallback to query without this, if no results are found
                    'transaction_type',
                    '=',
                    $transactionType
                )
                ->when($accountId, fn ($query) => $query->where(
                    "transaction_details_standard.account_{$accountDirection}_id",
                    '=',
                    $accountId
                ))
                ->groupBy("account_entities.id")
                ->orderByRaw('count(*) DESC')
                ->limit(10)
                ->pluck('id');

            // Hydrate models
            $payees = AccountEntity::findMany($payeeIds);
        } else {
            // Set payees to be empty
            $payees = collect();
        }

        return response()->json($payees, Response::HTTP_OK);
    }

    /**
     * Get the default category suggestion for payees.
     */
    public function getPayeeDefaultSuggestion(Request $request): Response
    {
        /**
         * @get("/api/v1/payees/category-suggestions/default")
         * @name("api.v1.payees.category-suggestions.default")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        $payeeSuggestion = $this->payeeCategoryStatsService->getDefaultSuggestion($request->user());

        if ($payeeSuggestion === null) {
            return response()->noContent(Response::HTTP_OK);
        }

        return response($payeeSuggestion, Response::HTTP_OK);
    }

    /**
     * @throws AuthorizationException
     */
    public function acceptPayeeDefaultCategorySuggestion(AccountEntity $accountEntity, Category $category): Response
    {
        /**
         * @post("/api/v1/payees/{accountEntity}/category-suggestions/accept/{category}")
         * @name("api.v1.payees.category-suggestions.accept")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        Gate::authorize('update', $accountEntity);

        $accountEntity->load(['config']);
        if (! $accountEntity->config instanceof Payee) {
            return response()->noContent(Response::HTTP_BAD_REQUEST);
        }

        $accountEntity->config->category_id = $category->id;
        $accountEntity->config->save();

        return response()->noContent(Response::HTTP_OK);
    }

    /**
     * @throws AuthorizationException
     */
    public function dismissPayeeDefaultCategorySuggestion(AccountEntity $accountEntity): Response
    {
        /**
         * @post("/api/v1/payees/{accountEntity}/category-suggestions/dismiss")
         * @name("api.v1.payees.category-suggestions.dismiss")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        Gate::authorize('update', $accountEntity);

        $accountEntity->load(['config']);
        if (! $accountEntity->config instanceof Payee) {
            return response()->noContent(Response::HTTP_BAD_REQUEST);
        }

        $accountEntity->config->category_suggestion_dismissed = now();
        $accountEntity->config->save();

        return response()->noContent(Response::HTTP_OK);
    }

    /**
     * Create a new payee.
     */
    public function storePayee(AccountEntityRequest $request)
    {
        /**
         * @post("/api/v1/payees")
         * @name("api.v1.payees.store")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        Gate::authorize('create', AccountEntity::class);

        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;

        $newPayee = new AccountEntity($validated);

        $payeeConfig = Payee::create($validated['config']);
        $newPayee->config()->associate($payeeConfig);

        $newPayee->push();

        return $newPayee;
    }

    /**
     * Get existing payees that are similar to the given name.
     * Optionally limit search to active or inactive payees.
     */
    public function getSimilarPayees(Request $request): JsonResponse
    {
        /**
         * @get("/api/v1/payees/similar")
         * @name("api.v1.payees.similar")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        $query = Str::lower($request->get('query'));
        $withActive = $request->get('withActive');

        // Get all payees of the user
        $payees = $request->user()
            ->payees()
            ->when($withActive, fn ($query) => $query->where('active', true))
            ->get(['id', 'name', 'active']);

        // Filter payees by similarity to query
        $payees = $payees->map(function ($payee) use ($query) {
            similar_text($query, Str::lower($payee->name), $percentage);

            return [
                'id' => $payee->id,
                'name' => $payee->name,
                'active' => $payee->active,
                'percentage' => $percentage,
            ];
        })
            ->sortByDesc('percentage')
            ->take(5)
            ->values();

        // Return JSON response with payees
        return response()
            ->json(
                $payees,
                Response::HTTP_OK
            );
    }

    /**
     * Get the payee entity and main attributes for the given id
     */
    public function getItem(AccountEntity $accountEntity): JsonResponse
    {
        /**
         * @get("/api/assets/payee/{accountEntity}")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        Gate::authorize('view', $accountEntity);

        $accountEntity->load([
            'config',
            'config.category',
            'preferredCategories',
            'deferredCategories',
        ]);

        return response()
            ->json(
                $accountEntity,
                Response::HTTP_OK
            );
    }

    /**
     * Update an existing payee
     *
     * @throws AuthorizationException
     */
    public function updatePayee(AccountEntityRequest $request, AccountEntity $accountEntity): JsonResponse
    {
        /**
         * @patch('/api/assets/payee/{accountEntity}')
         * @name('api.payee.update')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        Gate::authorize('update', $accountEntity);

        $validated = $request->validated();

        $accountEntity->load(['config']);
        $accountEntity->fill($validated);
        $accountEntity->config->fill($validated['config']);

        $accountEntity->push();

        // Reload to get fresh data
        $accountEntity->load([
            'config',
            'config.category',
        ]);

        return response()
            ->json(
                $accountEntity,
                Response::HTTP_OK
            );
    }
}
