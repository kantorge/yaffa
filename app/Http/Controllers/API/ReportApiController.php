<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountMonthlySummary;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Http\Requests\API\BudgetChartRequest;
use App\Services\BudgetChartService;
use App\Services\CategoryService;
use App\Services\CategoryWaterfallCacheService;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
    'budgetChart', 'getCategoryWaterfallData', 'getCashflowData',
])]
class ReportApiController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    private CategoryService $categoryService;

    public function __construct(private BudgetChartService $budgetChartService)
    {
        $this->categoryService = new CategoryService();
    }

    /**
     * Get budget vs actual chart data
     *
     * Collects actual and budgeted cost for selected categories, and returns it aggregated by month.
     */
    public function budgetChart(BudgetChartRequest $request): JsonResponse
    {
        // Get list of requested categories
        // This also ensures that child categories are loaded for all parents
        $categories = $this->categoryService->getChildCategories($request);

        $data = $this->budgetChartService->chart(
            $request->user(),
            $categories,
            $request->query('accountSelection'),
            $request->query('accountEntity'),
        );

        // Return fetched and prepared data
        return response()->json(
            [
                'chartData' => $data['chartData'],
                'warnings' => [
                    'currenciesWithoutRates' => $data['currenciesWithoutRates'],
                ],
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Get category waterfall data
     *
     * Collects actual transactions for the given interval and groups them by category.
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
        // The optional `include` query parameter selects any combination of waterfall groups;
        // without it, the legacy {transactionType} segment maps to a fixed set of groups.
        $request->validate([
            'include' => ['sometimes', 'array', 'min:1'],
            'include.*' => ['string', Rule::in(CategoryWaterfallCacheService::GROUPS)],
        ]);
        $groups = CategoryWaterfallCacheService::normalizeGroups(
            $request->has('include')
                ? $request->query('include')
                : CategoryWaterfallCacheService::LEGACY_TYPE_GROUPS[$transactionType]
        );

        $cacheKey = CategoryWaterfallCacheService::key($request->user()->id, $groups, $dataType, $year, $month);

        $payload = Cache::remember($cacheKey, CategoryWaterfallCacheService::ttl(), fn () => $this->buildCategoryWaterfallPayload($request, $groups, $year, $month));

        return response()->json(
            ['result' => 'success', ...$payload],
            Response::HTTP_OK
        );
    }

    /**
     * @return array{chartData: array, warnings: array}
     */
    private function buildCategoryWaterfallPayload(
        Request $request,
        string $groups,
        int $year,
        int|null $month
    ): array {
        $groups = explode(',', $groups);
        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRatesMap = $this->allCurrencyRatesByMonth();
        [$rangeStart, $rangeEnd] = $this->resolveDateRangeForYearMonth($year, $month);

        // Final result placeholder
        $dataByCategory = [];
        $currenciesWithMissingRates = [];
        // Top-level category id backing each standard bucket, keyed by bucket label (null for investment buckets)
        $categoryIdByBucket = [];
        // Transaction type values that contributed to each bucket, keyed by bucket label
        $transactionTypesByBucket = [];
        // Display label for buckets whose key differs from it (trade buckets)
        $labelByBucket = [];
        $standardBucketTypes = [TransactionTypeEnum::WITHDRAWAL->value, TransactionTypeEnum::DEPOSIT->value];
        $investmentBucketTypes = TransactionTypeEnum::investmentTypesWithAmountValues();
        $tradeBucketTypes = [
            TransactionTypeEnum::BUY->value => __('Investment purchases'),
            TransactionTypeEnum::SELL->value => __('Investment sales'),
        ];

        if (in_array('standard', $groups, true)) {
            // Get all standard transactions with related categories
            $standardTransactions = TransactionItem::with([
                'category.parent',
                'transaction',
                'transaction.currency',
            ])
                ->whereHas('transaction', function ($query) use ($request, $rangeStart, $rangeEnd) {
                    $query->where('user_id', $request->user()->id)
                        ->whereBetween('date', [$rangeStart, $rangeEnd])
                        ->where('schedule', false)
                        ->where('config_type', 'standard')
                        ->where('transaction_type', '!=', TransactionTypeEnum::TRANSFER);
                })
                ->get();

            $standardTransactions->each(function ($item) use (&$dataByCategory, &$categoryIdByBucket, &$transactionTypesByBucket, $standardBucketTypes, &$currenciesWithMissingRates, $baseCurrency, $allRatesMap) {
                // Determine the category group. This should be the top level category ideally.
                // Category ID is mandatory on a database level, but we add an untranlated fallback name for safety in case of data issues
                $topCategory = $item->category->parent ?: $item->category;
                $category = $topCategory->name;

                // Ensure that we have an array element for the category
                if (!array_key_exists($category, $dataByCategory)) {
                    $dataByCategory[$category] = BigDecimal::zero();
                    $categoryIdByBucket[$category] = $topCategory->id;
                    $transactionTypesByBucket[$category] = $standardBucketTypes;
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

                // Accumulated exactly (BigDecimal) rather than via float +=, since this sums
                // across every transaction in the category - the same repeated-summation
                // drift pattern AMOUNT_COMPARISON_EPSILON was invented to tolerate (FR-1).
                // $rate is already an inexact monthly average (AVG() over daily rates), so
                // multiplying by it doesn't need to be exact - only the summation does.
                $dataByCategory[$category] = $dataByCategory[$category]->plus(
                    $item->amount->getAmount()
                        ->multipliedBy($item->transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL ? -1 : 1)
                        ->multipliedBy((string) ($rate ?? 1))
                );
            });
        }

        if (in_array('income', $groups, true)) {
            // Add investment transaction results
            $investmentTransactions = Transaction::with([
                'currency',
            ])
                ->byType('investment')
                ->whereIn('transaction_type', TransactionTypeEnum::investmentTypesWithAmountValues())
                ->where('user_id', $request->user()->id)
                ->whereBetween('date', [$rangeStart, $rangeEnd])
                ->get();

            $investmentTransactions->each(function ($transaction) use (&$dataByCategory, &$categoryIdByBucket, &$transactionTypesByBucket, $investmentBucketTypes, &$currenciesWithMissingRates, $baseCurrency, $allRatesMap) {
                // Determine the category group. This should be the top level category ideally.
                $category = __('Investment income');

                // Ensure that we have an array element for the category
                if (!array_key_exists($category, $dataByCategory)) {
                    $dataByCategory[$category] = BigDecimal::zero();
                    $categoryIdByBucket[$category] = null;
                    $transactionTypesByBucket[$category] = $investmentBucketTypes;
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

                // As above: $rate is an inexact AVG() over daily rates, so multiplying by it
                // doesn't need to be exact - only the summation into $dataByCategory does.
                $dataByCategory[$category] = $dataByCategory[$category]->plus(
                    ($transaction->cashflow_value?->getAmount() ?? BigDecimal::zero())->multipliedBy((string) ($rate ?? 1))
                );
            });
        }

        if (in_array('trades', $groups, true)) {
            // Buys and sells are asset conversions rather than income or expense, so they get
            // their own two buckets (purchases and sales), shown only when explicitly requested.
            $tradeTransactions = Transaction::with(['currency'])
                ->byType('investment')
                ->whereIn('transaction_type', array_keys($tradeBucketTypes))
                ->where('user_id', $request->user()->id)
                ->whereBetween('date', [$rangeStart, $rangeEnd])
                ->get();

            $tradeTransactions->each(function ($transaction) use (&$dataByCategory, &$categoryIdByBucket, &$transactionTypesByBucket, &$labelByBucket, $tradeBucketTypes, &$currenciesWithMissingRates, $baseCurrency, $allRatesMap) {
                // Keyed separately from the displayed label, so a user category with the same
                // name can never merge into (or take over the metadata of) a trade bucket.
                $category = 'trade:' . $transaction->transaction_type->value;

                if (!array_key_exists($category, $dataByCategory)) {
                    $dataByCategory[$category] = BigDecimal::zero();
                    $categoryIdByBucket[$category] = null;
                    $transactionTypesByBucket[$category] = [$transaction->transaction_type->value];
                    $labelByBucket[$category] = $tradeBucketTypes[$transaction->transaction_type->value];
                }

                $rate = $this->getLatestRateFromMap(
                    $transaction->currency_id,
                    $transaction->date,
                    $allRatesMap,
                    $baseCurrency->id
                );

                if ($rate === null && $transaction->currency_id !== $baseCurrency->id) {
                    $currenciesWithMissingRates[$transaction->currency_id] = true;
                }

                $dataByCategory[$category] = $dataByCategory[$category]->plus(
                    ($transaction->cashflow_value?->getAmount() ?? BigDecimal::zero())->multipliedBy((string) ($rate ?? 1))
                );
            });
        }

        $result = [];
        foreach ($dataByCategory as $category => $value) {
            // The find-transactions endpoint already expands a top-level category into
            // itself plus its children (see CategoryService::getChildCategories()), so
            // only the top-level category id needs to be passed through here.
            // Collapse to float only here, at the chart-response boundary: waterfall.js
            // does plain JS `+` on this value (amCharts consumption), which requires a
            // real JSON number, not a decimal string - the same chart-boundary rule Phase 3
            // established for the frontend (Decimal -> Number only at the amCharts feed).
            $result[] = [
                'category' => (string) $category,
                'label' => $labelByBucket[$category] ?? (string) $category,
                'value' => $value->toFloat(),
                'category_id' => $categoryIdByBucket[$category],
                'transaction_types' => $transactionTypesByBucket[$category],
            ];
        }

        $missingRateCurrencies = $this->getMissingRateCurrencies($currenciesWithMissingRates);

        return [
            'chartData' => $result,
            'warnings' => [
                'currenciesWithoutRates' => $missingRateCurrencies,
            ],
        ];
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
     * Get monthly cashflow data
     *
     * Returns monthly cashflow data, with optional forecast values.
     */
    public function getCashflowData(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if forecast is required
        $withForecast = $request->query('withForecast') ?? false;

        // Before proceeding with any calculation, check if the data this request reads is still
        // being (re)calculated for this user. A fact-only request only needs to wait on fact
        // batches; a forecast-inclusive request also reads forecast/budget rows, so it must wait
        // on any task.
        $tasksToCheck = $withForecast ? [] : ['account_balance-fact', 'investment_value-fact'];

        if (AccountMonthlySummary::isCalculationInProgress($user->id, $tasksToCheck)) {
            return response()
                ->json(
                    [
                        'result' => 'busy',
                        'message' => __('Account summary calculations are in progress.'),
                    ],
                    Response::HTTP_OK
                );
        }

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
                'account_entities.id',
                // This ensures that we get budget summaries not associated with any account
                'left'
            )
            // At this point we assume that only accounts are provided
            ->join(
                'accounts',
                'account_entities.config_id',
                '=',
                'accounts.id',
                'left'
            )
            ->where('account_monthly_summaries.user_id', $user->id)
            ->when(
                !$withForecast,
                fn ($query) => $query->where('data_type', '=', 'fact')
            )
            // Optionally filter by account (using accountEntity)
            ->when(
                $request->query('accountEntity'),
                fn ($query) => $query->where('account_entity_id', '=', $request->query('accountEntity'))
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

        // Pre-seed every month in the presented range with zero values, so a month with no
        // underlying summary rows (e.g. zero net change that month) still appears in chartData
        // as a zero-value point instead of being skipped entirely.
        if ($monthlySummaries->isNotEmpty()) {
            $period = CarbonPeriod::create($monthlySummaries->min('date'), '1 month', $monthlySummaries->max('date'));
            foreach ($period as $month) {
                $key = $month->format('Y-m-d');
                $compact[$key] = [
                    'month' => $key,
                    'account_balance' => BigDecimal::zero(),
                    'account_balance_running_total' => BigDecimal::zero(),
                    'investment_value' => BigDecimal::zero(),
                ];
            }
        }

        $monthlySummaries->each(function ($summary) use (&$compact, &$currenciesWithMissingRates, &$debugRows, $baseCurrency, $allRatesMap) {
            $month = $summary->date;

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

                if ($debugRateSourceMonth === null) {
                    $debugRateSourceMonth = array_key_last($allRatesMap[$summary->currency_id]);
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

            // Accumulated exactly (BigDecimal) rather than via float +=, since this sums
            // across every summary row for the month/type - the same repeated-summation
            // drift pattern AMOUNT_COMPARISON_EPSILON was invented to tolerate (FR-1).
            // $effectiveRate is already an inexact monthly average, so multiplying by it
            // doesn't need to be exact - only the summation does.
            $amount = BigDecimal::of($summary->amount)->multipliedBy((string) ($effectiveRate ?? 1));

            // Based on the data_type and transaction_type, assign the amount to the correct field
            $compact[$month][$summary->transaction_type] = $compact[$month][$summary->transaction_type]->plus($amount);

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
                'converted_amount' => $amount->toFloat(),
                'flags' => $flags,
            ];
        });

        // Sort the compact array by month to help with the chart
        ksort($compact);

        // Calculate the running total for each month, for the account balance fact.
        // Accumulated exactly (BigDecimal) - a running total across every month in the
        // user's history is exactly the kind of long repeated-summation chain most prone to
        // float drift.
        $runningTotal = BigDecimal::zero();
        foreach ($compact as $month => $data) {
            $runningTotal = $runningTotal->plus($data['account_balance']);
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

        // Collapse to float only here, at the chart-response boundary: cashflow.js feeds
        // these fields straight to amCharts as valueY series, which needs real JSON numbers,
        // not decimal strings.
        $chartData = array_map(
            fn (array $row) => [
                ...$row,
                'account_balance' => $row['account_balance']->toFloat(),
                'account_balance_running_total' => $row['account_balance_running_total']->toFloat(),
                'investment_value' => $row['investment_value']->toFloat(),
            ],
            array_values($compact)
        );

        return response()->json(
            [
                'chartData' => $chartData,
                'warnings' => [
                    'currenciesWithoutRates' => $missingRateCurrencies,
                ],
                'debug' => $debugRows,
            ],
            Response::HTTP_OK
        );
    }
}
