<?php

namespace App\Services;

use App\Http\Traits\CurrencyTrait;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * On-demand budget drift review. Compares the recent level of actuals (L) with the forward
 * expectation (E = budget + schedule forecast) per analysis unit and proposes budget changes.
 *
 * Read-only: it returns plain arrays (the response contract in
 * .ai/docs/specifications/budget-drift-suggestions/specification.md) and writes nothing.
 * Thresholds live in config('yaffa.budget_suggestions').
 */
class BudgetSuggestionService
{
    use CurrencyTrait;

    public const DETECTOR_VERSION = 1;

    /** Months per analysis granularity. Aligned to calendar periods (Jan/Apr/Jul/Oct, January). */
    private const MONTHS = ['month' => 1, 'quarter' => 3, 'year' => 12];

    public function __construct(
        private readonly BudgetChartService $chartService,
        private readonly BudgetService $budgetService,
        private readonly RecurrenceRuleService $recurrenceRuleService,
        private readonly InflationCalculator $inflationCalculator,
    ) {
    }

    /**
     * @param int[]|null $categoryIds Explicit categories, or null to review all active, non-excluded ones
     * @param string|null $dataThrough A month (Y-m); defaults to the last complete month, never the current one
     * @return array{run: array<string, mixed>, units: array<int, array<string, mixed>>, series: array<int, array<string, mixed>>|null}
     */
    public function run(
        User $user,
        ?array $categoryIds = null,
        ?string $dataThrough = null,
        ?string $sensitivity = null,
        ?string $bias = null,
    ): array {
        $cfg = config('yaffa.budget_suggestions');
        $sensitivity = array_key_exists((string) $sensitivity, $cfg['sensitivity']) ? $sensitivity : $cfg['default_sensitivity'];
        $bias = in_array($bias, ['neutral', 'conservative'], true) ? $bias : $cfg['default_bias'];

        $baseCurrency = $this->getBaseCurrency($user->id);
        if ($baseCurrency === null) {
            throw new InvalidArgumentException('The user has no base currency.');
        }

        $today = Carbon::now();
        $lastCompleteMonth = $this->idx($today) - 1;
        $through = $dataThrough !== null ? min($this->idx(Carbon::parse($dataThrough)), $lastCompleteMonth) : $lastCompleteMonth;

        $aggregate = $this->chartService->aggregateByCategory($user);
        $categories = $user->categories()->with('parent')->get()->keyBy('id');
        $explicit = $categoryIds !== null;
        $selected = $explicit ? array_flip($categoryIds) : [];

        $c = [
            'cfg' => $cfg,
            'sens' => $cfg['sensitivity'][$sensitivity],
            'bias' => $bias,
            'through' => $through,
            'today' => $today,
            'endIdx' => $this->idx($user->end_date),
            'base' => $baseCurrency,
            'rates' => $this->allCurrencyRatesByMonth($user->id),
            'agg' => $aggregate['categories'],
            'missing' => $aggregate['missingRatePeriods'],
            'categories' => $categories,
        ];
        $c['avgMonthly'] = $this->averageMonthlyExpenses($c);
        $c['floor'] = $cfg['materiality_floor_share'] * $c['avgMonthly'];
        [$c['budgets'], $c['scheduleMonths']] = $this->loadExpectations($user, $baseCurrency);

        $units = [];
        $analysed = [];
        foreach ($this->buildUnits($categories, $c) as $unit) {
            $root = $unit['root'];
            $inScope = $explicit
                ? (bool) array_intersect_key(array_flip($unit['members']), $selected)
                : true;
            if (!$inScope || !$root->active) {
                continue;
            }

            if ($explicit) {
                foreach ($unit['members'] as $memberId) {
                    $member = $categories->get($memberId);
                    if ($memberId !== $root->id && isset($selected[$memberId]) && $member->active && !$member->calculate_budget_suggestions) {
                        $units[] = $this->unitOutput($member, null, 'excluded', null);
                    }
                }
            }

            if ($this->isExcluded($root, $categories)) {
                if ($explicit) {
                    $units[] = $this->unitOutput($root, null, 'excluded', null);
                }

                continue;
            }

            $series = $this->sumSeries($unit['members'], $c);
            if (!$explicit && !$this->hasData($series)) {
                continue;
            }

            $result = $this->evaluate($unit, $series, $c);
            $units[] = $this->unitOutput($root, $result['granularity'], $result['verdict'], $result['suggestion']);
            $analysed[] = ['unit' => $unit, 'result' => $result];
        }

        return [
            'run' => [
                'computed_at' => $today->toAtomString(),
                'scope' => $explicit ? 'selected' : 'all',
                'data_through' => $this->date($through, 'Y-m'),
                'sensitivity' => $sensitivity,
                'bias' => $bias,
                'tolerance' => $c['sens']['tolerance'],
                'detector_version' => self::DETECTOR_VERSION,
                'average_monthly_expenses' => round($c['avgMonthly'], 2),
                'materiality_floor_share' => (float) $cfg['materiality_floor_share'],
                'base_currency' => $this->currencyArray($baseCurrency),
                'currencies_without_rates' => $this->getMissingRateCurrencies($aggregate['missingRateCurrencyIds']),
            ],
            'units' => $this->orderUnits($units),
            'series' => $explicit ? $this->recommendedSeries($analysed, $c) : null,
        ];
    }

