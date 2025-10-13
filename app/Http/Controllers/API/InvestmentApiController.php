<?php

namespace App\Http\Controllers\API;

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
use Illuminate\Support\Facades\Auth;

class InvestmentApiController extends Controller
{
    use ScheduleTrait;

    protected InvestmentService $investmentService;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);

        $this->investmentService = new InvestmentService();
    }

    public function getList(Request $request): JsonResponse
    {
        /**
         * @get('/api/assets/investment')
         * @middlewares('api', 'auth:sanctum')
         */
        $investments = Auth::user()
            ->investments()
            ->where('active', true)
            ->select(['id', 'name AS text'])
            ->when($request->get('q'), function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->get('q') . '%');
            })
            ->when($request->get('currency_id'), function ($query) use ($request) {
                $query->where('currency_id', '=', $request->get('currency_id'));
            })
            ->orderBy('name')
            ->take(10)
            ->get();

        return response()->json($investments, Response::HTTP_OK);
    }

    /**
     * Read and return the details of a selected investment
     *
     * @param Investment $investment
     * @return JsonResponse
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
     *
     * @return JsonResponse
     */
    public function getInvestmentsWithTimeline(): JsonResponse
    {
        /**
         * @get('/api/assets/investment/timeline')
         * @middlewares('api', 'auth:sanctum')
         */
        $investmentService = new InvestmentService();

        $investments = Auth::user()
            ->investments()
            ->with([
                'currency',
                'investmentGroup',
            ])
            ->get();

        // Aggregate transactions into positions
        $positions = [];

        // Loop through investments and get related transactions
        $investments->map(fn ($investment) => $investmentService->enrichInvestmentWithQuantityHistory($investment))
            ->each(function ($investment) use (&$positions) {
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

                    if (! $start && ($item['schedule'] === 0 || $item['schedule'] === 0.0)) {
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
                if (array_key_exists('start', $period) && ! array_key_exists('end', $period)) {
                    $period['end'] = Auth::user()->end_date;
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
     *
     * @param Investment $investment
     * @return JsonResponse
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
