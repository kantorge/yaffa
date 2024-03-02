<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionType;
use App\Services\CategoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportApiController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    private CategoryService $categoryService;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
        $this->categoryService = new CategoryService();
    }

    /**
     * Collect actual and budgeted cost for selected categories, and return it aggregated by month.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function budgetChart(Request $request): JsonResponse
    {
        /**
         * @get('/api/budgetchart')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */

        // Get requested aggregation period
        $byYears = $request->get('byYears') ?? false;
        $periodFormat = $byYears ? 'Y-01-01' : 'Y-m-01';

        // Get list of requested categories
        // Ensure, that child categories are loaded for all parents
        $categories = $this->categoryService->getChildCategories($request);

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRates = $this->allCurrencyRatesByMonth()->sortByDesc('date_from');

        // Get all standard transactions with related categories
        $standardTransactions = TransactionItem::with([
            'transaction',
            'transaction.transactionType',
            'transaction.config.accountFrom.config',
            'transaction.config.accountTo.config',
        ])
            ->whereIn('category_id', $categories->pluck('id'))
            ->whereHas('transaction', function ($query) use ($request) {
                $query->whereUserId($request->user()->id)
                    ->byScheduleType('none')
                    ->byType('standard');
            })
            ->get();

        // Group standard transactions by selected period, and get all relevant details
        $standardCompact = [];
        $standardTransactions->each(function ($item) use (&$standardCompact, $periodFormat) {
            $period = $item->transaction->date->format($periodFormat);
            $currency_id = ($item->transaction->transactionType->name === 'withdrawal' ? $item->transaction->config->accountFrom->config->currency_id : $item->transaction->config->accountTo->config->currency_id);

            $standardCompact[$period][$currency_id][] = ($item->transaction->transactionType->name === 'withdrawal' ? -1 : 1) * $item->amount;
        });

        // Summarize items, applying currency rate
        $dataByPeriod = [];

        foreach ($standardCompact as $period => $periodData) {
            foreach ($periodData as $currency => $items) {
                if (!array_key_exists($period, $dataByPeriod)) {
                    $dataByPeriod[$period] = [
                        'actual' => null,
                        'budget' => 0,
                    ];
                }

                $rate = $allRates
                    ->where('from_id', $currency)
                    ->firstWhere('date_from', '<=', $period);

                $dataByPeriod[$period]['actual'] += array_sum($items) * ($rate ? $rate->rate : 1);
            }
        }

        // Get all budget transactions with related categories
        $budgetTransactions = Transaction::with([
            'transactionItems',
            'transactionType',
            'transactionSchedule',
            'config.accountFrom.config',
            'config.accountTo.config',
        ])
            ->whereHas('transactionItems', function ($query) use ($categories) {
                $query->whereIn('category_id', $categories->pluck('id'));
            })
            ->where('user_id', Auth::user()->id)
            ->where('budget', 1)
            ->where('config_type', 'standard')
            ->get();

        // Unify currencies and calculate amounts only for given categories
        $budgetTransactions->transform(function ($transaction) use ($categories) {
            $transaction->sum = $transaction->transactionItems
                ->filter(fn ($item) => $categories->pluck('id')->contains($item->category_id))
                ->sum('amount');

            return $transaction;
        });

        // Get all instances by month
        $budgetInstances = $this->getScheduleInstances(
            $budgetTransactions,
            'start',
            null,
            (new Carbon())->addYears(50)
        );

        $budgetCompact = [];
        $budgetInstances->each(function ($transaction) use (&$budgetCompact, $baseCurrency, $periodFormat) {
            $period = $transaction->date->format($periodFormat);
            if ($transaction->transactionType->name === 'withdrawal') {
                if ($transaction->config->accountFrom) {
                    $currency_id = $transaction->config->accountFrom->config->currency_id;
                } else {
                    $currency_id = $baseCurrency->id;
                }
            } else {
                if ($transaction->config->accountTo) {
                    $currency_id = $transaction->config->accountTo->config->currency_id;
                } else {
                    $currency_id = $baseCurrency->id;
                }
            }

            $budgetCompact[$period][$currency_id][] = ($transaction->transactionType->name === 'withdrawal' ? -1 : 1) * $transaction->sum;
        });

        foreach ($budgetCompact as $period => $periodData) {
            foreach ($periodData as $currency => $items) {
                if (!array_key_exists($period, $dataByPeriod)) {
                    $dataByPeriod[$period] = [
                        'actual' => null,
                        'budget' => 0,
                    ];
                }

                $rate = $allRates
                    ->where('from_id', $currency)
                    ->firstWhere('date_from', '<=', $period);

                $dataByPeriod[$period]['budget'] += array_sum($items) * ($rate ? $rate->rate : 1);
            }
        }

        // Transform standard data into amCharts format
        $result = [];
        foreach ($dataByPeriod as $key => $value) {
            $result[] = [
                'period' => new Carbon($key),
                'actual' => $value['actual'],
                'budget' => $value['budget'],
            ];
        }

        usort($result, fn ($a, $b) => $a['period'] <=> $b['period']);

        // Return fetched and prepared data
        return response()->json($result, Response::HTTP_OK);
    }

    /**
     * Collect actual transactions for the given interval.
     *
     * @param string $transactionType
     * @param string $dataType Planned feature for budget. Currently actual transactions are supported.
     * @param int $year
     * @param int|null $month
     * @return JsonResponse
     */
    public function getCategoryWaterfallData(string $transactionType, string $dataType, int $year, int $month = null): JsonResponse
    {
        /**
         * @get('/api/reports/waterfall/{type}/{year}/{month?}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRates = $this->allCurrencyRatesByMonth()->sortByDesc('date_from');

        // Final result placeholder
        $dataByCategory = [];

        if ($transactionType === 'all' || $transactionType === 'standard') {
            // Get all standard transactions with related categories
            $standardTransactions = TransactionItem::with([
                'category',
                'transaction',
                'transaction.transactionType',
                'transaction.config.accountFrom.config',
                'transaction.config.accountTo.config',
            ])
                ->whereHas('transaction', function ($query) use ($year, $month) {
                    $query->where('user_id', Auth::user()->id)
                        ->when($month === null, fn ($query) => $query->whereRaw('YEAR(date) = ?', [$year]))
                        ->when($year && $month, function ($query) use ($year, $month) {
                            return $query->whereRaw('YEAR(date) = ?', [$year])
                                ->whereRaw('MONTH(date) = ?', [$month]);
                        })
                        ->byScheduleType('none')
                        ->where('config_type', 'standard')
                        ->where(
                            'transaction_type_id',
                            '!=',
                            TransactionType::where('name', '=', 'transfer')->first()->id
                        );
                })
                ->get();

            $standardTransactions->each(function ($item) use (&$dataByCategory, $baseCurrency, $allRates) {
                // Determine the category group. This should be the top level category ideally.
                $category = $item->category?->parent?->name ?? $item->category?->name ?? 'No category assigned';

                // Ensure that we have an array element for the category
                if (!array_key_exists($category, $dataByCategory)) {
                    $dataByCategory[$category] = 0;
                }

                // Get the currency and determine currency rate
                $currency_id = ($item->transaction->transactionType->name === 'withdrawal' ? $item->transaction->config->accountFrom->config->currency_id : $item->transaction->config->accountTo->config->currency_id);
                if ($currency_id !== $baseCurrency->id) {
                    $rate = $allRates
                        ->where('from_id', $currency_id)
                        ->firstWhere('date_from', '<=', $item->transaction->date);
                } else {
                    $rate = null;
                }

                $dataByCategory[$category] += ($item->transaction->transactionType->name === 'withdrawal' ? -1 : 1) * $item->amount * ($rate ? $rate->rate : 1);
            });
        }

        if ($transactionType === 'all' || $transactionType === 'investment') {
            // Add investment transaction results
            $investmentTransactions = Transaction::with([
                'transactionType',
                'config.account.config',
            ])
                //->where('config_type', 'investment')
                ->whereIn(
                    'transaction_type_id',
                    TransactionType::where('type', 'investment')->whereNotNull('amount_operator')->get()->pluck('id')
                )
                ->where('user_id', Auth::user()->id)
                ->when($month === null, fn ($query) => $query->whereRaw('YEAR(date) = ?', [$year]))
                ->when($year && $month, function ($query) use ($year, $month) {
                    return $query->whereRaw('YEAR(date) = ?', [$year])
                        ->whereRaw('MONTH(date) = ?', [$month]);
                })
                ->get();

            $investmentTransactions->each(function ($transaction) use (&$dataByCategory, $baseCurrency, $allRates) {
                // Determine the category group. This should be the top level category ideally.
                $category = ($transaction->transactionType->amount_operator === 'plus' ? 'Investment income' : 'Investment payment');

                // Ensure that we have an array element for the category
                if (!array_key_exists($category, $dataByCategory)) {
                    $dataByCategory[$category] = 0;
                }

                // Get the currency and determine currency rate
                $currency_id = $transaction->config->account->config->currency_id;
                if ($currency_id !== $baseCurrency->id) {
                    $rate = $allRates
                        ->where('from_id', $currency_id)
                        ->firstWhere('date_from', '<=', $transaction->date);
                } else {
                    $rate = null;
                }

                $dataByCategory[$category] += $transaction->accountBalanceChange() * ($rate ? $rate->rate : 1);
            });
        }

        $result = [];
        foreach ($dataByCategory as $category => $value) {
            $result[] = [
                'category' => $category,
                'value' => $value,
            ];
        }

        // Return fetched and prepared data
        return response()->json(
            [
                'result' => 'success',
                'chartData' => $result,
            ],
            Response::HTTP_OK
        );
    }

    public function getCashflowData(Request $request): JsonResponse
    {
        $user = $request->user();

        // Before proceeding with any calculation, check if any batch jobs are running for this user
        $batchJobsCount = DB::table('job_batches')
            ->where('name', 'like', 'CalculateAccountMonthlySummariesJob-%-' . $user->id)
            ->where('finished_at', null)
            ->count();

        if ($batchJobsCount > 0) {
            return response()
                ->json(
                    [
                        'result' => 'busy',
                        'message' => __('Account summary calculations are in progress.'),
                    ],
                    Response::HTTP_OK
                );
        }

        // Check if forecast is required
        $withForecast = $request->get('withForecast') ?? false;

        // Get monthly average currency rate for all currencies
        $baseCurrency = $this->getBaseCurrency();

        $allRates = $this->allCurrencyRatesByMonth()->sortByDesc('date_from');

        // Pre-process the $allRates collection into a map
        $allRatesMap = [];
        foreach ($allRates as $rate) {
            $allRatesMap[$rate->from_id][$rate->date_from->format('Y-m-d')] = $rate->rate;
        }

        // Get all monthly summaries for the user
        // We don't need the model capabilities, so we can use the query builder directly
        // This also allows us to group by date, types and currencies
        $monthlySummaries = DB::table('account_monthly_summaries')
            ->join(
                'account_entities',
                'account_monthly_summaries.account_entity_id',
                '=',
                'account_entities.id'
            )
            // At this point we assume that only accounts are provided
            ->join(
                'accounts',
                'account_entities.config_id',
                '=',
                'accounts.id'
            )
            ->where('account_monthly_summaries.user_id', $user->id)
            ->when(
                !$withForecast,
                fn ($query) => $query->where('data_type', '=', 'fact')
            )
            // Optionally filter by accountEntity
            ->when(
                $request->get('accountEntity'),
                fn ($query) => $query->where('account_entity_id', '=', $request->get('accountEntity'))
            )
            ->select(
                'date',
                'transaction_type',
            )
            ->selectRaw('CAST(COALESCE(accounts.currency_id, ?) AS SIGNED) AS currency_id', [$baseCurrency->id])
            ->selectRaw('SUM(amount) AS amount')
            ->groupBy([
                'date',
                'transaction_type',
                'currency_id'
            ])
            ->get();

        // Group monthly summaries by month, and get all relevant details
        $compact = [];
        $monthlySummaries->each(function ($summary) use (&$compact, $baseCurrency, $allRatesMap) {
            // First of all, if the amount is 0, we can skip this summary
            if ($summary->amount === 0) {
                return;
            }

            $month = $summary->date;

            // Check if the given month is already in the compact array
            if (!array_key_exists($month, $compact)) {
                $compact[$month] = [
                    'month' => $month,
                    'account_balance' => 0,
                    'account_balance_running_total' => 0,
                    'investment_value' => 0,
                ];
            }

            // Calculate the amount in the base currency, using the currency rate closest to the given date
            // If the accountEntity is missing (for generic budgets), use the base currency, too
            if ($summary->currency_id !== $baseCurrency->id) {
                // Get the dates for this currency sorted in descending order
                $dates = array_keys($allRatesMap[$summary->currency_id]);
                rsort($dates);

                // Find the latest date before the summary's date
                foreach ($dates as $date) {
                    if ($date <= $summary->date) {
                        $rate = $allRatesMap[$summary->currency_id][$date];
                        break;
                    }
                }

                $rate = $rate ?? 1;

                $amount = $summary->amount * $rate;
            } else {
                $amount = $summary->amount;
            }

            // Based on the data_type and transaction_type, assign the amount to the correct field
            $compact[$month][$summary->transaction_type] += $amount;
        });

        // Sort the compact array by month to help with the chart
        ksort($compact);

        // Calculate the running total for each month, for the account balance fact
        $runningTotal = 0;
        foreach ($compact as $month => $data) {
            $runningTotal += $data['account_balance'];
            $compact[$month]['account_balance_running_total'] = $runningTotal;
        }

        return response()->json(
            [
                'chartData' => array_values($compact),
            ],
            Response::HTTP_OK
        );
    }
}