    // ---------------------------------------------------------------- units and scope

    /**
     * Units form bottom-up. A child is its own unit if it is not excluded and has Budget rows,
     * schedule items or passes the regularity test on its own transactions; otherwise it (and its
     * actuals) rolls into the parent's unit.
     *
     * @return array<int, array{root: Category, members: int[]}>
     */
    private function buildUnits(Collection $categories, array $c): array
    {
        $children = $categories->groupBy('parent_id');
        $units = [];

        $collect = function (Category $category) use (&$collect, $children, $c, &$units): array {
            $members = [$category->id];
            foreach ($children->get($category->id, collect()) as $child) {
                $childMembers = $collect($child);
                if ($this->isOwnUnit($child, $childMembers, $c)) {
                    $units[] = ['root' => $child, 'members' => $childMembers];
                } else {
                    $members = array_merge($members, $childMembers);
                }
            }

            return $members;
        };

        foreach ($categories as $category) {
            if ($category->parent_id === null || !$categories->has($category->parent_id)) {
                $units[] = ['root' => $category, 'members' => $collect($category)];
            }
        }

        return $units;
    }

    /**
     * @param int[] $members The child and its own rolled-up descendants
     */
    private function isOwnUnit(Category $child, array $members, array $c): bool
    {
        if (!$child->active || !$child->calculate_budget_suggestions) {
            return false;
        }

        if (isset($c['budgets'][$child->id]) || isset($c['scheduleMonths'][$child->id])) {
            return true;
        }

        return $this->regularCadence($this->sumSeries($members, $c), $c) !== null;
    }

    /**
     * Excluded when its own switch or any ancestor's is off.
     */
    private function isExcluded(Category $category, Collection $categories): bool
    {
        $seen = [];
        while ($category !== null && !isset($seen[$category->id])) {
            if (!$category->calculate_budget_suggestions) {
                return true;
            }
            $seen[$category->id] = true;
            $category = $category->parent_id ? $categories->get($category->parent_id) : null;
        }

        return false;
    }

    // ---------------------------------------------------------------- data loading

