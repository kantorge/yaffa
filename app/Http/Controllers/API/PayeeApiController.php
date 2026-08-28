<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountEntityRequest;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Services\PayeeCategoryStatsService;
use App\Services\PayeePersistenceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
                'getList', 'getPayeeDefaultSuggestion', 'getSimilarPayees', 'getItem',
            ])]
#[Middleware('abilities:write', only: [
                'acceptPayeeDefaultCategorySuggestion', 'dismissPayeeDefaultCategorySuggestion',
                'storePayee', 'updatePayee',
            ])]
class PayeeApiController extends Controller
{
    public function __construct(
        private PayeeCategoryStatsService $payeeCategoryStatsService,
        private PayeePersistenceService $payeePersistenceService,
    ) {}

    /**
     * List payees
     *
     * Returns payees matching a search term, or ranked by usage for a given
     * account and transaction type/direction when no search term is given.
     */
    public function getList(Request $request): JsonResponse
    {
        if ($request->query('q')) {
            $payees = $request->user()
                ->payees()
                ->when($request->missing('withInactive'), function ($query) {
                    $query->active();
                })
                ->when($request->query('q'), function ($query) use ($request) {
                    $query->where('name', 'LIKE', '%' . $request->query('q') . '%');
                })
                ->orderBy('name')
                ->take(10)
                ->get();
        } elseif ($request->query('account_entity_id')) {
            // Account and transaction type is expected to be present
            $accountId = $request->query('account_entity_id');

            $accountDirection = ($request->query('account_type') === 'from' ? 'to' : 'from');
            $payeeDirection = ($request->query('account_type') === 'from' ? 'from' : 'to');

            $transactionType = $request->query('transaction_type', null);
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
     * Get default payee category suggestion
     */
    public function getPayeeDefaultSuggestion(Request $request): Response
    {
        $payeeSuggestion = $this->payeeCategoryStatsService->getDefaultSuggestion($request->user());

        if ($payeeSuggestion === null) {
            return response()->noContent(Response::HTTP_OK);
        }

        return response($payeeSuggestion, Response::HTTP_OK);
    }

    /**
     * Accept payee category suggestion
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'accountEntity')]
    public function acceptPayeeDefaultCategorySuggestion(AccountEntity $accountEntity, Category $category): Response
    {
        Gate::authorize('view', $category);

        $accountEntity->load(['config']);
        if (! $accountEntity->config instanceof Payee) {
            return response()->noContent(Response::HTTP_BAD_REQUEST);
        }

        $accountEntity->config->category_id = $category->id;
        $accountEntity->config->save();

        return response()->noContent(Response::HTTP_OK);
    }

    /**
     * Dismiss payee category suggestion
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'accountEntity')]
    public function dismissPayeeDefaultCategorySuggestion(AccountEntity $accountEntity): Response
    {
        $accountEntity->load(['config']);
        if (! $accountEntity->config instanceof Payee) {
            return response()->noContent(Response::HTTP_BAD_REQUEST);
        }

        $accountEntity->config->category_suggestion_dismissed = now();
        $accountEntity->config->save();

        return response()->noContent(Response::HTTP_OK);
    }

    /**
     * Create a payee
     *
     * @throws AuthorizationException
     */
    #[Authorize('create', AccountEntity::class)]
    public function storePayee(AccountEntityRequest $request): JsonResponse
    {
        $newPayee = $this->payeePersistenceService->store($request);
        $newPayee->load($this->payeeResponseRelations());

        return response()->json($newPayee, Response::HTTP_CREATED);
    }

    /**
     * Find similar payees
     *
     * Returns existing payees ranked by name similarity to the given query.
     * Optionally limit the search to active or inactive payees.
     */
    public function getSimilarPayees(Request $request): JsonResponse
    {
        $query = Str::lower($request->query('query'));
        $withActive = $request->query('withActive');

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
     * Get a payee
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'accountEntity')]
    public function getItem(AccountEntity $accountEntity): JsonResponse
    {
        $accountEntity->load($this->payeeResponseRelations());

        return response()
            ->json(
                $accountEntity,
                Response::HTTP_OK
            );
    }

    /**
     * Update a payee
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'accountEntity')]
    public function updatePayee(AccountEntityRequest $request, AccountEntity $accountEntity): JsonResponse
    {
        if (! $accountEntity->isPayee()) {
            return response()->json([
                'message' => __('Payee not found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $accountEntity = $this->payeePersistenceService->update(
            $accountEntity,
            $request,
            $request->boolean('simplified'),
        );

        // Reload to get fresh data
        $accountEntity->load($this->payeeResponseRelations());

        return response()
            ->json(
                $accountEntity,
                Response::HTTP_OK
            );
    }

    /**
     * @return array<int, string>
     */
    private function payeeResponseRelations(): array
    {
        return [
            'config',
            'config.category',
            'config.category.parent',
            'preferredCategories',
            'deferredCategories',
        ];
    }
}
