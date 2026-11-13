# Phase 2a plan

Branch `feat/budget-suggestions-phase-2a`, from `feat/budget-suggestions` (phase 1 merged, `6ef3cd1f`).

## Files

- **Migration** `add_budget_suggestions_excluded_to_categories_table`: boolean, default `false`, reversible. `Category`: add to `#[Fillable]` and a `boolean` cast. `CategoryRequest`/`CategoryResource` are phase 3 (UI) and left alone.
- **`config/yaffa.php`**: the `budget_suggestions` block, verbatim from the spec.
- **`app/Services/BudgetSuggestionService.php`**, `run(User, ?array $categoryIds, ?string $dataThrough, ?string $sensitivity, ?string $bias): array`, returning the spec's response contract (`run`, `units`, `series`). It writes nothing. All numbers come from config. Steps:
    1. **Scope**: selected ids or all active, non-excluded categories; excluded if own or parent flag is set; an explicitly selected excluded one gets `excluded`.
    2. **Units**: bottom-up as in the spec. A child is its own unit if it has active Budget rows, active schedule items, or passes the regularity test. Otherwise it rolls into the parent. Unit series are sums of `aggregateByCategory()` periods.
    3. **Granularity G**: the coarsest of `default_aggregation` and the longest cadence of contributing Budget rows and schedules (read from the rrule). Cadence beyond a year gives `insufficient_data`. Monthly series are folded into quarters or years; a partly covered period is dropped.
    4. **Verdict pipeline**: `missing_rates`, `insufficient_data`, `cooldown`, `irregular`/new budget, then materiality (with the 0.5% floor), persistence, bias, `needs_review`, `suggestion`.
    5. **Replace**: candidates from active `Budget` rows read directly and ranked as in the spec. Occurrences per period via `projectOccurrences`. The amount conversion follows the spec, with the latest rate to the row's currency.
    6. **Suggestion key**: hash of category, type, budget id, effective date and the amount rounded to tolerance/2.
    7. **`series`**: only when ids are explicit. Each proposal is projected as an unsaved `Budget` through `projectOccurrences`, with inflation, added to the current budget plus forecast.
- **`BudgetChartService::aggregateByCategory()`**: additive change only, a `missingRatePeriods` key (category → periods lacking a rate). The existing keys and `chart()` stay as they are, so the regression test must stay green.

## Tests (Pest, `tests/Unit` for pure maths, `tests/Feature/Services` where the database is needed)

Every item in the spec's detector, scope/cutoff and new-budget lists (the contract and endpoint tests belong to 2b). The screenshot case (−24k budget, ~−27.5k actuals) is a fixture. Run: `vendor/bin/sail artisan test --compact --filter=BudgetSuggestion`, then `--filter=BudgetChartRegression`.

## Unclear spec points (my proposed answers; please confirm or correct)

1. **"L equals the corrected chart moving average"**: the JS fix is phase 3. In 2a the test pins L to the plain mean over the window of the chart's own `actual` series. The JS side is verified in phase 3.
2. **Average monthly expenses for the floor**: `aggregateByCategory` nets withdrawals and deposits per category, so it cannot isolate withdrawals. Proposal: sum the negative category-month actuals over the last 12 complete months. It is an approximation, and it is cheap, with no extra query. The alternative is one more grouped query, which needs its own currency conversion.
3. **Per-unit `missing_rates`** (confirmed, revised): `getLatestRateFromMap()` already falls back to the oldest rate, so a rate is only null for a currency with no rates at all. The existing reports flag that per currency (`currenciesWithoutRates`). 2a flags it at the same spot per category and period (`missingRatePeriods`) and reuses `getMissingRateCurrencies()` for the currency names in `run`.
4. **Mixed-sign units**: the neutral rule (net under 25% of gross) uses the sum of absolute values of the contributing categories' expected series as the gross.
5. **Create cadence when G is coarser than the regularity cadence**: the regularity test picks the finest regular cadence, even if it is finer than `default_aggregation`. The unit is then analysed at that cadence.

## Out of scope for 2a

The endpoint, the command, `mcp-integration.md`, any UI, translations.

Phase 2b gets its own plan after 2a is merged, as the workflow requires.
