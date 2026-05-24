<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Enums\TransactionType as TransactionTypeEnum;
use App\Services\CategoryService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportApiController extends Controller implements HasMiddleware
{
    use CurrencyTrait;
    use ScheduleTrait;

    private CategoryService $categoryService;

    public function __construct()
    {

        $this->categoryService = new CategoryService();
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    /**
     * Collect actual and budgeted cost for selected categories, and return it aggregated by month.
     */
    public function budgetChart(Request $request): JsonResponse
    {
        /**
         * @get("/api/v1/reports/budget-chart")
         * @name("api.v1.reports.budget-chart")
         * @middlewares("api", "auth:sanctum", "verified")
         */

        // Get list of requested categories
        // This also ensures that child categories are loaded for all parents
        $categories = $this->categoryService->getChildCategories($request);

        // Get the account selection properties
        $accountSelection = $request->get('accountSelection');
        $accountEntity = $request->get('accountEntity');

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRatesMap = $this->allCurrencyRatesByMonth();

        // Get all standard transactions with related categories
        if ($accountSelection === 'none') {
            $standardTransactions = new Collection();
        } else {
            $standardTransactions = TransactionItem::with([
                'transaction',
                'transaction.currency',
            ])
                ->whereIn('category_id', $categories->pluck('id'))
                ->whereHas('transaction', function ($query) use ($request, $accountSelection, $accountEntity) {
                    $query->whereUserId($request->user()->id)
                        ->where('schedule', false)
                        ->where('budget', false)
                        ->where('config_type', 'standard')
                        ->when($accountSelection === 'selected', fn ($query) => $query->whereHasMorph(
                            'config',
                            TransactionDetailStandard::class,
                            fn ($query) => $query->where('account_from_id', $accountEntity)
                                ->orWhere('account_to_id', $accountEntity)
                        ));
                })
                ->get();
        }

        // Group standard transactions by selected period, and get all relevant details
        $standardCompact = [];
        $standardTransactions->each(function ($item) use (&$standardCompact) {
            /** @var TransactionItem $item */
            $period = $item->transaction->date->format('Y-m-01');
            $currency_id = $item->transaction->currency_id;
            $amount = $item->transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL
                ? -1 * $item->amount
                : $item->amount;

            if (
                !array_key_exists($period, $standardCompact)
                || !array_key_exists($currency_id, $standardCompact[$period])
            ) {
                $standardCompact[$period][$currency_id] = 0;
            }
            $standardCompact[$period][$currency_id] += $amount;
        });

        // Summarize items, applying currency rate
        $dataByPeriod = [];
        $currenciesWithMissingRates = [];

        foreach ($standardCompact as $period => $periodData) {
            $carbonPeriod = Carbon::parse($period);
            foreach ($periodData as $currency => $value) {
                if (!array_key_exists($period, $dataByPeriod)) {
                    $dataByPeriod[$period] = [
                        'actual' => null,
                        'budget' => 0,
                    ];
                }

                $rate = $this->getLatestRateFromMap($currency, $carbonPeriod, $allRatesMap, $baseCurrency->id);

                if ($rate === null && $currency !== $baseCurrency->id) {
                    $currenciesWithMissingRates[$currency] = true;
                }

                $dataByPeriod[$period]['actual'] += $value * ($rate ?? 1);
            }
        }

        // Get all budget transactions with related categories
        $budgetTransactions = Transaction::with([
            'transactionItems',
            'transactionSchedule',
        ])
            ->whereHas('transactionItems', function ($query) use ($categories) {
                $query->whereIn('category_id', $categories->pluck('id'));
            })
            ->where('user_id', $request->user()->id)
            ->byType('standard')
            ->byScheduleType('budget')
            ->when($accountSelection === 'selected', fn ($query) => $query->whereHasMorph(
                'config',
                TransactionDetailStandard::class,
                fn ($query) => $query->where('account_from_id', $accountEntity)
                    ->orWhere('account_to_id', $accountEntity)
            ))
            ->when($accountSelection === 'none', function ($query) {
                return $query->where(function ($query) {
                    // Withdrawal with empty account_from_id
                    return $query->where(function ($query) {
                        $query->where('transaction_type', TransactionTypeEnum::WITHDRAWAL)
                            ->whereHasMorph(
                                'config',
                                TransactionDetailStandard::class,
                                fn ($query) => $query->whereNull('account_from_id')
                            );
                    })
                        // Or deposit with empty account_to_id
                        ->orWhere(function ($query) {
                            $query->where('transaction_type', TransactionTypeEnum::DEPOSIT)
                                ->whereHasMorph(
                                    'config',
                                    TransactionDetailStandard::class,
                                    fn ($query) => $query->whereNull('account_to_id')
                                );
                        });
                });
            })
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
            $request->user()->end_date
        );

        $budgetCompact = [];
        $budgetInstances->each(function ($transaction) use (&$budgetCompact, $baseCurrency) {
            $period = $transaction->date->format('Y-m-01');
            $currency_id = $transaction->currency_id ?? $baseCurrency->id;

            if (
                !array_key_exists($period, $budgetCompact)
                || !array_key_exists($currency_id, $budgetCompact[$period])
            ) {
                $budgetCompact[$period][$currency_id] = 0;
            }

            $budgetCompact[$period][$currency_id] += $transaction->sum
                * ($transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL ? -1 : 1);
        });

        foreach ($budgetCompact as $period => $periodData) {
            $carbonPeriod = Carbon::parse($period);
            foreach ($periodData as $currency => $value) {
                if (!array_key_exists($period, $dataByPeriod)) {
                    $dataByPeriod[$period] = [
                        'actual' => null,
                        'budget' => 0,
                    ];
                }

                $rate = $this->getLatestRateFromMap($currency, $carbonPeriod, $allRatesMap, $baseCurrency->id);

                if ($rate === null && $currency !== $baseCurrency->id) {
                    $currenciesWithMissingRates[$currency] = true;
                }

                $dataByPeriod[$period]['budget'] += $value * ($rate ?? 1);
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

        $missingRateCurrencies = $this->getMissingRateCurrencies($currenciesWithMissingRates);

        usort($result, fn ($a, $b) => $a['period'] <=> $b['period']);

        // Return fetched and prepared data
        return response()->json(
            [
                'chartData' => $result,
                'warnings' => [
                    'currenciesWithoutRates' => $missingRateCurrencies,
                ],
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Collect actual transactions for the given interval.
     *
     * @param string $dataType Planned feature for budget. Currently actual transactions are supported.
     */
    public function getCategoryWaterfallData(
        Request $request,
        string $transactionType,
        string $dataType,
        int $year,
        int|null $month = null
    ): JsonResponse {
        /**
         * @get("/api/v1/reports/waterfall/{transactionType}/{dataType}/{year}/{month?}")
         * @name("api.v1.reports.waterfall")
         * @middlewares("api", "auth:sanctum", "verified")
         */

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRatesMap = $this->allCurrencyRatesByMonth();
        [$rangeStart, $rangeEnd] = $this->resolveDateRangeForYearMonth($year, $month);

        // Final result placeholder
        $dataByCategory = [];
        $currenciesWithMissingRates = [];

        if ($transactionType === 'all' || $transactionType === 'standard') {
            // Get all standard transactions with related categories
            $standardTransactions = TransactionItem::with([
                'category.parent',
                'transaction',
                'transaction.currency',
                'transaction.config.accountFrom.config',
                'transaction.config.accountTo.config',
            ])
                ->whereHas('transaction', function ($query) use ($request, $rangeStart, $rangeEnd) {
                    $query->where('user_id', $request->user()->id)
                        ->whereBetween('date', [$rangeStart, $rangeEnd])
                        ->where('schedule', false)
                        ->where('budget', false)
                        ->where('config_type', 'standard')
                        ->where('transaction_type', '!=', TransactionTypeEnum::TRANSFER);
                })
                ->get();

            $standardTransactions->each(function ($item) use (&$dataByCategory, &$currenciesWithMissingRates, $baseCurrency, $allRatesMap) {
                // Determine the category group. This should be the top level category ideally.
                // Category ID is mandatory on a database level, but we add an untranlated fallback name for safety in case of data issues
                $category = $item->category->parent
                    ? $item->category->parent->name
                    : $item->category->name;

                // Ensure that we have an array element for the category
                if (!array_key_exists($category, $dataByCategory)) {
                    $dataByCategory[$category] = 0;
                }

                // Get the currency (from the transaction's cached value) and determine currency rate
                $currency_id = $item->transaction->currency_id;

                $rate = $this->getLatestRateFromMap(
                    $currency_id,
                    $item->transaction->date,
                    $allRatesMap,
                    $baseCurrency->id
                );

                if ($rate === null && $currency_id !== $baseCurrency->id) {
                    $currenciesWithMissingRates[$currency_id] = true;
                }

                $dataByCategory[$category] +=
                    ($item->transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL
                        ? -1
                        : 1)
                    * $item->amount
                    * ($rate ?? 1);
            });
        }

        if ($transactionType === 'all' || $transactionType === 'investment') {
            // Add investment transaction results
            $investmentTransactions = Transaction::with([
                'currency',
            ])
                ->byType('investment')
                ->whereIn('transaction_type', TransactionTypeEnum::investmentTypesWithAmountValues())
                ->where('user_id', $request->user()->id)
                ->whereBetween('date', [$rangeStart, $rangeEnd])
                ->get();

            $investmentTransactions->each(function ($transaction) use (&$dataByCategory, &$currenciesWithMissingRates, $baseCurrency, $allRatesMap) {
                // Determine the category group. This should be the top level category ideally.
                $category = ($transaction->transaction_type->amountMultiplier() === 1
                    ? __('Investment income')
                    : __('Investment payment'));

                // Ensure that we have an array element for the category
                if (!array_key_exists($category, $dataByCategory)) {
                    $dataByCategory[$category] = 0;
                }

                // Get the currency (from the cached column) and determine currency rate
                $rate = $this->getLatestRateFromMap(
                    $transaction->currency_id,
                    $transaction->date,
                    $allRatesMap,
                    $baseCurrency->id
                );

                if ($rate === null && $transaction->currency_id !== $baseCurrency->id) {
                    $currenciesWithMissingRates[$transaction->currency_id] = true;
                }

                $dataByCategory[$category] += ($transaction->cashflow_value ?? 0) * ($rate ?? 1);
            });
        }

        $result = [];
        foreach ($dataByCategory as $category => $value) {
            $result[] = [
                'category' => $category,
                'value' => $value,
            ];
        }

        $missingRateCurrencies = $this->getMissingRateCurrencies($currenciesWithMissingRates);

        // Return fetched and prepared data
        return response()->json(
            [
                'result' => 'success',
                'chartData' => $result,
                'warnings' => [
                    'currenciesWithoutRates' => $missingRateCurrencies,
                ],
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Resolve inclusive date range boundaries for a year or a specific year-month period.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveDateRangeForYearMonth(int $year, ?int $month): array
    {
        if ($month === null) {
            $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
            $end = CarbonImmutable::create($year, 12, 31)->endOfDay();

            return [$start, $end];
        }

        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth()->startOfDay();
        $end = $start->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    /**
     * Get monthly cashflow data with optional forecast values.
     */
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
        $allRatesMap = $this->allCurrencyRatesByMonth();

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
        // Track which currencies had missing rates (fell back to 1:1)
        $currenciesWithMissingRates = [];
        $debugRows = [];
        $compact = [];
        $monthlySummaries->each(function ($summary) use (&$compact, &$currenciesWithMissingRates, &$debugRows, $baseCurrency, $allRatesMap) {
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

            // Calculate the amount in the base currency, using the currency rate closest to the given date.
            // If the account entity is missing (for generic budgets), the base currency is used.
            // When no rate is found for a currency/period, the amount falls back to a 1:1 conversion.
            $isBaseCurrency = $summary->currency_id === $baseCurrency->id;
            $rate = $this->getLatestRateFromMap(
                $summary->currency_id,
                Carbon::parse($summary->date),
                $allRatesMap,
                $baseCurrency->id
            );

            // Resolve the source month for the rate that was actually applied
            $debugRateSourceMonth = null;
            if (!$isBaseCurrency && $rate !== null && array_key_exists($summary->currency_id, $allRatesMap)) {
                foreach ($allRatesMap[$summary->currency_id] as $rateDate => $rateValue) {
                    if (Carbon::parse($rateDate)->lte(Carbon::parse($summary->date))) {
                        $debugRateSourceMonth = $rateDate;
                        break;
                    }
                }
            }

            // Track if this currency had no rate (fell back to 1:1)
            $isMissingRate = !$isBaseCurrency && $rate === null;
            if ($isMissingRate) {
                $currenciesWithMissingRates[$summary->currency_id] = true;
            }

            // Flag a stale rate when the source month is more than one month before the data month
            $isStaleRate = false;
            if ($debugRateSourceMonth !== null) {
                $monthsApart = (int) Carbon::parse($summary->date)->diffInMonths(Carbon::parse($debugRateSourceMonth));
                $isStaleRate = $monthsApart > 1;
            }

            // Flag a suspicious rate value (e.g. off by a factor of thousands due to wrong unit)
            $effectiveRate = $isBaseCurrency ? 1.0 : $rate;
            $isSuspiciousRate = !$isBaseCurrency && $effectiveRate !== null && ($effectiveRate < 0.0001 || $effectiveRate > 10000);

            $amount = $summary->amount * ($effectiveRate ?? 1);

            // Based on the data_type and transaction_type, assign the amount to the correct field
            $compact[$month][$summary->transaction_type] += $amount;

            // Collect per-row debug data for browser console inspection
            $flags = array_values(array_filter([
                $isMissingRate ? 'missing_rate' : null,
                $isStaleRate ? 'stale_rate' : null,
                $isSuspiciousRate ? 'suspicious_rate' : null,
            ]));

            $debugRows[] = [
                'month' => $summary->date,
                'transaction_type' => $summary->transaction_type,
                'currency_id' => $summary->currency_id,
                'raw_amount' => $summary->amount,
                'exchange_rate' => $effectiveRate,
                'rate_source_month' => $debugRateSourceMonth,
                'is_base_currency' => $isBaseCurrency,
                'converted_amount' => $amount,
                'flags' => $flags,
            ];
        });

        // Sort the compact array by month to help with the chart
        ksort($compact);

        // Calculate the running total for each month, for the account balance fact
        $runningTotal = 0;
        foreach ($compact as $month => $data) {
            $runningTotal += $data['account_balance'];
            $compact[$month]['account_balance_running_total'] = $runningTotal;
        }

        // If there are currencies with missing rates, load their names for the warning message
        $missingRateCurrencies = $this->getMissingRateCurrencies($currenciesWithMissingRates);

        // Enrich debug rows with currency ISO codes
        if (!empty($debugRows)) {
            $allCurrencies = $this->getAllCurrencies();
            foreach ($debugRows as &$row) {
                $currency = $allCurrencies->get($row['currency_id']);
                $row['currency_iso_code'] = $currency->iso_code ?? 'Unknown';
            }
            unset($row);
        }

        return response()->json(
            [
                'chartData' => array_values($compact),
                'warnings' => [
                    'currenciesWithoutRates' => $missingRateCurrencies,
                ],
                'debug' => $debugRows,
            ],
            Response::HTTP_OK
        );
    }
}
