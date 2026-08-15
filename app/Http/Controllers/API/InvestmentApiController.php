<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\UpdateInvestmentProviderSettingsRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Traits\ScheduleTrait;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Services\InvestmentProviderSettingsResolver;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class InvestmentApiController extends Controller implements HasMiddleware
{
    use ScheduleTrait;

    public function __construct(
        protected InvestmentService $investmentService,
        protected InvestmentProviderSettingsResolver $providerSettingsResolver
    ) {
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
            new Middleware('abilities:read', only: [
                'index', 'getInvestmentDetails', 'getPriceHistory', 'getInvestmentsWithTimeline', 'getDisplayData',
            ]),
            new Middleware('abilities:write', only: [
                'patchActive', 'updateProviderSettings', 'destroy',
            ]),
        ];
    }

    /**
     * List investments
     *
     * Returns the user's investments with optional filtering and sorting.
     *
     * Supported query parameters:
     * - active: filter by active status (1 or 0)
     * - query: search string to match against name, symbol, or ISIN (alias: q)
     * - currency_id: filter by currency ID
     * - limit: maximum number of results to return (default 10)
     * - sort_by: field to sort by (name, symbol, isin, active, created_at), default is name
     * - sort_order: asc or desc, default is asc
     */
    public function index(Request $request): JsonResponse
    {
        // Whitelist of valid sortable columns
        $validSortColumns = ['name', 'symbol', 'isin', 'active', 'created_at'];
        $sortBy = $request->query('sort_by', 'name');
        $sortOrder = $request->query('sort_order', 'asc');

        // Validate sort_by parameter
        if (!in_array($sortBy, $validSortColumns, true)) {
            $sortBy = 'name';
        }

        // Validate sort_order parameter
        if (!in_array(Str::lower($sortOrder), ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }

        // 'q' is accepted as an alias for 'query', matching the search parameter used
        // by the other list endpoints (categories, payees, accounts, tags). Checked with
        // is_string() rather than ?: so that a literal "0" isn't treated as absent, and an
        // array value (e.g. ?query[]=x) can't reach Str::lower() below.
        $queryParam = $request->query('query');
        $searchTerm = is_string($queryParam) && $queryParam !== '' ? $queryParam : $request->query('q');
        $searchTerm = is_string($searchTerm) ? $searchTerm : null;

        $investments = $request->user()
            ->investments()
            ->when(
                $request->has('active'),
                fn ($query) =>
                $query->where('active', $request->query('active'))
            )
            ->when(
                $searchTerm !== null,
                fn ($query) =>
                // The query string is searched in: name, symbol, ISIN
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw(
                        'LOWER(name) LIKE ?',
                        ['%' . Str::lower($searchTerm) . '%']
                    )
                        ->orWhereRaw(
                            'LOWER(symbol) LIKE ?',
                            ['%' . Str::lower($searchTerm) . '%']
                        )
                        ->orWhereRaw(
                            'LOWER(isin) LIKE ?',
                            ['%' . Str::lower($searchTerm) . '%']
                        );
                })
            )
            ->when(
                $request->query('currency_id'),
                fn ($query) =>
                $query->where('currency_id', '=', $request->query('currency_id'))
            )
            ->orderBy($sortBy, $sortOrder)
            ->take($request->query('limit', 10))
            ->get();

        return response()->json($investments, Response::HTTP_OK);
    }

    /**
     * Get an investment
     *
     * @throws AuthorizationException
     */
    public function getInvestmentDetails(Investment $investment): JsonResponse
    {
        Gate::authorize('view', $investment);

        $investment->load(['currency']);

        return response()->json($this->serializeInvestment($investment), Response::HTTP_OK);
    }

    /**
     * Get investment price history
     *
     * @throws AuthorizationException
     */
    public function getPriceHistory(Investment $investment): JsonResponse
    {
        Gate::authorize('view', $investment);

        // investment_id must stay selected (and eager-loaded) so MoneyCast can resolve
        // price's currency via investment.currency - hidden again below since it isn't
        // part of this endpoint's response shape.
        $prices = InvestmentPrice::where('investment_id', '=', $investment->id)
            ->select(['id', 'date', 'investment_id', 'price'])
            ->with('investment.currency')
            ->orderBy('date')
            ->get()
            ->makeHidden('investment_id');

        // Return data
        return response()->json($prices, Response::HTTP_OK);
    }

    /**
     * Get investment display data
     *
     * Used by the investment detail page to update all visualizations after transaction changes.
     */
    public function getDisplayData(Investment $investment): JsonResponse
    {
        /**
         * @get("/api/v1/investments/{investment}/display-data")
         * @name("api.v1.investments.display-data")
         * @middlewares("api", "auth:sanctum")
         */
        Gate::authorize('view', $investment);

        // Load investment with related data
        $investment->load(['investmentGroup', 'currency']);

        // Enrich investment with calculated quantity history
        $investment = $this->investmentService->enrichInvestmentWithQuantityHistory($investment);

        // Get all prices
        $prices = InvestmentPrice::where('investment_id', $investment->id)
            ->with('investment.currency')
            ->orderBy('date')
            ->get();

        // Get basic (non-scheduled) transactions
        $transactions = $investment->transactionsBasic()
            ->with(['config'])
            ->get();

        // Get scheduled transactions and generate instances
        $scheduledTransactions = $investment->transactionsScheduled()
            ->with(['config', 'transactionSchedule'])
            ->get()
            ->filter(fn ($transaction): bool => $transaction instanceof \App\Models\Transaction
                && ($transaction->transactionSchedule?->active) === true);

        // Add all scheduled instances to list of transactions
        $scheduleInstances = $this->getScheduleInstances($scheduledTransactions, 'next');
        $transactions = $transactions->concat($scheduleInstances);

        return response()->json([
            'investment' => $this->serializeInvestment($investment),
            'transactions' => $transactions,
            'quantities' => $investment->quantities,
            'prices' => $prices,
        ], Response::HTTP_OK);
    }

    /**
     * Update investment active status
     *
     * Accepts { active: true|false } in the request body.
     *
     * @throws AuthorizationException
     */
    public function patchActive(Request $request, Investment $investment): JsonResponse
    {
        Gate::authorize('update', $investment);

        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $investment->active = $validated['active'];
        $investment->save();

        return response()->json($investment, Response::HTTP_OK);
    }

    /**
     * Update investment provider settings
     *
     * @throws AuthorizationException
     */
    public function updateProviderSettings(
        UpdateInvestmentProviderSettingsRequest $request,
        Investment $investment
    ): JsonResponse {
        Gate::authorize('update', $investment);

        $providerSettings = $request->validated('provider_settings');

        $investment->fill([
            'provider_settings' => $providerSettings,
        ]);
        $investment->save();
        $investment->load(['currency']);

        return response()->json($this->serializeInvestment($investment), Response::HTTP_OK);
    }

    /**
     * List investments with timeline data
     *
     * Returns each investment's holding periods (quantity, start/end dates) along with
     * the price as of the end of each period, built from the user's transaction schedule.
     */
    public function getInvestmentsWithTimeline(Request $request): JsonResponse
    {
        $investments = $request->user()
            ->investments()
            ->with([
                'currency',
                'investmentGroup',
            ])
            ->get();

        // Build the holding periods first, without resolving prices yet. Each period
        // records which (investment, as-of date) price it needs; a null date means
        // "current/latest price" for a still-open position.
        $periods = [];
        $priceRequests = collect();

        $investments->map(fn ($investment) => $investment instanceof Investment
            ? $this->investmentService->enrichInvestmentWithQuantityHistory($investment)
            : null)
            ->filter(fn ($investment) => $investment instanceof Investment)
            ->each(function (Investment $investment) use (&$periods, &$priceRequests, $request) {
                $start = true;
                $period = [];

                foreach ($investment->quantities as $item) {
                    if ($start && $item['schedule'] > 0) {
                        $period = [
                            'id' => $investment->id,
                            'name' => $investment->name,
                            'active' => $investment->active,
                            'currency' => $investment->currency,
                            'investment_group' => $investment->investmentGroup,
                            'start' => $item['date'],
                            'quantity' => $item['schedule'],
                        ];

                        $start = false;

                        continue;
                    }

                    if (! $start && $item['schedule'] === 0.0) {
                        $period['end'] = $item['date'];
                        $period['_price_investment'] = $investment;
                        $period['_price_as_of'] = new Carbon($item['date']);
                        $priceRequests->push(['investment' => $investment, 'date' => $period['_price_as_of']]);
                        $periods[] = $period;
                        $period = [];

                        $start = true;

                        continue;
                    }

                    $period['quantity'] = $item['schedule'];
                }

                // If period start was set but the end date is missing, then set it to the app config end date
                if (Arr::has($period, 'start') && ! Arr::has($period, 'end')) {
                    $period['end'] = $request->user()->end_date;
                    $period['_price_investment'] = $investment;
                    $period['_price_as_of'] = null;
                    $priceRequests->push(['investment' => $investment, 'date' => null]);
                    $periods[] = $period;
                }
            });

        // Resolve every period's price in a small constant number of queries, instead of
        // up to 2 queries per holding period across the whole portfolio.
        $priceMap = $this->investmentService->getLatestPricesBatch($priceRequests);

        $positions = array_map(function (array $period) use ($priceMap) {
            $investment = $period['_price_investment'];
            $asOfDate = $period['_price_as_of'];

            $period['last_price'] = $priceMap[$this->investmentService->priceBatchKey($investment->id, $asOfDate)] ?? null;

            unset($period['_price_investment'], $period['_price_as_of']);

            return $period;
        }, $periods);

        return response()
            ->json(
                $positions,
                Response::HTTP_OK
            );
    }

    /**
     * Delete an investment
     *
     * @throws AuthorizationException
     */
    public function destroy(Investment $investment): JsonResponse
    {
        Gate::authorize('delete', $investment);

        $result = $this->investmentService->delete($investment);

        if ($result['success']) {
            return response()
                ->json(
                    ['investment' => $investment],
                    Response::HTTP_OK
                );
        }

        return response()
            ->json(
                [
                    'investment' => $investment,
                    'error' => $result['error'],
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvestment(Investment $investment): array
    {
        $payload = $investment->toArray();
        $payload['provider_settings'] = $this->providerSettingsResolver->resolveForInvestment($investment);

        return $payload;
    }
}
