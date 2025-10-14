<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountEntityRequest;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayeeApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth:sanctum', 'verified'],
        ];
    }

    public function getList(Request $request): JsonResponse
    {
        /**
         * @get('/api/assets/payee')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        if ($request->get('q')) {
            $payees = Auth::user()
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
            if ($transactionType !== null && !array_key_exists($transactionType, config('transaction_types'))) {
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
                    'transaction_type_id',
                    '=',
                    config('transaction_types')[$transactionType]['id']
                )
                ->when($accountId, fn ($query) => $query->where(
                    "transaction_details_standard.account_{$accountDirection}_id",
                    '=',
                    $accountId
                ))
                ->groupBy("account_entities.id")
                ->orderByRaw('count(*) DESC')
                ->limit(10)
                ->get()
                ->pluck('id');

            // Hydrate models
            $payees = AccountEntity::findMany($payeeIds);
        } else {
            // Set payees to be empty
            $payees = collect();
        }

        return response()->json($payees, Response::HTTP_OK);
    }

    public function getPayeeDefaultSuggestion()
    {
        /**
         * @get('/api/assets/get_default_category_suggestion')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $baseQueryFrom = DB::table('transaction_items')
            ->join(
                'transactions',
                'transactions.id',
                '=',
                'transaction_items.transaction_id'
            )
            ->join(
                'transaction_details_standard',
                'transaction_details_standard.id',
                '=',
                'transactions.config_id'
            )
            ->join(
                'categories',
                'categories.id',
                '=',
                'transaction_items.category_id'
            )
            ->join(
                'account_entities',
                'account_entities.id',
                '=',
                'transaction_details_standard.account_from_id'
            )
            ->join(
                'payees',
                'payees.id',
                '=',
                'account_entities.config_id'
            )
            ->where('categories.user_id', Auth::user()->id)
            ->where('transactions.user_id', Auth::user()->id)
            ->where('account_entities.user_id', Auth::user()->id)
            ->where('categories.active', true) // Only active category can be recommended
            ->whereNull('payees.category_id') // No category set
            ->whereNull('payees.category_suggestion_dismissed') // Suggestion was not dismissed yet
            ->where('account_entities.config_type', 'payee')
            ->where('account_entities.active', true) // Only active payee can get recommendation
            ->select([
                'transaction_details_standard.account_from_id as payee_id',
                'categories.id as category_id',
            ]);

        $baseQuery = DB::table('transaction_items')
            ->join(
                'transactions',
                'transactions.id',
                '=',
                'transaction_items.transaction_id'
            )
            ->join(
                'transaction_details_standard',
                'transaction_details_standard.id',
                '=',
                'transactions.config_id'
            )
            ->join(
                'categories',
                'categories.id',
                '=',
                'transaction_items.category_id'
            )
            ->join(
                'account_entities',
                'account_entities.id',
                '=',
                'transaction_details_standard.account_to_id'
            )
            ->join(
                'payees',
                'payees.id',
                '=',
                'account_entities.config_id'
            )
            ->where('categories.user_id', Auth::user()->id) // Only for authenticated user
            ->where('categories.active', true) // Only active category can be recommended
            ->whereNull('payees.category_id') // No category set
            ->whereNull('payees.category_suggestion_dismissed') // Suggestion was not dismissed yet
            ->where('account_entities.config_type', 'payee')
            ->where('account_entities.active', true) // Only active payee can get recommendation
            ->select([
                'transaction_details_standard.account_to_id as payee_id',
                'categories.id as category_id',
            ])
            ->unionAll($baseQueryFrom);

        $data = DB::query()
            ->fromSub($baseQuery, 'base')
            ->select([
                'payee_id',
                'category_id',
            ])
            ->selectRaw('count(*) as transactions')
            ->groupBy([
                'payee_id',
                'category_id',
            ])
            ->get();

        // Calculate total by payees
        $payees = $data
            ->groupBy('payee_id')
            ->map(fn ($payee) => [
                'payee_id' => $payee->first()->payee_id,
                'sum' => $payee->sum('transactions'),
                'max' => $payee->max('transactions'),
                'max_category_id' => $payee->firstWhere('transactions', $payee->max('transactions'))->category_id,
            ])
            // Minimum required transactions to calculate with payee
            // TODO: make this dynamic, e.g based on average or mean
            ->filter(fn ($value) => $value['sum'] > 5)
            // Only where maximum is significant (at least half of all items)
            // TODO: make this dynamic
            ->filter(fn ($value) => $value['max'] / $value['sum'] > .5);

        if ($payees->count() === 0) {
            return response('', Response::HTTP_OK);
        }

        $payee = $payees->random();

        $payee['payee'] = AccountEntity::find($payee['payee_id'])->name;
        $payee['category'] = Category::find($payee['max_category_id'])->full_name;

        return response($payee, Response::HTTP_OK);
    }

    /**
     * @throws AuthorizationException
     */
    public function acceptPayeeDefaultCategorySuggestion(AccountEntity $accountEntity, Category $category)
    {
        /**
         * @get('/api/assets/accept_default_category_suggestion/{accountEntity}/{category}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        Gate::authorize('update', $accountEntity);

        $accountEntity->load(['config']);
        $accountEntity->config->category_id = $category->id;
        $accountEntity->config->save();

        return Response::HTTP_OK;
    }

    /**
     * @throws AuthorizationException
     */
    public function dismissPayeeDefaultCategorySuggestion(AccountEntity $accountEntity)
    {
        /**
         * @get('/api/assets/dismiss_default_category_suggestion/{accountEntity}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        Gate::authorize('update', $accountEntity);

        $accountEntity->load(['config']);
        $accountEntity->config->category_suggestion_dismissed = Carbon::now();
        $accountEntity->config->save();

        return Response::HTTP_OK;
    }

    public function storePayee(AccountEntityRequest $request)
    {
        /**
         * @post('/api/assets/payee')
         * @name('api.payee.store')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        Gate::authorize('create', AccountEntity::class);

        $validated = $request->validated();
        $validated['user_id'] = Auth::user()->id;

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
         * @get('/api/assets/payee/similar')
         * @name('api.payee.similar')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $query = Str::lower($request->get('query'));
        $withActive = $request->get('withActive');

        // Get all payees of the user
        $payees = Auth::user()
            ->payees()
            ->when($withActive, fn ($query) => $query->where('active', true))
            ->get(['id', 'name', 'active']);

        // Filter payees by similarity to query
        $payees = $payees->map(function ($payee) use ($query) {
            similar_text($query, Str::lower($payee->name), $percentage);
            $payee->percentage = $percentage;

            return $payee;
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
     *
     * @param  AccountEntity  $accountEntity
     * @return JsonResponse
     */
    public function getItem(AccountEntity $accountEntity): JsonResponse
    {
        /**
         * @get('/api/assets/payee/{accountEntity}')
         * @middlewares('api', 'auth:sanctum', 'verified')
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
}
