# Phase 2a handoff

## Built
- `app/Services/BudgetSuggestionService.php`: `run(User, ?int[] $categoryIds, ?string $dataThrough, ?string $sensitivity, ?string $bias): array`. Read-only; it returns the spec's response contract.
- Migration `2026_11_12_000001_add_calculate_budget_suggestions_to_categories_table` (boolean, default true; the 2a build first shipped it as `budget_suggestions_excluded`, default false, before the rename). `Category` has the `boolean` cast and the field is `#[Fillable]`. `CategoryRequest`/`CategoryResource` are untouched (phase 3).
- `config/yaffa.php`: the `budget_suggestions` block, verbatim from the spec.
- `BudgetChartService::aggregateByCategory()`: additive key `missingRatePeriods` (category id => [Y-m-01 => true]). Nothing else changed; `chart()` is untouched.
- `tests/Feature/Services/BudgetSuggestionServiceTest.php`: 26 tests.

## Interfaces for the next phase
- `run()` returns `['run' => [...], 'units' => [...], 'series' => [...]|null]`.
    - `run`: `computed_at`, `scope` (`selected`|`all`), `data_through` (Y-m), `sensitivity`, `bias`, `tolerance`, `detector_version` (1), `base_currency`, plus `currencies_without_rates` (same shape as the reports' warning).
    - `units[]`: `category_id`, `category_name`, `granularity` (`month`|`quarter`|`year`, null for `excluded`), `verdict`, `suggestion` (null unless `suggestion`). The suggestion has `key`, `proposal_type`, `budget_id`, `effective_date`, `current_amount`, `suggested_amount`, `currency`, `cadence`, `level`, `expected`, `target`, `direction` (`expense`|`income`|`neutral`), `persistence`, `candidates[]`, `evidence[]`. Candidates have `budget_id`, `account_id`, `account_name`, `cadence`, `current_amount`, `suggested_amount` (null if that row would flip sign), `currency`.
    - `series`: `[{period, current, recommended}]`, monthly, from the first effective date to the user's `end_date`. `null` for "Review all", `[]` when there is no proposal.
- Passing `$categoryIds = null` is "Review all". Explicit ids match a unit if any member category is selected.
- Invalid `sensitivity`/`bias` fall back to the config defaults, so the Form Request (2b) must validate them. `data_through` is clamped to the last complete month.
- Amounts are floats rounded to 2 decimals, signed (withdrawals negative).

## Deviations from the spec
All are in the decisions log. Summary: floor from negative category-month actuals and scaled to a month (now optional and off by default, see the phase 3 handoff); `create` amount is target − E; horizon truncated to `end_date`; empty units skipped in "Review all"; `missingRatePeriods` key.

## Measurements
- Dev database (the phase 1 dataset): `run()` for all categories took 3.1 s in the sail container, with a warm cache. Verdicts: 62 no_drift, 27 suggestion, 3 cooldown, 6 irregular, 1 needs_review.
- 27 suggestions for about 100 units is too many for a first run: this is what the 2b `--backtest` tuning is for (thresholds are in config only).
- Not yet measured: a cold cache run, and the query count.

## Tests
- `vendor/bin/sail artisan test --compact --filter=BudgetSuggestionServiceTest`: 26 pass.
- `--filter='BudgetChart|Category|BudgetSuggestion'`: 172 pass. Pint and PHPStan (whole `app/`) are clean.
- Not covered yet (2b contract tests): endpoint writes nothing, the series matches a saved-then-projected Budget, the ability test.

## Known issues and open questions
- The horizon is cut at the user's `end_date`. A user with a short forecast end date gets `insufficient_data` for every unit that needs the full horizon.
- A `COUNT`-limited rrule in a replaced row restarts its count in the series.
- Mixed-sign rows inside one category net out before the neutral check.
- Units whose root is inactive are skipped, but their children still get their own units.
- A single-currency conversion assumption: candidate conversion uses the latest rate (today), as the spec says.