    /**
     * Active Budget rows and active schedule cadences by category.
     *
     * @return array{0: array<int, Budget[]>, 1: array<int, int[]>}
     */
    private function loadExpectations(User $user, Currency $baseCurrency): array
    {
        $budgets = [];
        Budget::with('account.config.currency')
            ->where('user_id', $user->id)
            ->where('active', true)
            ->get()
            ->each(function (Budget $budget) use (&$budgets) {
                $budgets[$budget->category_id][] = $budget;
            });

        $scheduleMonths = [];
        Transaction::with(['transactionItems', 'transactionSchedule'])
            ->where('user_id', $user->id)
            ->byType('standard')
            ->isSchedule()
            ->whereHas('transactionSchedule', fn ($query) => $query->where('active', true))
            ->get()
            ->each(function (Transaction $transaction) use (&$scheduleMonths) {
                $months = $this->cadenceMonths($transaction->transactionSchedule->frequency, (int) $transaction->transactionSchedule->interval);
                foreach ($transaction->transactionItems as $item) {
                    $scheduleMonths[(int) $item->category_id][] = $months;
                }
            });

        return [$budgets, $scheduleMonths];
    }

    /**
     * Period length of a recurrence in months. Daily and weekly intervals convert by actual
     * length (30.4375 days a month, rounded, at least one month), so 13 weeks is 3 months.
     */
    private function cadenceMonths(string $frequency, int $interval): int
    {
        $interval = max($interval, 1);

        return match ($frequency) {
            'YEARLY' => 12 * $interval,
            'MONTHLY' => $interval,
            'WEEKLY' => max((int) round($interval * 7 / 30.4375), 1),
            default => max((int) round($interval / 30.4375), 1),
        };
    }

    /**
     * Monthly series of a unit: the sum of its member categories.
     *
     * @param int[] $ids
     * @return array<int, array{a: float|null, e: float}> month index => actual (null if no transaction) and expected (budget + forecast)
     */
    private function sumSeries(array $ids, array $c): array
    {
        $series = [];
        foreach ($ids as $id) {
            foreach ($c['agg'][$id] ?? [] as $period => $values) {
                $i = $this->idx(Carbon::parse($period));
                $series[$i] ??= ['a' => null, 'e' => 0.0];
                if ($values['actual'] !== null) {
                    $series[$i]['a'] = ($series[$i]['a'] ?? 0.0) + $values['actual']->toFloat();
                }
                $series[$i]['e'] += $values['budget']->plus($values['forecast'])->toFloat();
            }
        }
        ksort($series);

        return $series;
    }

