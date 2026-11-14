# Architecture: Budget drift suggestions

## Components

| Piece | Location |
| --- | --- |
| Aggregation (batched per user, per category and month) | `BudgetChartService::aggregateByCategory()` (extracted from `ReportApiController::budgetChart()`, whose response is pinned by golden-file tests) |
| Detector, proposals, recommended series | `app/Services/BudgetSuggestionService.php`, `run()`. Read-only, returns plain arrays |
| Endpoint | `GET /api/v1/reports/budget-suggestions` (`api.v1.reports.budget-suggestions`), `ReportApiController::budgetSuggestions()`, validated by `App\Http\Requests\API\BudgetSuggestionsRequest` |
| Review command | `budget-suggestions:review {user} {--through=} {--sensitivity=} {--bias=} {--json} {--backtest}` (`ReviewBudgetSuggestions`) |
| Opt-out switch | `categories.calculate_budget_suggestions` (boolean, default true), `PATCH /api/v1/categories/{category}/budget-suggestions` (`CategoryApiController::patchBudgetSuggestions`), `CategoryRequest` field, category form checkbox |
| Report panel | `resources/js/reports/components/BudgetSuggestions.vue` (+ `budgetSuggestions.js` helpers), mounted by `budgetchart.js` in `#budgetSuggestionsApp`; `BudgetForm.vue` `show(budgetId, prefill)` |
| Moving average | `resources/js/reports/movingAverage.js` |
| Future MCP wrapper | `.ai/docs/specifications/budget-drift-suggestions/mcp-integration.md` (not built) |

Endpoint, command and (later) MCP tool all call `run()`; `--json` output equals the endpoint body apart from `computed_at`.

## Request

`category_ids[]` (user-owned, non-empty) **xor** `all=1`; optional `data_through` (`Y-m`), `sensitivity` (`low|medium|high`), `bias` (`neutral|conservative|conservative_rounded`). Otherwise 422. A user without a base currency also gets 422. `--through` cannot be combined with `--backtest`.

## Detector (per analysis unit)

- **Scope:** selected categories, or all active ones for "Review all". Excluded = own switch or any ancestor's switch off. Inactive roots are skipped.
- **Units:** bottom-up. A child is its own unit if it has Budget rows, schedule items, or is regular (see below); otherwise it rolls into its parent. Empty units are skipped in "Review all".
- **Series:** per month, actual (null if no transactions) and expected E = budget + schedule forecast, base currency, summed over the unit's categories. Account filters do not apply.
- **Granularity G:** the coarsest of the category's `default_aggregation` and the longest cadence among its rows and schedules (month/quarter/year, cadences over 12 months give `insufficient_data`). A unit with no rows or schedules uses the finest *regular* cadence: at least k of the last n periods have a transaction (`regularity`); history under 12 months gives `insufficient_data`, else `irregular`.
- **Level L:** mean of the actuals over the last `level_window` complete periods up to the data-through month.
- **Expected E:** mean expected amount over the horizon, whole periods from the next period start, cut at the user's `end_date`.
- **Checks in order, each with its verdict:** history (`insufficient_data`), missing rates (`missing_rates`), cooldown (`cooldown`: a contributing Budget row started within the cooldown window or starts in the future), horizon, materiality (`no_drift` unless |L − E| > tolerance × |E|; optional floor), persistence (`no_drift` unless the sign of actual − E matches the gap in k of the last n periods).
- **Bias:** `neutral` target = L. `conservative` shifts the target by `margin` against the user on unfavorable gaps and uses an asymmetric tolerance (`unfavorable_factor`/`favorable_factor`); `conservative_rounded` additionally rounds the amount toward the unfavorable side. Neutral units (mixed-sign categories netting under `neutral_net_share`) ignore the bias.
- **Verdicts:** `suggestion`, `no_drift`, `cooldown`, `insufficient_data`, `irregular`, `missing_rates`, `needs_review`, `excluded`.

## Proposals

- **replace:** the unit has Budget rows. Candidates are the rows still contributing from the effective date, ranked: account-agnostic first, most recently started, largest contribution. Each carries an amount that would absorb the whole gap on that row alone (converted with today's rate, inflation compounded from the effective date); a candidate whose amount would flip sign has `suggested_amount = null`; if the top one is null the verdict is `needs_review`.
- **create:** no usable Budget row (schedule-only unit, or no expectation). One account-agnostic row in base currency with amount = target − E per period. Next to schedules it gets `end_date` = the latest schedule end; open-ended schedule → no end date; a schedule ending inside the horizon → `needs_review`.
- **Schedules:** contributing schedules are listed (`schedules[]`); they are never changed.
- **Impact:** `monthly_impact` and `impact_share` come from the final rounded amount; `units[]` is ordered by |`monthly_impact`| descending, then verdict-only units by name.
- **`key`:** stable 16-char hash of category, proposal type, budget id, effective date and the amount bucketed on a log scale of tolerance/2. Not stored.
- **`series`:** monthly `{period, current, recommended}` from the first effective date to `end_date`; only for explicit category requests (`null` for "Review all"). Created rows are projected as unsaved `Budget`s through `RecurrenceRuleService` directly (the occurrence cache key would collide). The replace series reuses the old row's rrule.

Response contract: `run` (computed_at, scope, data_through, sensitivity, bias, tolerance, detector_version = 1, average_monthly_expenses, materiality_floor_share, base_currency, currencies_without_rates), `units[]` (category_id, category_name, granularity, verdict, suggestion), `series`. Amounts are signed JSON **numbers** rounded to 2 decimals (withdrawals negative), unlike the decimal strings of Eloquent responses. See `mcp-integration.md` for the field-level contract of `suggestion`.

## Permissions

Both endpoints are API controller actions with ability middleware: the report endpoint is `abilities:read`, the category switch is `abilities:write`. `ApiAbilityEnforcementTest` has a row for each. `category_ids` are validated as owned by the user. The service and the GET endpoint write nothing (contract test).

## Frontend behavior to know

- Sensitivity and bias are remembered in `localStorage`; the data-through month is not.
- Clicking a recommendation re-selects its category in the tree and runs a second single-category request to get the series. The recommended line shows only while the loaded chart covers exactly the checked categories, and is dropped after a budget is saved.
- The recommended series assumes the top-ranked candidate and is not refetched when another row is picked.
- The moving average is the mean of the last 12 months / 4 quarters / 5 years of complete periods (history counted from the first actual), so it equals L only when the chart is at the unit's granularity and `data_through` is the last complete month.

## Known limitations (carried from the build)

- Horizon is cut at the user's `end_date`; a short forecast end gives `insufficient_data`.
- A counted rrule in a replaced row restarts its count in the series; the replace series for an `INTERVAL > 1` row can differ from the saved row.
- Mixed-sign rows inside one category net out before the neutral check; a schedule's sign is assumed to match its unit's.
- Units whose root is inactive are skipped but their children still get their own units.
- Average monthly expenses is the mean over 12 months of negative category-month actuals (refunds net against withdrawals), so it is approximate.
- `--backtest` counts suggestions per category per year (more than 2 flagged) without deduplicating by key and without comparing to later actuals.
- The `bias` value list is hard-coded in the request, the command and the service; the config comment lists only `neutral | conservative`.
- The category form's parent note does not follow the parent dropdown live.
- A queued/background mode was deferred: the full "Review all" run was within the 5 s synchronous target on the dev dataset (cold-cache margin small); re-measure on a larger dataset.
