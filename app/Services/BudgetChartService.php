<?php

namespace App\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Budget vs actual vs forecast data, shared by the budget report endpoint and the budget
 * suggestion detector. Extracted from ReportApiController::budgetChart() without behaviour change.
 */
class BudgetChartService
{
    use CurrencyTrait;
    use ScheduleTrait;

    public function __construct(private BudgetService $budgetService)
    {
    }

    /**
     * Chart data for the given categories, aggregated by month.
     *
     * @param Collection $categories Categories to include (children already resolved by the caller)
     * @param string|null $accountSelection 'any' (default), 'selected' or 'none'
     * @return array{chartData: array<int, array<string, mixed>>, currenciesWithoutRates: array<int, array{id: int, iso_code: string, name: string}>}
     */
    public function chart(User $user, Collection $categories, ?string $accountSelection = null, string|int|null $accountEntity = null): array
    {
        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency($user->id);
        $allRatesMap = $this->allCurrencyRatesByMonth($user->id);
        // Already keyed by id - see CurrencyTrait::getAllCurrencies().
        $allCurrencies = $this->getAllCurrencies($user->id);

        // Get all standard transactions with related categories
        if ($accountSelection === 'none') {
            $standardTransactions = new Collection();
        } else {
            $standardTransactions = TransactionItem::with([
                'transaction',
                'transaction.currency',
            ])
                ->whereIn('category_id', $categories->pluck('id'))
                ->whereHas('transaction', function ($query) use ($user, $accountSelection, $accountEntity) {
                    $query->whereUserId($user->id)
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
                ->where('user_id', $user->id)
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
            $user->end_date
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
                    'days_before_month_end' => $transaction->transactionSchedule->days_before_month_end,
                    'last_business_day_of_month' => $transaction->transactionSchedule->last_business_day_of_month,
                    'count' => $transaction->transactionSchedule->count,
                    'start_date' => $transaction->transactionSchedule->start_date,
                    'end_date' => $transaction->transactionSchedule->end_date,
                    'inflation' => $transaction->transactionSchedule->inflation,
                ],
            ];
        });

        // FR-2 #2: all standalone Budget rows for the requested categories - closed ones (no
        // occurrence left, active = false) included, since their past periods are still history
        // the chart must show - regardless of account_id - an account-scoped row and an account-agnostic row for the same category
        // are both included, by design (see background.md "Account Scoping for Budgets") - unless
        // the report's own account-scope filter narrows this down: 'selected' keeps only that
        // account's own budgets, 'none' keeps only account-agnostic budgets (mirroring
        // getScheduledItems()'s identical 'none' => whereNull('account_id') handling of Budget
        // rows - a budget with no account is exactly what "none" means here, not "no budgets"),
        // and 'any' (default) keeps the unfiltered FR-2 behaviour.
        $budgets = Budget::with(['category', 'account.config.currency'])
            ->where('user_id', $user->id)
            ->whereIn('category_id', $categories->pluck('id'))
            ->when($accountSelection === 'selected' && $accountEntity, fn ($query) => $query->where('account_id', $accountEntity))
            ->when($accountSelection === 'none', fn ($query) => $query->whereNull('account_id'))
            ->get();

        $inflationCalculator = new InflationCalculator();

