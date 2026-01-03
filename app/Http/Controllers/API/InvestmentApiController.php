<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Traits\ScheduleTrait;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvestmentApiController extends Controller implements HasMiddleware
{
    use ScheduleTrait;

    protected InvestmentService $investmentService;

    public function __construct()
    {

        $this->investmentService = new InvestmentService();
    }

    public static function middleware(): array
    {
        return [
            ['auth:sanctum', 'verified'],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        /**
         * @get('/api/assets/investment')
         * @middlewares('api', 'auth:sanctum')
         *
         * Currently supported query parameters:
         * - active: filter by active status (1 or 0)
         * - query: search string to match against name, symbol, or ISIN
         * - currency_id: filter by currency ID
         * - limit: maximum number of results to return (default 10)
         * - sort_by: field to sort by (name, symbol, isin, active, created_at), default is name
         * - sort_order: asc or desc, default is asc
         */
        // Whitelist of valid sortable columns
        $validSortColumns = ['name', 'symbol', 'isin', 'active', 'created_at'];
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');

        // Validate sort_by parameter
        if (!in_array($sortBy, $validSortColumns, true)) {
            $sortBy = 'name';
        }

        // Validate sort_order parameter
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }

        $investments = $request->user()
            ->investments()
            ->when($request->has('active'), fn($query) =>
                $query->where('active', $request->get('active'))
            )
            ->when($request->get('query'), fn ($query) =>
                // The query string is searched in: name, symbol, ISIN
                $query->where(function ($q) use ($request) {
                    $q->whereRaw(
                        'LOWER(name) LIKE ?',
                        ['%' . strtolower($request->get('query')) . '%']
                    )
                    ->orWhereRaw(
                        'LOWER(symbol) LIKE ?',
                        ['%' . strtolower($request->get('query')) . '%']
                    )
                    ->orWhereRaw(
                        'LOWER(isin) LIKE ?',
                        ['%' . strtolower($request->get('query')) . '%']
                    );
                })
            )
            ->when($request->get('currency_id'), fn ($query) =>
                $query->where('currency_id', '=', $request->get('currency_id'))
            )
            ->orderBy($sortBy, $sortOrder)
            ->take($request->get('limit', 10))
            ->get();

        return response()->json($investments, Response::HTTP_OK);
    }

    /**
     * Read and return the details of a selected investment
     */
    public function getInvestmentDetails(Investment $investment): JsonResponse
    {
        /**
         * @get('/api/assets/investment/{investment}')
         * @name('investment.getDetails')
         * @middlewares('api', 'auth:sanctum')
         */
        Gate::authorize('view', $investment);

        $investment->load(['currency']);

        return response()->json($investment, Response::HTTP_OK);
    }

    public function getPriceHistory(Investment $investment): JsonResponse
    {
        /**
         * @get('/api/assets/investment/price/{investment}')
         * @middlewares('api', 'auth:sanctum')
         */
        Gate::authorize('view', $investment);

        $prices = InvestmentPrice::where('investment_id', '=', $investment->id)
            ->select(['id', 'date', 'price'])
            ->orderBy('date')
            ->get();

        // Return data
        return response()->json($prices, Response::HTTP_OK);
    }

    /**
     * @throws AuthorizationException
     */
    public function updateActive(Investment $investment, $active): JsonResponse
    {
        /**
         * @put('/api/assets/investment/{investment}/active/{active}')
         * @name('api.investment.updateActive')
         * @middlewares('api', 'auth:sanctum')
         */
        Gate::authorize('update', $investment);

        $investment->active = $active;
        $investment->save();

        return response()
            ->json(
                $investment,
                Response::HTTP_OK
            );
    }

    /**
     * Get all investments with timeline data
     */
    public function getInvestmentsWithTimeline(Request $request): JsonResponse
    {
        /**
         * @get('/api/assets/investment/timeline')
         * @middlewares('api', 'auth:sanctum')
         */
        $investmentService = new InvestmentService();

        $investments = $request->user()
            ->investments()
            ->with([
                'currency',
                'investmentGroup',
            ])
            ->get();

        // Aggregate transactions into positions
        $positions = [];

        // Loop through investments and get related transactions
        $investments->map(fn($investment) => $investmentService->enrichInvestmentWithQuantityHistory($investment))
            ->each(function ($investment) use (&$positions, $request) {
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

                    if (!$start && ($item['schedule'] === 0 || $item['schedule'] === 0.0)) {
                        $period['end'] = $item['date'];
                        $period['last_price'] = $investment->getLatestPrice('combined', new Carbon($item['date']));
                        $positions[] = $period;
                        $period = [];

                        $start = true;

                        continue;
                    }

                    $period['quantity'] = $item['schedule'];
                }

                // If period start was set but the end date is missing, then set it to the app config end date
                if (array_key_exists('start', $period) && !array_key_exists('end', $period)) {
                    $period['end'] = $request->user()->end_date;
                    $period['last_price'] = $investment->getLatestPrice('combined');
                    $positions[] = $period;
                }
            });

        return response()
            ->json(
                $positions,
                Response::HTTP_OK
            );
    }

    /**
     * Remove the specified investment.
     */
    public function destroy(Investment $investment): JsonResponse
    {
        /**
         * @delete('/api/investment/{investment}')
         * @name('api.investment.destroy')
         * @middlewares('web', 'auth', 'verified')
         */
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
}