    private function hasData(array $series): bool
    {
        foreach ($series as $point) {
            if ($point['a'] !== null || abs($point['e']) > 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Average of the negative category-month actuals over the 12 complete months up to data_through.
     * Approximates "categorized withdrawals": a category's refunds net against its withdrawals.
     */
    private function averageMonthlyExpenses(array $c): float
    {
        $from = $c['through'] - 11;
        $total = 0.0;
        foreach ($c['agg'] as $periods) {
            foreach ($periods as $period => $values) {
                $i = $this->idx(Carbon::parse($period));
                if ($i >= $from && $i <= $c['through'] && $values['actual'] !== null && $values['actual']->isNegative()) {
                    $total -= $values['actual']->toFloat();
                }
            }
        }

        return $total / 12;
    }

    // ---------------------------------------------------------------- the detector

    /**
     * @param array{root: Category, members: int[]} $unit
     * @return array{verdict: string, granularity: string|null, suggestion: array<string, mixed>|null, proposal?: array<string, mixed>}
     */
    private function evaluate(array $unit, array $series, array $c): array
    {
        $cfg = $c['cfg'];
        $rows = collect($unit['members'])->flatMap(fn ($id) => $c['budgets'][$id] ?? [])->values();
        $scheduleMonths = collect($unit['members'])->flatMap(fn ($id) => $c['scheduleMonths'][$id] ?? [])->all();
        $defaultMonths = self::MONTHS[$unit['root']->default_aggregation] ?? 1;
        $isNew = $rows->isEmpty() && $scheduleMonths === [];

        if ($isNew) {
            $regular = $this->regularCadence($series, $c);
            if ($regular === null) {
                return $this->verdict($this->historyMonths($series, $c) < $cfg['regularity']['month'][1] ? 'insufficient_data' : 'irregular', $defaultMonths);
            }
            $m = self::MONTHS[$regular];
        } else {
            $cadences = array_merge($scheduleMonths, $rows->map(fn (Budget $b) => $this->cadenceMonths($b->frequency, (int) $b->interval))->all());
            $longest = max($cadences);
            if ($longest > 12) {
                return $this->verdict('insufficient_data', 12);
            }
            $m = max($defaultMonths, $longest <= 1 ? 1 : ($longest <= 3 ? 3 : 12));
        }
        $g = array_search($m, self::MONTHS, true);

        // History check
        $last = $this->lastComplete($c['through'], $m);
        $firstActual = $this->firstActual($series);
        if ($firstActual === null || $last < $this->periodStart($firstActual, $m)) {
            return $this->verdict('insufficient_data', $m);
        }
        $available = intdiv($last - $this->periodStart($firstActual, $m), $m) + 1;
        if ($available < $cfg['min_history'][$g]) {
            return $this->verdict('insufficient_data', $m);
        }
        $windowCount = min($cfg['level_window'][$g], $available);
        $windowStart = $last - ($windowCount - 1) * $m;

        if ($this->hasMissingRates($unit['members'], $windowStart, $last + $m - 1, $c)) {
            return $this->verdict('missing_rates', $m);
        }

        // Cooldown: a contributing Budget row started within the last M periods, or starts in the future
        $currentStart = $this->periodStart($this->idx($c['today']), $m);
        if (!$isNew) {
            $boundary = $currentStart - $cfg['cooldown'][$g] * $m;
            if ($rows->contains(fn (Budget $b) => $this->idx($b->start_date) >= $boundary)) {
                return $this->verdict('cooldown', $m);
            }
        }

        // Forward expectation E over whole periods that the forecast horizon covers
        $effective = $currentStart + $m;
        $horizon = min($cfg['horizon'][$g], intdiv($c['endIdx'] - $effective + 1, $m));
        if ($horizon < 1) {
            return $this->verdict('insufficient_data', $m);
        }
        if ($this->hasMissingRates($unit['members'], $effective, $effective + $horizon * $m - 1, $c)) {
            return $this->verdict('missing_rates', $m);
        }
        $expected = $this->sumExpected($series, $effective, $horizon * $m) / $horizon;

        // Level L: plain mean of the window's complete actuals
        $level = 0.0;
        for ($p = $windowStart; $p <= $last; $p += $m) {
            $level += $this->foldActual($series, $p, $m);
        }
        $level /= $windowCount;
        $gap = $level - $expected;

        // Direction and bias
        $neutral = $this->isNeutral($unit['members'], $expected, $effective, $horizon * $m, $windowStart, $last + $m - 1, $c);
        $conservative = $c['bias'] === 'conservative' && !$neutral;
        $unfavorable = $gap < 0;
        $factor = $conservative ? ($unfavorable ? $cfg['bias']['unfavorable_factor'] : $cfg['bias']['favorable_factor']) : 1.0;
        $tolerance = $c['sens']['tolerance'] * $factor;

        // Materiality: relative to E. The optional floor (off when its share is 0) is monthly, the gap per period.
        $aboveFloor = $cfg['materiality_floor_share'] <= 0 || abs($gap) / $m > $c['floor'];
        if (!(abs($gap) > $tolerance * abs($expected) && $aboveFloor)) {
            return $this->verdict('no_drift', $m);
        }

        // Persistence: the sign of (actual - E) matches the gap in k of the last n periods
        [$k, $n] = $c['sens']['persistence'][$g];
        $evidence = [];
        $matches = 0;
        for ($i = 0; $i < $n; $i++) {
            $p = $last - $i * $m;
            $actual = $this->foldActual($series, $p, $m);
            if (($actual - $expected) * $gap > 0) {
                $matches++;
            }
            $evidence[] = ['period' => $this->date($p), 'actual' => round($actual, 2), 'expected' => round($expected, 2)];
        }
        if ($matches < $k) {
            return $this->verdict('no_drift', $m);
        }

        $target = $level;
        if ($conservative && ($isNew || $unfavorable)) {
            $target = $level - $cfg['bias']['margin'] * abs($level);
        }

        $basis = $expected !== 0.0 ? $expected : $level;
        $monthlyImpact = ($target - $expected) / $m;
        $common = [
            'effective_date' => $this->date($effective),
            'level' => round($level, 2),
            'expected' => round($expected, 2),
            'target' => round($target, 2),
            'monthly_impact' => round($monthlyImpact, 2),
            'impact_share' => $c['avgMonthly'] > 0 ? round(abs($monthlyImpact) / $c['avgMonthly'], 4) : null,
            'direction' => $neutral ? 'neutral' : ($basis < 0 ? 'expense' : 'income'),
            'persistence' => ['k' => $matches, 'n' => $n],
            'evidence' => array_reverse($evidence),
        ];

        $candidates = $isNew ? [] : $this->candidates($rows, $effective, $horizon, $m, $target - $expected, $c);
        if ($candidates === []) {
            return $this->createProposal($unit, $m, $g, $isNew, $target - $expected, $common, $c);
        }

        return $this->replaceProposal($unit, $m, $candidates, $common, $c);
    }

    /**
     * @return array{verdict: string, granularity: string|null, suggestion: null}
     */
    private function verdict(string $verdict, int $months): array
    {
        return ['verdict' => $verdict, 'granularity' => array_search($months, self::MONTHS, true) ?: 'year', 'suggestion' => null];
    }

    /**
     * Finest cadence at which the unit is regular over complete periods, or null.
     */
    private function regularCadence(array $series, array $c): ?string
    {
        $firstActual = $this->firstActual($series);
        if ($firstActual === null) {
            return null;
        }

        foreach (self::MONTHS as $g => $m) {
            [$k, $n] = $c['cfg']['regularity'][$g];
            $last = $this->lastComplete($c['through'], $m);
            $first = $this->periodStart($firstActual, $m);
            if ($last < $first || intdiv($last - $first, $m) + 1 < $n) {
                continue;
            }

            $nonZero = 0;
            for ($i = 0; $i < $n; $i++) {
                $nonZero += $this->hasActual($series, $last - $i * $m, $m) ? 1 : 0;
            }
            if ($nonZero >= $k) {
                return $g;
            }
        }

        return null;
    }

    private function historyMonths(array $series, array $c): int
    {
        $first = $this->firstActual($series);

        return $first === null ? 0 : max($c['through'] - $first + 1, 0);
    }

    /**
     * A unit whose contributing categories mix signs, with a net under neutral_net_share of the gross.
     *
     * @param int[] $ids
     */
    private function isNeutral(array $ids, float $expected, int $effective, int $horizonMonths, int $windowFrom, int $windowTo, array $c): bool
    {
        $totals = [];
        foreach ($ids as $id) {
            $categorySeries = $this->sumSeries([$id], $c);
            $totals[] = $expected !== 0.0
                ? $this->sumExpected($categorySeries, $effective, $horizonMonths)
                : $this->sumActual($categorySeries, $windowFrom, $windowTo);
        }

        $mixed = count(array_filter($totals, fn ($t) => $t > 0)) > 0 && count(array_filter($totals, fn ($t) => $t < 0)) > 0;
        $gross = array_sum(array_map('abs', $totals));

        return $mixed && $gross > 0 && abs(array_sum($totals)) < $c['cfg']['neutral_net_share'] * $gross;
    }

    /**
     * @param int[] $ids
     */
    private function hasMissingRates(array $ids, int $fromIdx, int $toIdx, array $c): bool
    {
        foreach ($ids as $id) {
            foreach (array_keys($c['missing'][$id] ?? []) as $period) {
                $i = $this->idx(Carbon::parse($period));
                if ($i >= $fromIdx && $i <= $toIdx) {
                    return true;
                }
            }
        }

        return false;
    }

    // ---------------------------------------------------------------- proposals

    /**
     * Budget rows contributing from the effective date, ranked: account-agnostic first, most
     * recently started, largest contribution over the horizon. Each carries the amount that would
     * absorb the whole gap on that row alone.
     *
     * @param Collection<int, Budget> $rows
     * @return array<int, array<string, mixed>>
     */
    private function candidates(Collection $rows, int $effective, int $horizon, int $m, float $deltaPerPeriod, array $c): array
    {
        $from = Carbon::parse($this->date($effective));
        $to = Carbon::parse($this->date($effective + $horizon * $m))->subDay();
        $candidates = [];

        foreach ($rows as $budget) {
            $dates = $this->budgetService->projectOccurrences($budget, $from, $to);
            $occurrences = count($dates);
            if ($occurrences === 0) {
                continue;
            }
            // Inflation compounds on the new amount from the effective date, so the delta spreads over the multipliers, not the count
            $multipliers = array_sum(array_map(fn ($date) => $this->inflationCalculator->applyAnnualRate(1.0, $budget->inflation, $from, $date), $dates));

            $currency = $this->rowCurrency($budget, $c['base']);
            $rate = (float) ($this->getLatestRateFromMap($currency->id, $c['today'], $c['rates'], $c['base']->id) ?? 1);
            $sign = $budget->transaction_type->amountMultiplier();
            $current = (float) $budget->getRawOriginal('amount') * $sign
                * $this->inflationCalculator->applyAnnualRate(1.0, $budget->inflation, $budget->start_date, $from);
            $occurrenceDelta = $deltaPerPeriod * $horizon / $multipliers / $rate;
            $suggested = $current + $occurrenceDelta;

            $candidates[] = [
                'budget' => $budget,
                'budget_id' => $budget->id,
                'account_id' => $budget->account_id,
                'account_name' => $budget->account?->name,
                'cadence' => ['frequency' => $budget->frequency, 'interval' => (int) $budget->interval],
                'current_amount' => round($current, 2),
                // A sign flip is not a valid proposal for that row
                'suggested_amount' => $suggested * $current > 0 ? round($suggested, 2) : null,
                'currency' => $this->currencyArray($currency),
                'weight' => abs($current * $occurrences * $rate),
            ];
        }

        usort($candidates, fn ($a, $b) => [$a['account_id'] !== null, $b['budget']->start_date->timestamp, $b['weight']]
            <=> [$b['account_id'] !== null, $a['budget']->start_date->timestamp, $a['weight']]);

        return $candidates;
    }

    private function replaceProposal(array $unit, int $m, array $candidates, array $common, array $c): array
    {
        $top = $candidates[0];
        $granularity = array_search($m, self::MONTHS, true);
        if ($top['suggested_amount'] === null) {
            return $this->verdict('needs_review', $m);
        }

        $suggestion = $common + [
            'proposal_type' => 'replace',
            'budget_id' => $top['budget_id'],
            'current_amount' => $top['current_amount'],
            'suggested_amount' => $top['suggested_amount'],
            'currency' => $top['currency'],
            'cadence' => $top['cadence'],
            'candidates' => array_map(fn ($cand) => array_diff_key($cand, ['budget' => 1, 'weight' => 1]), $candidates),
        ];
        $suggestion = ['key' => $this->key($unit['root']->id, $suggestion, $c)] + $suggestion;

        return [
            'verdict' => 'suggestion',
            'granularity' => $granularity,
            'suggestion' => $suggestion,
            'proposal' => ['type' => 'replace', 'budget' => $top['budget'], 'amount' => $top['suggested_amount'], 'effective' => $common['effective_date']],
        ];
    }

    /**
     * A new account-agnostic row in base currency, absorbing the gap to the target.
     */
    private function createProposal(array $unit, int $m, string $g, bool $isNew, float $delta, array $common, array $c): array
    {
        if (abs(round($delta, 2)) === 0.0) {
            return $this->verdict('no_drift', $m);
        }

        $cadence = $m === 12 ? ['frequency' => 'YEARLY', 'interval' => 1] : ['frequency' => 'MONTHLY', 'interval' => $m];
        $suggestion = $common + [
            'proposal_type' => 'create',
            'budget_id' => null,
            'current_amount' => null,
            'suggested_amount' => round($delta, 2),
            'currency' => $this->currencyArray($c['base']),
            'cadence' => $cadence,
            'candidates' => [],
        ];
        $suggestion = ['key' => $this->key($unit['root']->id, $suggestion, $c)] + $suggestion;

        return [
            'verdict' => 'suggestion',
            'granularity' => $g,
            'suggestion' => $suggestion,
            'proposal' => ['type' => 'create', 'cadence' => $cadence, 'amount' => round($delta, 2), 'effective' => $common['effective_date']],
        ];
    }

    /**
     * Stable fingerprint: identical runs give identical keys. The amount is bucketed on a
     * logarithmic scale of tolerance/2, so noise below that does not change the key.
     */
    private function key(int $categoryId, array $suggestion, array $c): string
    {
        $amount = $suggestion['suggested_amount'];
        $step = log(1 + $c['sens']['tolerance'] / 2);
        $bucket = ($amount < 0 ? -1 : 1) * (int) floor(log(max(abs($amount), 0.01)) / $step);

        return mb_substr(sha1(implode('|', [$categoryId, $suggestion['proposal_type'], $suggestion['budget_id'], $suggestion['effective_date'], $bucket])), 0, 16);
    }

    // ---------------------------------------------------------------- recommended series

    /**
     * Monthly expected amount (budget + forecast) for the units in scope, current and with their
     * proposals applied. Each proposed row is projected as an unsaved Budget (no cache, no id),
     * and the replaced row's own occurrences are taken out from the effective date on.
     *
     * @return array<int, array{period: string, current: float, recommended: float}>
     */
    private function recommendedSeries(array $analysed, array $c): array
    {
        $proposals = array_values(array_filter(array_column($analysed, 'result'), fn ($r) => isset($r['proposal'])));
        if ($proposals === []) {
            return [];
        }

        $from = min(array_map(fn ($r) => $this->idx(Carbon::parse($r['proposal']['effective'])), $proposals));
        $current = [];
        foreach ($analysed as $item) {
            if ($item['result']['verdict'] === 'excluded') {
                continue;
            }
            foreach ($this->sumSeries($item['unit']['members'], $c) as $i => $point) {
                $current[$i] = ($current[$i] ?? 0.0) + $point['e'];
            }
        }

        $delta = [];
        $to = Carbon::now()->setDate(intdiv($c['endIdx'], 12), $c['endIdx'] % 12 + 1, 1)->endOfMonth();
        foreach ($proposals as $result) {
            $proposal = $result['proposal'];
            $effective = Carbon::parse($proposal['effective']);
            if ($proposal['type'] === 'replace') {
                /** @var Budget $old */
                $old = $proposal['budget'];
                $currency = $this->rowCurrency($old, $c['base']);
                $sign = $old->transaction_type->amountMultiplier();
                $oldAmount = (float) $old->getRawOriginal('amount') * $sign;
                foreach ($this->budgetService->projectOccurrences($old, $effective, $to) as $date) {
                    $this->addDelta($delta, $date, -$oldAmount * $this->inflationCalculator->applyAnnualRate(1.0, $old->inflation, $old->start_date, $date), $currency->id, $c);
                }
                $inflation = $old->inflation;
                $rrule = $old->effectiveRrule();
            } else {
                $currency = $c['base'];
                $inflation = null;
                $rrule = 'FREQ=' . $proposal['cadence']['frequency'] . ';INTERVAL=' . $proposal['cadence']['interval'];
            }

            // A replacement keeps the old row's recurrence phase and count; a new row starts at the effective date
            $anchor = $proposal['type'] === 'replace' ? $old->start_date : $effective;
            $occurrences = $this->recurrenceRuleService->getRecurrenceBetween($anchor, $rrule, $effective, $to);
            foreach ($occurrences as $occurrence) {
                $date = Carbon::instance($occurrence->getStart());
                $this->addDelta($delta, $date, $proposal['amount'] * $this->inflationCalculator->applyAnnualRate(1.0, $inflation, $effective, $date), $currency->id, $c);
            }
        }

        $series = [];
        for ($i = $from; $i <= $c['endIdx']; $i++) {
            $series[] = [
                'period' => $this->date($i),
                'current' => round($current[$i] ?? 0.0, 2),
                'recommended' => round(($current[$i] ?? 0.0) + ($delta[$i] ?? 0.0), 2),
            ];
        }

        return $series;
    }

    private function addDelta(array &$delta, Carbon $date, float $amount, int $currencyId, array $c): void
    {
        $rate = (float) ($this->getLatestRateFromMap($currencyId, $date->copy()->startOfMonth(), $c['rates'], $c['base']->id) ?? 1);
        $i = $this->idx($date);
        $delta[$i] = ($delta[$i] ?? 0.0) + $amount * $rate;
    }

    // ---------------------------------------------------------------- small helpers

    /**
     * Suggestions first, by |monthly_impact| descending then category name; every other unit follows by name.
     *
     * @param array<int, array<string, mixed>> $units
     * @return array<int, array<string, mixed>>
     */
    private function orderUnits(array $units): array
    {
        usort($units, fn ($a, $b) => [$a['suggestion'] === null, $a['suggestion'] ? -abs($a['suggestion']['monthly_impact']) : 0, $a['category_name']]
            <=> [$b['suggestion'] === null, $b['suggestion'] ? -abs($b['suggestion']['monthly_impact']) : 0, $b['category_name']]);

        return $units;
    }

    private function unitOutput(Category $root, ?string $granularity, string $verdict, ?array $suggestion): array
    {
        return [
            'category_id' => $root->id,
            'category_name' => $root->full_name,
            'granularity' => $granularity,
            'verdict' => $verdict,
            'suggestion' => $suggestion,
        ];
    }

    private function rowCurrency(Budget $budget, Currency $base): Currency
    {
        $config = $budget->account_id ? $budget->account?->config : null;
        $currency = $config instanceof Account ? $config->currency : null;

        return $currency instanceof Currency ? $currency : $base;
    }

    /**
     * @return array{id: int, iso_code: string}
     */
    private function currencyArray(Currency $currency): array
    {
        return ['id' => $currency->id, 'iso_code' => $currency->iso_code];
    }

    /** Month index: year * 12 + month - 1. Periods align on multiples of their length in months. */
    private function idx(Carbon $date): int
    {
        return $date->year * 12 + $date->month - 1;
    }

    private function date(int $idx, string $format = 'Y-m-d'): string
    {
        return Carbon::create(intdiv($idx, 12), $idx % 12 + 1, 1)->format($format);
    }

    private function periodStart(int $idx, int $m): int
    {
        return intdiv($idx, $m) * $m;
    }

    /** Start of the latest period whose last month is on or before data_through. */
    private function lastComplete(int $through, int $m): int
    {
        return intdiv($through - $m + 1, $m) * $m;
    }

    private function firstActual(array $series): ?int
    {
        foreach ($series as $i => $point) {
            if ($point['a'] !== null) {
                return $i;
            }
        }

        return null;
    }

    private function foldActual(array $series, int $start, int $m): float
    {
        return $this->sumActual($series, $start, $start + $m - 1);
    }

    private function sumActual(array $series, int $from, int $to): float
    {
        $total = 0.0;
        for ($i = $from; $i <= $to; $i++) {
            $total += $series[$i]['a'] ?? 0.0;
        }

        return $total;
    }

    private function hasActual(array $series, int $start, int $m): bool
    {
        for ($i = $start; $i < $start + $m; $i++) {
            if (($series[$i]['a'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    private function sumExpected(array $series, int $from, int $months): float
    {
        $total = 0.0;
        for ($i = $from; $i < $from + $months; $i++) {
            $total += $series[$i]['e'] ?? 0.0;
        }

        return $total;
    }
}