        foreach ($budgets as $budget) {
            $occurrences = $this->budgetService->projectOccurrences(
                $budget,
                $budget->start_date,
                $user->end_date
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
                        'days_before_month_end' => $budget->days_before_month_end,
                        'last_business_day_of_month' => $budget->last_business_day_of_month,
                        'count' => $budget->count,
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

        return [
            'chartData' => $result,
            'currenciesWithoutRates' => $missingRateCurrencies,
        ];
    }

    /**
     * Per-category, per-month actual, budget and forecast for every category of the user, in base
     * currency, scope "any" (no account filter). Same numbers chart() returns for a single
     * category, computed in a fixed number of queries instead of one chart() run per category.
     *
     * Actuals come from one grouped query; schedules and Budget rows are loaded and projected once
     * and attributed to their own categories. Sums are additive per category, so callers combine
     * categories into analysis units by adding periods.
     *
     * @return array{
     *     categories: array<int, array<string, array{actual: BigDecimal|null, budget: BigDecimal, forecast: BigDecimal}>>,
     *     missingRateCurrencyIds: array<int, int>,
     *     missingRatePeriods: array<int, array<string, true>>
     * } Categories are keyed by id, then by period (Y-m-01). 'actual' is null where the
     *     category has no standard transaction in the period, as in chart(). 'missingRatePeriods'
     *     lists, per category, the periods (Y-m-01) whose amounts fell back to a 1:1 rate because the
     *     currency has no rates at all.
     */
    public function aggregateByCategory(User $user): array
    {
        $baseCurrency = $this->getBaseCurrency($user->id);
        $allRatesMap = $this->allCurrencyRatesByMonth($user->id);
        $baseCurrencyId = $baseCurrency->id;

        // [category][period][currency] => BigDecimal, per series
        $compact = ['actual' => [], 'budget' => [], 'forecast' => []];

        // Actuals: one grouped query. Signed like chart(): withdrawals negative, others positive.
        $rows = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.schedule', false)
            ->where('transactions.config_type', 'standard')
            ->whereNotNull('transaction_items.category_id')
            ->groupBy('transaction_items.category_id', 'period', 'transactions.currency_id', 'transactions.transaction_type')
            ->selectRaw(
                "transaction_items.category_id, DATE_FORMAT(transactions.date, '%Y-%m-01') AS period, "
                . 'transactions.currency_id, transactions.transaction_type, SUM(transaction_items.amount) AS total'
            )
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $total = BigDecimal::of($row->total);
            if ($row->transaction_type === TransactionTypeEnum::WITHDRAWAL->value) {
                $total = $total->negated();
            }
            $this->addTo($compact['actual'], (int) $row->category_id, $row->period, (int) ($row->currency_id ?? $baseCurrencyId), $total);
        }

        // Forecast: active standard schedules, projected once from each next_date.
        $schedules = Transaction::with(['transactionItems', 'transactionSchedule'])
            ->where('user_id', $user->id)
            ->byType('standard')
            ->isSchedule()
            ->whereHas('transactionSchedule', fn ($query) => $query->where('active', true))
            ->get();

        // Instances of one schedule share their items, and the Money cast is costly per access,
        // so each item's amount is resolved once per schedule transaction.
        $itemAmounts = [];
        $this->getScheduleInstances($schedules, 'next', null, $user->end_date)->each(
            function ($transaction) use (&$compact, &$itemAmounts, $baseCurrencyId) {
                $sign = $transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL ? -1 : 1;
                $period = $transaction->date->format('Y-m-01');
                $currencyId = (int) ($transaction->currency_id ?? $baseCurrencyId);

                $itemAmounts[$transaction->originalId] ??= $transaction->transactionItems
                    ->map(fn ($item) => [(int) $item->category_id, $item->amount->getAmount()])
                    ->all();

                foreach ($itemAmounts[$transaction->originalId] as [$categoryId, $amount]) {
                    $this->addTo(
                        $compact['forecast'],
                        $categoryId,
                        $period,
                        $currencyId,
                        $amount->multipliedBy($sign)->multipliedBy($transaction->inflationMultiplier)
                    );
                }
            }
        );

        // Budget rows: all active rows regardless of account, as chart() does for scope "any".
        $inflationCalculator = new InflationCalculator();
        $budgets = Budget::with(['account.config.currency'])
            ->where('user_id', $user->id)
            ->where('active', true)
            ->get();

        foreach ($budgets as $budget) {
            $currencyId = ($budget->account_id ? $budget->currency()?->id : null) ?? $baseCurrencyId;
            // Raw decimal: the Money cast would resolve the budget's currency (a lazy query) just to read the amount.
            $baseAmount = BigDecimal::of($budget->getRawOriginal('amount'))->multipliedBy($budget->transaction_type->amountMultiplier());

            foreach ($this->budgetService->projectOccurrences($budget, $budget->start_date, $user->end_date) as $occurrenceDate) {
                $multiplier = (string) $inflationCalculator->applyAnnualRate(1.0, $budget->inflation, $budget->start_date, $occurrenceDate);
                $this->addTo($compact['budget'], $budget->category_id, $occurrenceDate->format('Y-m-01'), $currencyId, $baseAmount->multipliedBy($multiplier));
            }
        }

        // Convert to base currency at each period's monthly rate, as chart() does.
        $categories = [];
        $missing = [];
        $missingPeriods = [];
        // Rate lookups scan the rates map and parse dates, so resolve each currency/month once.
        $rateCache = [];
        foreach ($compact as $series => $byCategory) {
            foreach ($byCategory as $categoryId => $byPeriod) {
                foreach ($byPeriod as $period => $byCurrency) {
                    foreach ($byCurrency as $currencyId => $value) {
                        $rate = array_key_exists("{$currencyId}|{$period}", $rateCache)
                            ? $rateCache["{$currencyId}|{$period}"]
                            : ($rateCache["{$currencyId}|{$period}"] = $this->getLatestRateFromMap($currencyId, Carbon::parse($period), $allRatesMap, $baseCurrencyId));
                        if ($rate === null && $currencyId !== $baseCurrencyId) {
                            $missing[$currencyId] = $currencyId;
                            $missingPeriods[$categoryId][$period] = true;
                        }

                        $categories[$categoryId][$period] ??= ['actual' => null, 'budget' => BigDecimal::zero(), 'forecast' => BigDecimal::zero()];
                        $converted = $value->multipliedBy((string) ($rate ?? 1));
                        $categories[$categoryId][$period][$series] = ($categories[$categoryId][$period][$series] ?? BigDecimal::zero())->plus($converted);
                    }
                }
            }
        }

        foreach ($categories as &$byPeriod) {
            ksort($byPeriod);
        }

        return ['categories' => $categories, 'missingRateCurrencyIds' => array_values($missing), 'missingRatePeriods' => $missingPeriods];
    }

    /**
     * @param array<int, array<string, array<int, BigDecimal>>> $target
     */
    private function addTo(array &$target, int $categoryId, string $period, int $currencyId, BigDecimal $amount): void
    {
        $target[$categoryId][$period][$currencyId] = ($target[$categoryId][$period][$currencyId] ?? BigDecimal::zero())->plus($amount);
    }
}
