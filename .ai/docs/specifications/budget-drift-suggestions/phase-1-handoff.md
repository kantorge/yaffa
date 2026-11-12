# Phase 1 handoff

## Built
- `app/Services/BudgetChartService.php`
    - `chart()`: the former `budgetChart()` logic, moved without behaviour change.
    - `aggregateByCategory()`: batched per-user aggregation.
- `ReportApiController::budgetChart()` now delegates to `chart()`. The `BudgetService` dependency moved to the new service.
- `CurrencyTrait::allCurrencyRatesByMonth(?int $userId = null)`. The default keeps the old behaviour.
- `tests/Feature/API/BudgetChartRegressionTest.php` and `tests/Fixtures/budget_chart_*.json`.

## Interfaces for the next phase
- `chart(User $user, Collection $categories, ?string $accountSelection = null, string|int|null $accountEntity = null): array{chartData, currenciesWithoutRates}`
    - `chartData[].period` is a `Carbon`. `actual` is `?float`, `budget` and `forecast` are floats.
    - Breakdown arrays are included, as before.
- `aggregateByCategory(User $user): array{categories, missingRateCurrencyIds}`
    - `categories[categoryId][Y-m-01] = ['actual' => ?BigDecimal, 'budget' => BigDecimal, 'forecast' => BigDecimal]`, in base currency, sorted by period.
    - A unit's series is the sum of its categories' periods. `actual` stays null only if every member is null.
    - Inactive categories are included, so the caller filters scope.
    - `missingRateCurrencyIds` is the list of currencies with no rate. Phase 2a needs per-unit, per-window `missing_rates`, so it must either map these ids to the periods or extend the return value to carry them per period.
    - There is no breakdown. For replace candidates, 2a reads active `Budget` rows directly and projects them with `BudgetService::projectOccurrences`.

## Deviations from the spec
- None in behaviour. Two small additions:
    - `CurrencyTrait` takes an optional user id.
    - `aggregateByCategory()` returns per-category series rather than per-unit ones. Units are composed from categories later, because sums are additive.

## Measurements
Dev database: 1 user, 128 categories (115 with data), 43,122 transaction items, 125 Budget rows (62 active, about 31k projected occurrences), 56 active schedules (about 16k instances).

| Run | Time | Queries |
| --- | --- | --- |
| `aggregateByCategory`, cold (cache flushed) | 3.1 s | 10 |
| `aggregateByCategory`, warm | 1.2-1.4 s | 8 |
| `chart()` once per active category, warm | 14.5 s | 783 |

- `aggregateByCategory()` alone runs well inside 5 s, including a cold cache. This is **only the aggregation step**: the full "Review all" workflow (detector, endpoint, serialization) does not exist yet, so its 5 s target is not yet established and must be re-measured in phase 2b.
- Recommendation: do not build the queued fallback now, but re-check it against the phase 2b measurement.
- Remaining time is mostly PHP-side BigDecimal and projection work. The cold run's extra time comes from rebuilding the schedule and budget occurrence caches.
- Measured in the sail container, which is likely slower than production.

## Tests
- `vendor/bin/sail artisan test --compact --filter=BudgetChartRegression`: 5 pass (4 golden-file, 1 equivalence).
- `--filter='BudgetChart|ReportApi|ApiAbility|Currency'`: 272 pass. PHPStan and Pint are clean.
- To re-record the golden files deliberately: `vendor/bin/sail exec -e UPDATE_BUDGET_CHART_GOLDEN=1 yaffa.test php artisan test --filter=BudgetChartRegression`.
- The golden files were recorded from the **pre-extraction** controller (via `git stash`), then compared against the refactor.

## Known issues and open questions
- `chart()` has the same quirk as before: it mutates `$user->end_date` via `startOfDay()`. Left alone to keep byte-identical behaviour.
- The golden fixture normalizes database ids to first-seen ordinals, since auto-increment ids differ between runs.
- Queued fallback: owner decided to defer it to the backlog (recorded in the decisions log). Re-measure the full "Review all" run in phase 2b.
