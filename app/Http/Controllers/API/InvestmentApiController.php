<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\ScheduleTrait;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
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
        $this->authorize('view', $investment);

        $investment->load(['currency']);

        return response()->json($investment, Response::HTTP_OK);
    }

    public function getPriceHistory(Investment $investment): JsonResponse
    {
        /**
         * @get('/api/assets/investment/price/{investment}')
         * @middlewares('api', 'auth:sanctum')
         */
        $this->authorize('view', $investment);

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
        $this->authorize('update', $investment);

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
        $investments->map(function ($investment) {
            $rawTransactions =
            Transaction::with([
                'config',
                'transactionType',
            ])
                ->whereHasMorph(
                    'config',
                    [TransactionDetailInvestment::class],
                    function (Builder $query) use ($investment) {
                        $query->where('investment_id', $investment->id);
                    }
                )
                ->orderBy('date')
                ->get();

            // Get all transactions related to selected investment
            $rawTransactions = Transaction::with([
                'config',
                'transactionType',
                'transactionSchedule'
            ])
                ->whereHasMorph(
                    'config',
                    [TransactionDetailInvestment::class],
                    function (Builder $query) use ($investment) {
                        $query->where('investment_id', $investment->id);
                    }
                )
                ->orderBy('date')
                ->get();

            // Split the transactions into historical and scheduled, based on the schedule flag
            [$scheduledTransactions, $transactions] = $rawTransactions->partition('schedule');

            // Add all scheduled items to list of transactions
            $scheduleInstances = $this->getScheduleInstances($scheduledTransactions, 'start');
            $transactions = $transactions->concat($scheduleInstances);

            // Calculate historical and scheduled quantity changes for chart
            $runningTotal = 0;
            $runningSchedule = 0;
            $quantities = $transactions
                ->sortBy('date')
                ->map(function (Transaction $transaction) use (&$runningTotal, &$runningSchedule) {
                    // Quantity operator can be 1, -1 or null.
                    // It's the expected behavior to set the quantity to 0 if the operator is null.
                    $quantity = $transaction->transactionType->quantity_multiplier * $transaction->config->quantity;

                    $runningSchedule += $quantity;
                    if (!$transaction->schedule) {
                        $runningTotal += $quantity;
                    }

                    return [
                        'date' => $transaction->date->format('Y-m-d'),
                        'quantity' => $runningTotal,
                        'schedule' => $runningSchedule,
                    ];
                });

            $investment->quantities = $quantities;

            return $investment;
        })
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
