<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\BetweenConstraint;

class InvestmentApiController extends Controller
{
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
                        $query->Where('investment_id', $investment->id);
                    }
                )
                ->orderBy('date')
                ->get();

            // Process data for table and chart
            $rawTransactions
                ->transform(function ($transaction) {
                    $commonData =
                        [
                            'id' => $transaction->id,
                            'transaction_type' => $transaction->transactionType->toArray(),
                            'amount_operator' => $transaction->transactionType->amount_operator,
                            'quantity_operator' => $transaction->transactionType->quantity_operator,

                            'reconciled' => $transaction->reconciled,
                            'comment' => $transaction->comment,
                        ];

                    $baseData = [
                        'quantity' => $transaction->config->quantity,
                        'price' => $transaction->config->price,
                        'dividend' => $transaction->config->dividend,
                        'commission' => $transaction->config->commission,
                        'tax' => $transaction->config->tax,
                    ];

                    if ($transaction->schedule) {
                        $transaction->load(['transactionSchedule']);

                        $dateData = [
                            'schedule' => $transaction->transactionSchedule,
                            'transaction_group' => 'schedule',
                        ];
                    } else {
                        $dateData = [
                            'date' => $transaction->date,
                            'transaction_group' => 'history',
                        ];
                    }

                    return array_merge($commonData, $baseData, $dateData);
                });

            // Get all historical transactions
            $transactions = $rawTransactions->where('transaction_group', 'history');

            // Add all scheduled items to list of transactions
            $rawTransactions
                ->where('transaction_group', 'schedule')
                ->each(function ($transaction) use (&$transactions) {
                    $rule = new Rule();
                    $rule->setStartDate(new Carbon($transaction['schedule']->start_date));

                    if ($transaction['schedule']->end_date) {
                        $rule->setUntil(new Carbon($transaction['schedule']->end_date));
                    }

                    $rule->setFreq($transaction['schedule']->frequency);

                    if ($transaction['schedule']->count) {
                        $rule->setCount($transaction['schedule']->count);
                    }
                    if ($transaction['schedule']->interval) {
                        $rule->setInterval($transaction['schedule']->interval);
                    }

                    $transformerConfig = new ArrayTransformerConfig();
                    $transformerConfig->enableLastDayOfMonthFix();
                    // Avoid overloading too frequent schedules. TODO: notify user if limit is reached.
                    $transformerConfig->setVirtualLimit(500);

                    $transformer = new ArrayTransformer();
                    $transformer->setConfig($transformerConfig);

                    $startDate = new Carbon($transaction['schedule']->next_date);
                    $startDate->startOfDay();

                    if ($transaction['schedule']->end_date === null) {
                        $endDate = Auth::user()->end_date;
                    } else {
                        $endDate = new Carbon($transaction['schedule']->end_date);
                    }
                    $endDate->startOfDay();

                    $constraint = new BetweenConstraint($startDate, $endDate, true);

                    $first = true;

                    foreach ($transformer->transform($rule, $constraint) as $instance) {
                        $newTransaction = $transaction;
                        $newTransaction['date'] = new Carbon($instance->getStart());
                        $newTransaction['transaction_group'] = 'forecast';
                        $newTransaction['schedule_is_first'] = $first;

                        $transactions->push($newTransaction);

                        $first = false;
                    }
                });

            // Calculate historical and scheduled quantity changes for chart
            $runningTotal = 0;
            $runningSchedule = 0;
            $quantities = $transactions
                // TODO: group by date
                ->sortBy('date')
                ->map(function ($transaction) use (&$runningTotal, &$runningSchedule) {
                    $operator = $transaction['quantity_operator'];
                    if (! $operator) {
                        $quantity = 0;
                    } else {
                        $quantity = ($operator === 'minus' ? -1 : 1) * $transaction['quantity'];
                    }

                    $runningSchedule += $quantity;
                    if ($transaction['transaction_group'] === 'history') {
                        $runningTotal += $quantity;
                    }

                    return [
                        'date' => $transaction['date']->format('Y-m-d'),
                        'quantity' => $runningTotal,
                        'schedule' => $runningSchedule,
                    ];
                })
                ->values();

            $investment->quantities = $quantities;

            return $investment;
        })
            ->each(function ($investment) use (&$positions) {
                $start = true;

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

                // If period start was set but end date is missiong, set it to app config end date
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
