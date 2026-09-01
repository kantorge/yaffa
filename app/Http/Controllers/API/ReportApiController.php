<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountMonthlySummary;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Services\BudgetService;
use App\Services\CategoryService;
use App\Services\CategoryWaterfallCacheService;
use App\Services\InflationCalculator;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    public function __construct(private BudgetService $budgetService)
    {
        $this->categoryService = new CategoryService();
    }

    /**
     * Get budget vs actual chart data
     *
     * Collects actual and budgeted cost for selected categories, and returns it aggregated by month.
     */
    public function budgetChart(Request $request): JsonResponse
    {
        // Get list of requested categories
        // This also ensures that child categories are loaded for all parents
        $categories = $this->categoryService->getChildCategories($request);

        // Get the account selection properties
        $accountSelection = $request->query('accountSelection');
        $accountEntity = $request->query('accountEntity');

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRatesMap = $this->allCurrencyRatesByMonth();
        // Already keyed by id - see CurrencyTrait::getAllCurrencies().
        $allCurrencies = $this->getAllCurrencies($request->user()->id);

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

        // Group standard transactions by selected period, and get all relevant details.
        // Accumulated exactly (BigDecimal) rather than via float +=, since this sums across
        // every transaction in the period/currency - the same repeated-summation drift
        // pattern AMOUNT_COMPARISON_EPSILON was invented to tolerate (FR-1).
        $standardCompact = [];
        $standardTransactions->each(function ($item) use (&$standardCompact) {
            /** @var TransactionItem $item */
            $period = $item->transaction->date->format('Y-m-01');
            $currency_id = $item->transaction->currency_id;
            $amount = $item->amount->getAmount()
                ->multipliedBy($item->transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL ? -1 : 1);

            if (
                !array_key_exists($period, $standardCompact)
                || !array_key_exists($currency_id, $standardCompact[$period])
            ) {
                $standardCompact[$period][$currency_id] = BigDecimal::zero();
            }
            $standardCompact[$period][$currency_id] = $standardCompact[$period][$currency_id]->plus($amount);
        });

        // Summarize items, applying currency rate. $rate is already an inexact monthly
        // average (AVG() over daily rates), so multiplying by it doesn't need to be exact -
        // only the summation does.
        // 'actual' starts as null (rather than BigDecimal::zero()) and stays that way for a
        // period with no standard transactions at all - budgetchart.js relies on this
        // null-vs-zero distinction to find the last period with real data.
        $dataByPeriod = [];
        $currenciesWithMissingRates = [];

        foreach ($standardCompact as $period => $periodData) {
            $carbonPeriod = Carbon::parse($period);
            foreach ($periodData as $currency => $value) {
                if (!array_key_exists($period, $dataByPeriod)) {
                    $dataByPeriod[$period] = [
                        'actual' => null,
                        'budget' => BigDecimal::zero(),
                        'forecast' => BigDecimal::zero(),
                    ];
                }

                $rate = $this->getLatestRateFromMap($currency, $carbonPeriod, $allRatesMap, $baseCurrency->id);

                if ($rate === null && $currency !== $baseCurrency->id) {
                    $currenciesWithMissingRates[$currency] = true;
                }

                $dataByPeriod[$period]['actual'] = ($dataByPeriod[$period]['actual'] ?? BigDecimal::zero())
                    ->plus($value->multipliedBy((string) ($rate ?? 1)));
            }
        }

        // Forecast series: the projected value of active scheduled standard transactions, kept
        // separate from the 'budget' series (which is standalone Budget rows only - see below).
        // Projected from each schedule's own next_date, not start_date, since occurrences before
        // next_date have either already been recorded as real (actual) transactions or skipped.
        //
        // 'none' scope has no schedule-transaction equivalent post-redesign: account_from_id/
        // account_to_id are NOT NULL on transaction_details_standard now (Phase 7), so a real
        // schedule transaction can never be account-agnostic - that case is now represented only
        // by an account-agnostic standalone Budget row (account_id = null, handled below). An
        // empty collection here mirrors how $standardTransactions already short-circuits for
        // 'none' above, instead of running a whereNull query against a column that can never be
        // null (which previously silently always matched zero rows).
        if ($accountSelection === 'none') {
            // Same Eloquent collection type Transaction::...->get() below returns, so downstream
            // ->transform() isn't left inferring the item type from a mismatched empty base
            // Illuminate\Support\Collection.
            $scheduleTransactions = new \Illuminate\Database\Eloquent\Collection();
        } else {
            $scheduleTransactions = Transaction::with([
                'transactionItems.category',
                'transactionSchedule',
            ])
                ->whereHas('transactionItems', function ($query) use ($categories) {
                    $query->whereIn('category_id', $categories->pluck('id'));
                })
                ->where('user_id', $request->user()->id)
                ->byType('standard')
                ->isSchedule()
                ->whereHas('transactionSchedule', fn ($query) => $query->where('active', true))
                ->when($accountSelection === 'selected' && $accountEntity, fn ($query) => $query->whereHasMorph(
                    'config',
                    TransactionDetailStandard::class,
                    fn ($query) => $query->where('account_from_id', $accountEntity)
                        ->orWhere('account_to_id', $accountEntity)
                ))
                ->get();
        }

        // Computed once and reused below, rather than re-plucking ids from $categories inside
        // every per-item filter closure.
        $categoryIds = $categories->pluck('id');

        // Unify currencies and calculate amounts only for given categories. Summed exactly
        // (BigDecimal) rather than Collection::sum()'s native float +=, since a transaction can
        // have multiple items.
        $scheduleTransactions->transform(function ($transaction) use ($categoryIds) {
            $transaction->sum = $transaction->transactionItems
                ->filter(fn ($item) => $categoryIds->contains($item->category_id))
                ->reduce(fn (BigDecimal $carry, $item) => $carry->plus($item->amount->getAmount()), BigDecimal::zero());

            return $transaction;
        });

        // Get all instances by month, from each schedule's next_date onward.
        $scheduleInstances = $this->getScheduleInstances(
            $scheduleTransactions,
            'next',
            null,
            $request->user()->end_date
        );

        $forecastCompact = [];
        $budgetCompact = [];
        // FR-7: which Budget rows contributed to each period's total, for the drill-down.
        $budgetBreakdown = [];
        // Same idea as $budgetBreakdown, for the schedule-transaction (forecast) side.
        $scheduleBreakdown = [];

        $scheduleInstances->each(function ($transaction) use (&$forecastCompact, &$scheduleBreakdown, $baseCurrency, $categoryIds, $allCurrencies) {
            $period = $transaction->date->format('Y-m-01');
            $currency_id = $transaction->currency_id ?? $baseCurrency->id;

            if (
                !array_key_exists($period, $forecastCompact)
                || !array_key_exists($currency_id, $forecastCompact[$period])
            ) {
                $forecastCompact[$period][$currency_id] = BigDecimal::zero();
            }

            // FR-8: apply this occurrence's own inflation-compounded multiplier.
            $amount = $transaction->sum
                ->multipliedBy($transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL ? -1 : 1)
                ->multipliedBy($transaction->inflationMultiplier);

            $forecastCompact[$period][$currency_id] = $forecastCompact[$period][$currency_id]->plus($amount);

            $categoryNames = $transaction->transactionItems
                ->filter(fn ($item) => $categoryIds->contains($item->category_id))
                ->pluck('category.name')
                ->unique()
                ->values()
                ->all();

            $scheduleBreakdown[$period][] = [
                'transaction_id' => $transaction->originalId,
                'category_names' => $categoryNames,
                'amount' => $amount->toFloat(),
                'currency_id' => $currency_id,
                'currency' => $allCurrencies->get($currency_id),
                'transaction_schedule' => [
                    'frequency' => $transaction->transactionSchedule->frequency,
                    'interval' => $transaction->transactionSchedule->interval,
                    'by_day' => $transaction->transactionSchedule->by_day,
                    'by_month' => $transaction->transactionSchedule->by_month,
                    'start_date' => $transaction->transactionSchedule->start_date,
                    'end_date' => $transaction->transactionSchedule->end_date,
                    'inflation' => $transaction->transactionSchedule->inflation,
                ],
            ];
        });

        // FR-2 #2: all active standalone Budget rows for the requested categories, regardless of
        // account_id - an account-scoped row and an account-agnostic row for the same category
        // are both included, by design (see background.md "Account Scoping for Budgets") - unless
        // the report's own account-scope filter narrows this down: 'selected' keeps only that
        // account's own budgets, 'none' keeps only account-agnostic budgets (mirroring
        // getScheduledItems()'s identical 'none' => whereNull('account_id') handling of Budget
        // rows - a budget with no account is exactly what "none" means here, not "no budgets"),
        // and 'any' (default) keeps the unfiltered FR-2 behaviour.
        $budgets = Budget::with(['category', 'account.config.currency'])
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->whereIn('category_id', $categories->pluck('id'))
            ->when($accountSelection === 'selected' && $accountEntity, fn ($query) => $query->where('account_id', $accountEntity))
            ->when($accountSelection === 'none', fn ($query) => $query->whereNull('account_id'))
            ->get();

        $inflationCalculator = new InflationCalculator();

        foreach ($budgets as $budget) {
            $occurrences = $this->budgetService->projectOccurrences(
                $budget,
                $budget->start_date,
                $request->user()->end_date
            );

            // Account-scoped budgets resolve their own currency via the eager-loaded
            // account.config.currency relation (no extra query); account-agnostic ones would
            // otherwise re-lazy-load user->baseCurrency() per row, so reuse the value already
            // computed once above instead.
            $budgetCurrency = $budget->account_id ? $budget->currency() : $baseCurrency;
            $currency_id = $budgetCurrency !== null ? $budgetCurrency->id : $baseCurrency->id;

            foreach ($occurrences as $occurrenceDate) {
                $period = $occurrenceDate->format('Y-m-01');

                // Same "apply to a unit multiplier" pattern as Transaction::scheduleInstances()'s
                // inflationMultiplier, so $budget->amount's exact BigDecimal is only ever
                // multiplied (never added to/subtracted from a float).
                // Cast to string: BigDecimal::multipliedBy() below deprecates a raw float operand.
                $multiplier = (string) $inflationCalculator->applyAnnualRate(
                    1.0,
                    $budget->inflation,
                    $budget->start_date,
                    $occurrenceDate,
                );
                $amount = $budget->amount->getAmount()
                    ->multipliedBy($multiplier)
                    ->multipliedBy($budget->transaction_type->amountMultiplier());

                if (
                    !array_key_exists($period, $budgetCompact)
                    || !array_key_exists($currency_id, $budgetCompact[$period])
                ) {
                    $budgetCompact[$period][$currency_id] = BigDecimal::zero();
                }
                $budgetCompact[$period][$currency_id] = $budgetCompact[$period][$currency_id]->plus($amount);

                $budgetBreakdown[$period][] = [
                    'budget_id' => $budget->id,
                    'category_id' => $budget->category_id,
                    'category_name' => $budget->category->name,
                    'account_id' => $budget->account_id,
                    'account_name' => $budget->account?->name,
                    'amount' => $amount->toFloat(),
                    'currency_id' => $currency_id,
                    'currency' => $allCurrencies->get($currency_id),
                    'transaction_schedule' => [
                        'frequency' => $budget->frequency,
                        'interval' => $budget->interval,
                        'by_day' => $budget->by_day,
                        'by_month' => $budget->by_month,
                        'start_date' => $budget->start_date,
                        'end_date' => $budget->end_date,
                        'inflation' => $budget->inflation,
                    ],
                ];
            }
        }

        foreach ($budgetCompact as $period => $periodData) {
            $carbonPeriod = Carbon::parse($period);
            foreach ($periodData as $currency => $value) {
                if (!array_key_exists($period, $dataByPeriod)) {
                    $dataByPeriod[$period] = [
                        'actual' => null,
                        'budget' => BigDecimal::zero(),
                        'forecast' => BigDecimal::zero(),
                    ];
                }

                $rate = $this->getLatestRateFromMap($currency, $carbonPeriod, $allRatesMap, $baseCurrency->id);

                if ($rate === null && $currency !== $baseCurrency->id) {
                    $currenciesWithMissingRates[$currency] = true;
                }

                // As above: $rate is an inexact AVG() over daily rates, so multiplying by it
                // doesn't need to be exact - only the summation into 'budget' does.
                $dataByPeriod[$period]['budget'] = $dataByPeriod[$period]['budget']->plus($value->multipliedBy((string) ($rate ?? 1)));
            }
        }

        foreach ($forecastCompact as $period => $periodData) {
            $carbonPeriod = Carbon::parse($period);
            foreach ($periodData as $currency => $value) {
                if (!array_key_exists($period, $dataByPeriod)) {
                    $dataByPeriod[$period] = [
                        'actual' => null,
                        'budget' => BigDecimal::zero(),
                        'forecast' => BigDecimal::zero(),
                    ];
                }

                $rate = $this->getLatestRateFromMap($currency, $carbonPeriod, $allRatesMap, $baseCurrency->id);

                if ($rate === null && $currency !== $baseCurrency->id) {
                    $currenciesWithMissingRates[$currency] = true;
                }

                $dataByPeriod[$period]['forecast'] = $dataByPeriod[$period]['forecast']->plus($value->multipliedBy((string) ($rate ?? 1)));
            }
        }

        // Transform standard data into amCharts format. Collapse to float only here, at the
        // chart-response boundary: budgetchart.js does plain JS `+` on these values (amCharts
        // consumption), which requires real JSON numbers, not decimal strings.
        $result = [];
        foreach ($dataByPeriod as $key => $value) {
            $result[] = [
                'period' => new Carbon($key),
                'actual' => $value['actual']?->toFloat(),
                'budget' => $value['budget']->toFloat(),
                'forecast' => $value['forecast']->toFloat(),
                'budgetBreakdown' => $budgetBreakdown[$key] ?? [],
                'scheduleBreakdown' => $scheduleBreakdown[$key] ?? [],
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
        $cacheKey = CategoryWaterfallCacheService::key($request->user()->id, $transactionType, $dataType, $year, $month);

        $payload = Cache::remember($cacheKey, CategoryWaterfallCacheService::ttl(), fn () => $this->buildCategoryWaterfallPayload($request, $transactionType, $year, $month));

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
        string $transactionType,
        int $year,
        int|null $month
    ): array {
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
        $standardBucketTypes = [TransactionTypeEnum::WITHDRAWAL->value, TransactionTypeEnum::DEPOSIT->value];
        $investmentBucketTypes = TransactionTypeEnum::investmentTypesWithAmountValues();

        if ($transactionType === 'all' || $transactionType === 'standard') {
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

            $investmentTransactions->each(function ($transaction) use (&$dataByCategory, &$categoryIdByBucket, &$transactionTypesByBucket, $investmentBucketTypes, &$currenciesWithMissingRates, $baseCurrency, $allRatesMap) {
                // Determine the category group. This should be the top level category ideally.
                $category = ($transaction->transaction_type->amountMultiplier() === 1
                    ? __('Investment income')
                    : __('Investment payment'));

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
                'category' => $category,
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
