# Budget drift suggestions — specification (MVP)

This file is the source of truth for the build. Agents record any deviation in the "Decisions log" at the end, with the reason, instead of silently diverging. The build process is described in `.ai/docs/specifications/budget-drift-suggestions/build-workflow.md`.

## Context and goal

YAFFA gets an on-demand budget self-check. The user runs it from the budget report, either for the selected categories or for all non-excluded categories. They review a list of recommendations and apply the ones they agree with through the existing budget forms. The example: budget about -24k from Jan 2027 while recent actuals sit near -27.5k.

A regular, user-initiated review fits YAFFA's mindful-finance approach: the user judges whether their data is up to date and decides every change.

- **Goal:** make the forecast accurate from now on.
- **Non-goal:** retro-fitting past periods so history looks well forecast.
- **Non-goal:** never changes scheduled transactions itself. A schedule whose amount looks out of date is pointed out, and the user changes it through the existing replace flow.
- **Non-goal for the MVP:** background monitoring and notifications (see "Future extension").
- **Constraint:** no suggestions from one-off deviations, from too little data, or right after a budget change.

The feature is stateless for MVP. It has no scheduled run, no stored suggestions and a single read-only endpoint, and its only database change is one column on `categories`. Reviewed against `develop` at `37f1d7a`.

## Where it lives

This is a native YAFFA feature, branched from `develop`, with no dependency on the work-in-progress `feat/mcp_server`.

- Detection is deterministic statistics over app data, so it belongs in a service: `BudgetSuggestionService`, returning plain arrays.
- **One read-only REST endpoint** exposes the service. Every caller uses the same contract: the report page, an artisan command, and later an MCP read tool.
- The MCP branch is unmerged and has no budget tools, so the MCP tool is a follow-up. `.ai/docs/specifications/budget-drift-suggestions/mcp-integration.md` documents the future `GetBudgetSuggestions` read tool, an optional `ReplaceBudget` write tool, and the output schema.

## Detector design

The detector reviews the categories in the run's scope. It uses only periods up to the user's data-through month, and compares the recent level (the chart's moving average) with the forward expectation (Budget rows plus schedule forecast).

### Scope: which categories

- **Selected:** the categories checked in the budget report's category tree.
- **Review all:** every active category that is not excluded. Inactive categories are always skipped.
- **Opting out:** the "Calculate budget suggestions" switch (on by default) is turned off from the category form, or with "Stop monitoring this category" on a recommendation. With no stored dismissals, it is the only way to silence a recommendation that keeps coming back.
- **Excluding a parent excludes its children.** A category counts as excluded if its own switch or its parent's switch is off. The child's toggle shows "excluded via parent" and is disabled.
- **Excluding a child alone** only stops the child being analysed as its own unit, so it gets no suggestion of its own. If the child has no Budget rows or schedules, its actuals still count toward the parent's unit, because the parent's budget covers it (remainder rule, option A). Removing them would make the parent look underspent.

### Analysis unit

- Units form bottom-up. A child category is its own unit if it has its own Budget rows or schedule items, or passes the regularity test on its own transactions (see "New budget" below).
- Other children roll into the parent's unit, together with the parent's direct transactions. A unit never spans unrelated categories.
- Data comes from `BudgetChartService` with scope `any`. Account filters do not apply.

### Expected value: budget plus forecast

Expected for a period = `budget` + `forecast` from the chart data. Per `budget-schedule-redesign/background.md`, schedules always count toward the category comparison and are summed with Budget rows, never reconciled. Actuals include recorded schedule instances, so comparing against Budget rows alone would report a false drift equal to the scheduled amount.

### Effective granularity

G = the coarsest of: the category's `default_aggregation`, and the longest cadence among contributing Budget rows and schedules (capped at year). A yearly Budget row in a monthly category gives a series of 11 zeros and one spike, which per-period comparison mishandles. Cadences longer than a year give the unit the insufficient_data verdict in the MVP.

### Data-through month

The user decides how far their data is complete. Entry can lag days to months, and an incomplete month looks like lower spending. Only the user knows which months are complete, so the system does not guess.

- **Run parameter `data_through`:** a month, defaulting to the last complete calendar month. The current month is never analysed.
- Only periods ending on or before it are used. A quarter or year that is only partly covered counts as incomplete and is left out.
- The response echoes `data_through`, and the panel shows it next to every recommendation.

### Signal

1. **History check.** At least N complete periods at G. Defaults: monthly 6, quarterly 4, yearly 3. Otherwise the result is `insufficient_data`.
2. **Level L** = plain mean of the complete actuals inside the chart's moving-average window: 12 months, 4 quarters or 5 years. If less history exists, it uses what is available (at least N). This is the same window and the same mean as the corrected chart moving average, so L equals the line the user sees. Protection against one-offs comes from the persistence test, not from the level.
3. **Forward expectation E** = the average expected per period over a horizon H from the candidate effective date. Defaults: monthly 12, quarterly 4, yearly 2. Averaging absorbs inflation steps and lumpy cadences.
4. **Materiality.** |L − E| > tolerance × |E|. There is no floor by default. An optional floor (`materiality_floor_share`, a share of the user's average monthly expenses, off at 0) is kept for a future scheduled-notification mode: when above 0 the gap must also exceed it, with a quarterly or yearly gap converted to a month first. Average monthly expenses = the sum of negative category-month actuals over the last 12 complete months up to `data_through`, in base currency (a category's refunds net against its withdrawals). Transfers and investment transactions have no categories, so they never count. The suggestion list is ranked by monthly impact instead of filtered (see the response contract). With E near zero the tolerance band is near zero, so any persistent gap passes; persistence still applies.
5. **Persistence.** The sign of (actual − E) matches the gap's sign in at least k of the last n complete periods (see the sensitivity table).
6. **Already fixed?** No separate check is needed. E looks forward, so a budget already replaced from next month removes the gap.

Past gaps between actuals and the past budget never trigger a suggestion. Past budget lines are unreliable anyway: a replaced Budget row turns inactive and drops out of the chart query.

### Stability

- **Cooldown.** No suggestion while any contributing Budget row has a `start_date` within the last M periods or in the future. Defaults: monthly 3, quarterly 2, yearly 1. This depends only on data, so it needs no stored state.
- **Effective date** = the start of the first G period after both the cooldown and the current period. It is never in the past.

### Sensitivity and bias

These are run options. Defaults come from `config/yaffa.php`, and the panel remembers the last choice in browser storage.

| Sensitivity      | Tolerance | Persistence: monthly | Quarterly | Yearly |
| ---------------- | --------- | -------------------- | --------- | ------ |
| Low              | 20%       | 4 of 4               | 2 of 3    | 2 of 2 |
| Medium (default) | 10%       | 3 of 4               | 2 of 3    | 2 of 2 |
| High             | 5%        | 2 of 3               | 2 of 3    | 2 of 2 |

In the MVP, quarterly and yearly persistence is the same at every sensitivity level, so sensitivity changes only their tolerance.

- **Direction** comes from the sign of E: negative behaves like an expense, positive like income. If contributing elements mix signs and the net is under 25% of the gross, the unit is neutral and gets no bias.
- **Conservative bias** (overestimate costs, underestimate income): an unfavorable gap uses 0.75 × tolerance and a favorable one 1.5 × tolerance. The suggested target sits 3% beyond L on the unfavorable side.

### Verdicts per unit

Every unit in scope gets exactly one verdict:

| Verdict             | Meaning                                                                               |
| ------------------- | ------------------------------------------------------------------------------------- |
| `suggestion`        | Drift detected, or a new budget is suggested                                          |
| `no_drift`          | Expectation matches recent actuals                                                    |
| `cooldown`          | A contributing budget changed recently                                                |
| `insufficient_data` | Fewer than N complete periods, or a cadence longer than a year                        |
| `irregular`         | No expectation and the regularity test fails                                          |
| `missing_rates`     | Currency rates missing for periods in the window (`budgetChart` already detects this) |
| `needs_review`      | The suggested amount would flip sign                                                  |
| `excluded`          | Opted out; only shown when the category is explicitly selected                        |

### Out of scope for the MVP

- **Seasonality** (same period last year). It is the main known source of false alarms for seasonal monthly categories such as heating. A category set to yearly aggregation avoids it.
- **A category-level income/expense flag.**
- **Suggesting changes to inflation rates.**

## What gets suggested

A unit with an existing expectation gets a `replace` proposal on one Budget row. A unit with no Budget row gets a `create` proposal with a suggested cadence. Only the base amount is suggested; the inflation rate is carried over unchanged.

| Type      | When                                                                                                                  | Applied as                                                                                               |
| --------- | --------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `replace` | At least one Budget row contributes and drift is detected                                                             | `BudgetService::replace`: the old row ends the day before the effective date, and a new row starts on it |
| `create`  | No Budget row contributes: schedule-only units with a gap, or units with no expectation that pass the regularity test | `BudgetService::store`: a new account-agnostic Budget row from the effective date                        |

### Replace: which Budget row

Candidates are the active Budget rows contributing at the effective date, from `budgetBreakdown`. Rank them:

1. **Account-agnostic rows first** (`account_id = null`). They are the category's target level. Account-scoped rows exist to check one account's balance, and changing them also moves that account's forecast.
2. **Most recently started.**
3. **Largest contribution over the horizon.**

The top candidate is the default. The response lists all ranked candidates with their converted amounts, and the panel lets the user pick another. Several candidates only exist when a category has several rows (for example a generic row plus an account-scoped one).

### New budget: categories without one

The suggested cadence is the finest one at which the unit is regular over complete periods:

| Cadence   | Regular when                                  |
| --------- | --------------------------------------------- |
| Monthly   | Non-zero in at least 10 of the last 12 months |
| Quarterly | Non-zero in at least 3 of the last 4 quarters |
| Yearly    | Non-zero in at least 2 of the last 3 years    |
| None      | Otherwise: "irregular", no suggestion         |

"Non-zero" means at least one transaction in the period, whatever its amount.

- **Amount** = L at that cadence plus the bias margin. Every unit that passes the regularity test and the other rules gets a suggestion, whatever its size; the list is ranked by impact.
- **Defaults:** account-agnostic, `transaction_type` from the net sign, no inflation, starting at the first period after the current one.
- **End date next to schedules.** When a `create` complements schedules (the unit's E includes schedule forecast), the new row ends with them, otherwise it would outlive the schedule and the forecast would drop to the gap amount after the schedule ends:
    - the end date is the latest end among the unit's contributing schedules; for a schedule with a count it is the date of its last occurrence
    - a schedule that ended before the effective date does not contribute
    - if any contributing schedule is open-ended, the row has no end date
    - if the latest end falls inside the horizon H from the effective date, the verdict is `needs_review`: E changes within the horizon, so one gap amount would be wrong
    - a `create` for a unit with no expectation has no end date; a `replace` keeps the old row's recurrence, including its end
- If the suggested cadence differs from the category's `default_aggregation`, the panel says so. The setting itself is not changed.
- A category that is irregular on purpose (occasional large purchases) can be opted out with "Stop monitoring this category".

### Amount conversion (replace)

1. delta per period = target − E
2. occurrences per period = the candidate's `projectOccurrences` count over H, divided by H
3. occurrence delta = delta per period ÷ occurrences per period
4. Convert to the candidate's currency (the account currency for account-scoped rows) at the latest rate.
5. New base amount = the current occurrence amount at the effective date, with inflation applied, + occurrence delta. The new row starts at the effective date, so its inflation multiplier starts at 1 there.
6. Keep `transaction_type`. If the amount would flip sign, there is no suggestion; the verdict is needs_review.

### Inflation

- E already includes existing rows' inflation, because it comes from the chart's projections.
- The suggestion changes the base amount only. A `replace` carries the old row's rate unchanged, and a `create` has none.
- Changing the rate is not suggested. Over 6–12 periods, a 3–5% yearly price trend cannot be separated from changes in consumption and noise. A periodic level reset captures real price growth anyway.

## Data model and response contract

The MVP stores no suggestions. Its only database change is one column, and every run recomputes its results from current data.

### Database

| Table        | Column                        | Type, default    | Purpose                  |
| ------------ | ----------------------------- | ---------------- | ------------------------ |
| `categories` | `calculate_budget_suggestions` | boolean, `true` | Opt-out switch for "Review all" (off = excluded) |

- No new tables, and no `users` columns. Sensitivity, bias and the detector thresholds are run options with defaults in a `budget_suggestions` block of `config/yaffa.php`.
- `budgets` is not changed. Accepting a recommendation goes through the existing budget store and replace requests, unmodified.

### Configuration defaults

The thresholds are instance-wide, live in config only (not the UI or `.env`), and are tuned during the build. Sensitivity and bias are the only user-facing controls.

```php
'budget_suggestions' => [
    'default_sensitivity' => 'medium',   // low | medium | high
    'default_bias' => 'neutral',          // neutral | conservative
    'min_history' => ['month' => 6, 'quarter' => 4, 'year' => 3],
    'level_window' => ['month' => 12, 'quarter' => 4, 'year' => 5], // = chart moving average
    'horizon' => ['month' => 12, 'quarter' => 4, 'year' => 2],
    'cooldown' => ['month' => 3, 'quarter' => 2, 'year' => 1],
    'sensitivity' => [
        'low' => ['tolerance' => 0.20, 'persistence' => ['month' => [4, 4], 'quarter' => [2, 3], 'year' => [2, 2]]],
        'medium' => ['tolerance' => 0.10, 'persistence' => ['month' => [3, 4], 'quarter' => [2, 3], 'year' => [2, 2]]],
        'high' => ['tolerance' => 0.05, 'persistence' => ['month' => [2, 3], 'quarter' => [2, 3], 'year' => [2, 2]]],
    ],
    'materiality_floor_share' => 0.0,   // 0 = off; for a future scheduled-notification mode
    'bias' => ['unfavorable_factor' => 0.75, 'favorable_factor' => 1.5, 'margin' => 0.03],
    'neutral_net_share' => 0.25,
    'regularity' => ['month' => [10, 12], 'quarter' => [3, 4], 'year' => [2, 3]], // [k, of n] periods with at least one transaction
    'max_sync_seconds' => 5,
],
```

### Response contract

The contract is designed so that stored suggestions and scheduling can be added later without changing it.

- **`run`:** `computed_at`, `scope` (selected or all), `data_through`, sensitivity, bias, tolerance, `detector_version` (stays 1: the contract is unreleased), `average_monthly_expenses` (base currency, a positive number), `materiality_floor_share` (echo of the config value), `base_currency` and `currencies_without_rates`.
- **`units[]`:** one entry per unit in scope, with `category_id`, `category_name`, `verdict`, G, and for suggestions a `suggestion` object. The service orders the list, so the endpoint, the command and the future MCP tool agree: `suggestion` units first, by |`monthly_impact`| descending with ties by `category_name`; all other units follow, by `category_name`.
- **`suggestion`:**
    - `key`: a stable fingerprint, the hash of category, proposal type, budget id, effective date and the amount rounded to tolerance/2. Computed but not stored. Identical runs give identical keys, so a later dismiss, dedupe or notification can key on it.
    - `proposal_type` (replace or create), `budget_id`, `effective_date`, `current_amount`, `suggested_amount`, `currency`
    - `end_date`: the proposed row's end date (`Y-m-d`), or `null`. Only a `create` next to schedules can have one.
    - `schedules[]`: one entry per contributing schedule (active, with an occurrence on or after the effective date), empty when none contributes: `transaction_id`, `name` (payee, else the transaction's comment, else `#id`), `amount` (signed, per occurrence, only this unit's categories' items, in the schedule's currency), `currency`, `cadence` (`frequency`, `interval`) and `end_date` (or `null`). Listed for `replace` and `create` alike; the schedules are never changed by the feature.
    - `level` (L), `expected` (E), `target`, `monthly_impact`, `impact_share`, `direction`, `persistence` (k of n), in that order
    - `monthly_impact`: the monthly change the suggestion makes to E, in base currency, signed like other amounts (negative = more spending expected). It comes from the **final** target: after the bias margin and, for "Conservative and rounded", after rounding, so it always matches `suggested_amount`. For one occurrence per period in base currency: `monthly_impact` × months per period (1, 3 or 12) = `suggested_amount` − `current_amount`, with `current_amount` counted as 0 for a `create`. `level`, `expected` and `target` are unchanged evidence (`target` is not rounded). The service's ordering uses this same value.
    - `impact_share`: |`monthly_impact`| ÷ `run.average_monthly_expenses`, a fraction (4 decimals), or `null` when average monthly expenses are 0
    - `candidates[]`: ranked, each with its converted amount
    - `cadence`: for create
    - `evidence[]`: per period, the actual and the expected
- **`series`:** the monthly recommended-budget series. Returned only when the request names categories explicitly. It is not returned for "Review all".

### Example response

This is a run on 3 Dec 2026 for four selected categories, with data through November and medium sensitivity. The first unit is the screenshot case. `evidence` and `series` are shortened.

- Amounts are JSON numbers, as in `budgetChart`'s breakdowns. They are signed: withdrawals are negative.
- Unit-level amounts (`level`, `expected`, `target`, `series`) are per period G, in base currency. `monthly_impact` is per month, whatever G is: Groceries −3,500 a month; Home > Maintenance −45,000 a quarter = −15,000 a month, so Maintenance is listed first. `impact_share` is against average monthly expenses of 600,000 HUF.
- Suggestion and candidate amounts are per occurrence, in the row's currency.
- `cadence` uses the Budget model's own fields (`frequency`: DAILY, WEEKLY, MONTHLY or YEARLY, plus `interval`). Quarterly is MONTHLY with interval 3.
- `suggestion` is null for every verdict except `suggestion`.

```json
{
    "run": {
        "computed_at": "2026-12-03T19:12:44+01:00",
        "scope": "selected",
        "data_through": "2026-11",
        "sensitivity": "medium",
        "bias": "neutral",
        "tolerance": 0.1,
        "detector_version": 1,
        "average_monthly_expenses": 600000,
        "materiality_floor_share": 0,
        "base_currency": { "id": 1, "iso_code": "HUF" }
    },
    "units": [
        {
            "category_id": 57,
            "category_name": "Home > Maintenance",
            "granularity": "quarter",
            "verdict": "suggestion",
            "suggestion": {
                "key": "41d8e07a9c5b2e66",
                "proposal_type": "create",
                "budget_id": null,
                "effective_date": "2027-01-01",
                "current_amount": null,
                "suggested_amount": -45000,
                "currency": { "id": 1, "iso_code": "HUF" },
                "cadence": { "frequency": "MONTHLY", "interval": 3 },
                "level": -45000,
                "expected": 0,
                "target": -45000,
                "monthly_impact": -15000,
                "impact_share": 0.025,
                "direction": "expense",
                "persistence": { "k": 3, "n": 3 },
                "candidates": [],
                "end_date": null,
                "schedules": [],
                "evidence": [
                    { "period": "2026-07-01", "actual": -38000, "expected": 0 },
                    { "period": "2026-10-01", "actual": -51000, "expected": 0 }
                ]
            }
        },
        {
            "category_id": 42,
            "category_name": "Food > Groceries",
            "granularity": "month",
            "verdict": "suggestion",
            "suggestion": {
                "key": "9b2f6c0e4d1a7f3b",
                "proposal_type": "replace",
                "budget_id": 117,
                "effective_date": "2027-01-01",
                "current_amount": -24000,
                "suggested_amount": -27500,
                "currency": { "id": 1, "iso_code": "HUF" },
                "cadence": { "frequency": "MONTHLY", "interval": 1 },
                "level": -27500,
                "expected": -24000,
                "target": -27500,
                "monthly_impact": -3500,
                "impact_share": 0.0058,
                "direction": "expense",
                "persistence": { "k": 4, "n": 4 },
                "candidates": [
                    {
                        "budget_id": 117,
                        "account_id": null,
                        "account_name": null,
                        "cadence": { "frequency": "MONTHLY", "interval": 1 },
                        "current_amount": -24000,
                        "suggested_amount": -27500,
                        "currency": { "id": 1, "iso_code": "HUF" }
                    }
                ],
                "end_date": null,
                "schedules": [],
                "evidence": [
                    {
                        "period": "2026-10-01",
                        "actual": -28100,
                        "expected": -24000
                    },
                    {
                        "period": "2026-11-01",
                        "actual": -27300,
                        "expected": -24000
                    }
                ]
            }
        },
        {
            "category_id": 71,
            "category_name": "Hobbies > Photography",
            "granularity": "month",
            "verdict": "irregular",
            "suggestion": null
        },
        {
            "category_id": 63,
            "category_name": "Transport > Fuel",
            "granularity": "month",
            "verdict": "no_drift",
            "suggestion": null
        }
    ],
    "series": [
        { "period": "2027-01-01", "current": -112400, "recommended": -160900 },
        { "period": "2027-02-01", "current": -112400, "recommended": -115900 }
    ]
}
```

`series.current` is the existing expectation (budget plus forecast) for the selected categories. `series.recommended` is the same with the open proposals applied. February differs less than January because the quarterly `create` lands only in January, April, July and October.

The service builds this structure with no HTTP concerns. The endpoint, the artisan command and the future MCP tool only serialize it.

## API, review flow and report page

One read-only endpoint serves both flows. "Review all" is a button on the budget report, plus an artisan command for tuning.

### Endpoint

| Method and path                          | Ability | Purpose                                                             |
| ---------------------------------------- | ------- | ------------------------------------------------------------------- |
| `GET /api/v1/reports/budget-suggestions` | read    | Runs the detector and returns the response contract. Writes nothing |

Query parameters: `category_ids[]` or `all=1`, plus optional `data_through`, `sensitivity` and `bias`.

- **Access control:** per-controller `abilities:read` middleware, and a new row in the `ApiAbilityEnforcementTest` data provider (per CLAUDE.md).
- **Applying a recommendation** uses the existing budget store and replace endpoints, with no new parameter.
- **Stop monitoring** sets `calculate_budget_suggestions` to false through a dedicated category endpoint (`PATCH /api/v1/categories/{category}/budget-suggestions`), because `patchActive` is reserved for the active flag.

### "Review all"

- **Report page button:** "Review all categories". It runs with `all=1` and the chosen data-through month, sensitivity and bias, then shows the recommendation list. Clicking a recommendation selects that category in the tree and reloads the chart with its recommended series.
- **Artisan command:** `budget-suggestions:review {user} {--through=} {--sensitivity=} {--bias=} {--json} {--backtest}`. It prints the same list as a table, or as JSON. It is a thin wrapper over the service, meant for tuning thresholds against real data in phase 2 and for self-hosters who prefer the CLI. The backtest option re-runs the detector with data through set to each month of the last 24 and prints suggestions per category per year.
- **Runtime target:** "Review all" for one user finishes synchronously in under 5 seconds. Phase 1's batched aggregation is what makes this possible: one grouped query for actuals per category, month and currency, schedule instances projected once, and Budget projections that are already cached. Reusing `budgetChart()` per unit would likely take tens of seconds for about 100 categories (unmeasured estimate).
- **Fallback, only if phase 1 measurements miss the target:** a queued job whose result is held in the cache under a run id (TTL 24 h), with the page polling for it. No database table.

### Report page

- **Run controls:** a data-through month picker (default: last complete calendar month), sensitivity and bias. The last choice is remembered in browser storage.
- **Two run buttons:** "Check selected categories" and "Review all categories".
- **Recommendation list.** Each item shows the category, current vs suggested amount, effective date, the monthly impact (currency per month) and its share of monthly expenses (percent), L, E, "k of n periods" and data through. The list keeps the server order (largest impact first); it is not re-sorted client-side. It has a target-row picker for alternative candidates and three actions:
    - **Create new budget period** opens the existing `BudgetForm` replace modal, or the new-budget modal for `create`, prefilled with the effective date and amount.
    - **Hide** removes it from this list only.
    - **Stop monitoring this category.**
- **"Or adjust the schedule instead" block.** For each item with a non-empty `schedules[]` the item lists every schedule with its name, current amount (`money`) and cadence, plus a link to the existing schedule replace page (`route('transaction.open', {transaction, action: 'replace'})`), opening in a new tab with no prefill. A short note says the check must be run again after changing a schedule. The `create`/`replace` button stays the default proposal; this is an alternative path.
- **End-date prefill.** The "Create new budget period" modal for a `create` is prefilled with `end_date` as well when the suggestion has one.
- **Collapsed list of units without a recommendation,** grouped by verdict: no drift, cooldown, insufficient data, irregular, missing rates, needs review.
- **Chart:** a new dashed "Recommended budget" series. From each effective date onward it shows the expected amount (budget plus forecast) with the selected categories' proposals applied. It is computed server-side by projecting each proposed row as an unsaved `Budget` (its RRULE, end date included, is composed by the model and expanded through `RecurrenceRuleService`; `projectOccurrences` is not used for it because its cache key needs an id), so cadence, end date and inflation are exact. It is returned monthly so the chart's existing quarter/year aggregation applies. L appears in the tooltip and the list, not as its own series.
- **Separate small fix:** `computeMovingAverage` includes the current, unfinished period and uses a 13-period inclusive window. Exclude the open period and use exactly 12 months, 4 quarters or 5 years, so the line equals the detector's level L.

## Future extension: scheduled monitoring

Scheduling is additive. The detector, the endpoint and the response contract stay as they are, and a scheduled job becomes one more caller of the service. It would add:

- **A `budget_suggestions` table** keyed on the response's `key`. It holds the status lifecycle (open, dismissed, accepted, superseded, resolved), snooze, notification dedupe and an evidence snapshot.
- **A check for outdated stored suggestions,** comparing contributing budgets, schedules and an actuals checksum since the run. Alternatively, re-run on view.
- **`users` settings:** sensitivity, bias, auto-run and a data-lag in months, replacing the run options' config defaults.
- **A scheduled command** (weekly), a per-user job, and a database notification following `AiDocumentStatusNotification`.
- **An optional lag warning** for accounts with no recent user-entered transactions. Open question: does `RecordScheduledTransactions` write a `TransactionOrigin` row with the created relation?
- **Alternative:** an external agent calls the MCP read tool on a schedule. Monitoring then needs no in-app state, but avoiding repeat alerts becomes the agent's job.

### Future extension: schedule proposals

- **Level 1: `adjust_schedule` as a proposal type.** Schedules become candidates next to Budget rows; when there are no Budget rows the schedule is the default; per-occurrence item conversion; the recommended series projects the changed schedule; the replace form opens prefilled.
- **Level 2: split the gap by matching actuals to schedules** by payee, account and category, since recorded instances do not link back to their schedule.

## Phases, files and verification

The MVP has three phases. The MCP tool and scheduled monitoring come later.

### Phases

1. **Extract `BudgetChartService`** from `ReportApiController::budgetChart()`. It takes `(User, categoryIds, scope)` with no `Request`, and the controller's response stays byte-identical. Add the batched per-user aggregation and measure "Review all" on your own data against the 5-second target.
2. **Detector and endpoint:**
    - `BudgetSuggestionService` covering drift, new budgets, verdicts and the recommended series
    - the `budget_suggestions` config block
    - the `categories.calculate_budget_suggestions` migration
    - the GET endpoint, with its ability test
    - the artisan review command
    - Pest unit tests, with the screenshot scenario as a fixture
    - `mcp-integration.md`
    - tuning thresholds with the command against real data
3. **Report page:**
    - run controls and both run buttons
    - recommendation list with Hide and Stop monitoring
    - recommended-budget series
    - category form toggle
    - moving-average fix
    - translations in all `/lang` files, flagged for native-speaker review

**Later:** the MCP `GetBudgetSuggestions` tool once `feat/mcp_server` merges, and scheduled monitoring as described above.

### Critical files

- `app/Http/Controllers/API/ReportApiController.php`, `routes/api.php`
- `app/Services/BudgetService.php` (`projectOccurrences`), `app/Services/CategoryService.php`
- `app/Models/Budget.php`, `app/Models/Category.php`, category form and controller
- `app/Console/Commands/` (review command), `config/yaffa.php`
- `resources/js/reports/budgetchart.js`, `BudgetForm.vue`
- `tests/Feature/API/ApiAbilityEnforcementTest.php`

### Verification

- **Detector unit tests:**
    - stable data gives `no_drift`
    - a single spike gives nothing
    - a sustained 3-period shift gives one suggestion
    - a recently replaced budget gives `cooldown`
    - a past-only gap does not trigger
    - the conservative bias is asymmetric
    - a schedule that matches actuals gives nothing
    - a yearly Budget row in a monthly category is compared at yearly granularity
    - amount conversion is correct for weekly, account-currency and inflated rows
    - L equals the corrected chart moving average for the same data
- **Scope and cutoff tests:**
    - "Review all" skips excluded and inactive categories
    - an excluded category that is explicitly selected returns `excluded`
    - excluding a parent excludes its children
    - an excluded child with no Budget rows or schedules still counts toward its parent's unit
    - periods after `data_through` are ignored
    - a partly covered quarter counts as incomplete
- **New-budget tests:** an irregular category gives `irregular`, a monthly-regular one a monthly `create`, a quarterly-regular one a quarterly `create`, and a small regular category with no budget gets a `create` under the default config.
- **Materiality and impact tests:**
    - a small category with a persistent 30% change gets a `suggestion` under the default config
    - the same data with `materiality_floor_share = 0.005` gets none, so the optional floor stays covered
    - suggestions are ordered by |`monthly_impact`| descending (a quarterly gap divided by 3), then other units by name
    - `impact_share` is computed correctly, and is `null` when average monthly expenses are 0
    - contract: the new `run` and `suggestion` fields exist with the right types, in the documented order
- **Schedule-aware tests (phase 3, level 0):**
    - quarterly schedule fixture (a schedule ending in 2045, actuals above its amount): the `create` ends with the schedule, the recommended series is 0 after it (not the gap amount), and `schedules[]` lists the schedule with MONTHLY/3
    - a count-based schedule gives the date of its last occurrence as the end date; an open-ended one gives no end date
    - a schedule ending inside the horizon gives `needs_review`; one that ended before the effective date is not listed
    - a `create` with no expectation has `end_date: null` and `schedules: []`
    - a split schedule shows only the amount of this unit's categories
    - the impact invariant holds with rounding on and off, and the ordering follows the reported (rounded) impact
    - contract: `end_date` and `schedules[]` exist with the right types
- **Contract tests:**
    - two identical runs return identical keys
    - the endpoint writes nothing (no database changes after a call)
    - the recommended series matches a saved-then-projected Budget
- **Regression:** `budgetChart` output is identical before and after the extraction.
- **Feature test:** the endpoint allows with `read` and denies without it.
- **Manual:** run "Review all" against dev data and compare with the chart. Rebuild assets with `vendor/bin/sail npm run dev` first.

### Open decisions

- **Threshold values.** The rules are decided; the numbers get tuned during the build. Method: a `--backtest` option on the review command re-runs the detector with `data_through` set to each month of the last two years. It counts suggestions per category per year (target: at most one or two) and checks them against the actuals that followed.
- **Queued fallback: deferred to the backlog.** Phase 1 measured only the aggregation step (well inside the target), so it is not built in the MVP for now. Re-measure the full "Review all" run in phase 2b, and revisit it if that or user testing shows a bottleneck on larger datasets.

Decided: parent and child budgets follow the remainder rule (option A, matching the chart); quarterly and yearly persistence is fixed across sensitivities; the level uses the moving-average window; no materiality floor in the MVP; suggestions are ranked by monthly impact; any single transaction makes a period count as non-zero.

## Decisions log

Append one line per decision or deviation made during the build: date, phase, decision, reason.

| Date       | Phase  | Decision                                                  | Reason                                                                                 |
| ---------- | ------ | --------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| 2026-11-11 | Design | Stateless MVP, no scheduled run                           | User-initiated self-check fits the mindful-finance approach; scheduling stays additive |
| 2026-11-11 | Design | Parent/child budgets use the remainder rule (option A)    | Matches how the budget chart sums budgets today                                        |
| 2026-11-11 | Design | Level L = plain mean over the chart moving-average window | Consistency with the chart line the user sees                                          |
| 2026-11-11 | 1      | Queued fallback deferred to backlog (owner confirmed)      | aggregation step measured well inside the target on the dev data; full "Review all" to be re-measured in phase 2b, revisit if it or user testing finds a bottleneck |
| 2026-11-11 | 1      | `aggregateByCategory` returns per-category series         | Sums are additive, so units can be composed later without re-querying                 |
| 2026-11-13 | 2a     | `aggregateByCategory()` also returns `missingRatePeriods` | Additive. Rates only go missing for a currency with no rates at all (`getLatestRateFromMap` falls back to the oldest rate), flagged here per category and period, like the reports' `currenciesWithoutRates` |
| 2026-11-13 | 2a     | Average monthly expenses = sum of negative category-month actuals over the last 12 complete months / 12 | `aggregateByCategory` nets withdrawals and deposits per category, so exact withdrawals are not available; the floor is coarse *(Superseded in part by the 2026-11-13 phase 3 row: the floor is optional and off by default.)* |
| 2026-11-13 | 2a     | Materiality floor is monthly: a quarterly or yearly gap is divided by the months per period before comparing | The spec defines the floor on monthly expenses and does not say how to compare it with a per-period gap *(Superseded in part by the 2026-11-13 phase 3 row: the floor is optional and off by default.)* |
| 2026-11-13 | 2a     | A `create` for a schedule-only unit (or E = 0) uses target − E per period as the amount | Budget rows add to schedules, so the new row covers only the gap |
| 2026-11-13 | 2a     | Create cadence comes from the regularity test, finer or coarser than `default_aggregation`; no regular cadence gives `insufficient_data` if under 12 months of history, else `irregular` | Spec says "finest regular cadence"; needed a rule to tell short history from irregularity |
| 2026-11-13 | 2a     | The horizon covers whole periods up to the user's `end_date`; none fits gives `insufficient_data` | The aggregation projects only up to `end_date`, so a longer horizon would understate E |
| 2026-11-13 | 2a     | In "Review all", units with no actuals, budget or forecast are skipped; selecting one explicitly returns its verdict | An empty category is not a finding and would flood the list |
| 2026-11-13 | 2a     | The neutral check sums each member category's expected series (actuals if E = 0); mixed signs inside one category net out before the check | Per-category data is all the aggregation has |
| 2026-11-13 | 2a     | The recommended series reuses the old row's rrule for a `replace` (a `COUNT` would restart at the effective date) and is `null` for "Review all" | Counted rules are rare; the series is only returned for named categories |
| 2026-11-13 | 2b     | `category_ids` must exist and belong to the user (422 otherwise); exactly one of `category_ids` or `all=1` is required | Spec names the parameters only; ownership check avoids returning data shape for foreign ids |
| 2026-11-13 | 2b     | Backtest counts every month with a `suggestion` verdict per category per calendar year, without deduplicating by `key`; more than 2 in a year is flagged | Repeats are the instability the tuning must expose |
| 2026-11-13 | 2b     | Full "Review all" run measured on the dev data: under the 5 s target, cold cache close; queued fallback stays in the backlog | Under the 5 s target on one dev user; the cold margin is small, so re-measure on a larger dataset; see phase-2b-handoff.md |
| 2026-11-13 | 2b     | The backtest only counts suggestions per category per year; it does not compare them with the actuals that followed | Spec asked for both; the comparison is left to the owner's manual check during tuning |
| 2026-11-13 | 2b     | A user without any currency gets a 422 (endpoint) or an error (command) | The service throws; found in review |
| 2026-11-13 | 3      | Column renamed `budget_suggestions_excluded` (default false) to `calculate_budget_suggestions` (default true), 2a migration edited in place | "Excluded" was not intuitive on the category form (owner); the column only existed in the dev database, which was rolled back and migrated again. The `excluded` verdict name is unchanged |
| 2026-11-13 | 3      | "Stop monitoring" uses a new `PATCH /api/v1/categories/{category}/budget-suggestions` endpoint instead of extending `patchActive` | `patchActive` has a dedicated feature (owner); `CategoryRequest` also gets the field, with a default of 0 on unchecked forms |
| 2026-11-13 | 3      | The form checkbox is paired with a hidden `0` input; `CategoryRequest` has `sometimes\|boolean` and no `prepareForValidation` default | The same request serves `POST /api/v1/categories`, where a missing flag must keep the DB default (true); a request-level default of 0 would switch it off |
| 2026-11-13 | 3      | The run controls remember sensitivity and bias in `localStorage`, not the data-through month; sensitivity and bias start at `medium`/`neutral` | A remembered month goes stale; the config defaults are not exposed to the page |
| 2026-11-13 | 3      | The moving average takes the mean over the last 12 months, 4 quarters or 5 years of complete periods, the open period carries the last value | Spec's moving-average fix; the line now equals the detector's level L at the last complete month |
| 2026-11-13 | 3      | Clicking a recommendation runs a second, single-category request for the chart series; the recommended line is shown only while the loaded chart covers exactly the checked categories, and is dropped after a budget is saved | A "Review all" run returns no series; the account filter of the chart does not apply to the series (scope `any`) |
| 2026-11-13 | 3      | A period only partly covered by the series (quarter/year views) uses budget + forecast for its other months | The series starts at the first effective date |
| 2026-11-13 | 3      | The chart's moving average counts history from the first period with an actual, but it still ends at the last complete month by today's date (L ends at `data_through`) and is computed at the chart's view granularity | Found in review: the line equals L only when `data_through` is the last complete month and the chart is shown at the unit's own granularity |
| 2026-11-13 | 3      | The recommended series always assumes the top-ranked candidate and is not refetched after the user picks another row, hides or stops an item | The service computes one series per run; refetching per pick is deferred |
| 2026-11-13 | 3      | Materiality floor off by default (config 0); suggestions ranked by monthly impact, with impact fields in the contract | In a user-initiated review the floor hid real persistent changes in small categories; ranking keeps large items first without hiding anything (owner) |
| 2026-11-13 | 3      | A create that fills a gap next to schedules ends with the latest contributing schedule; a schedule ending inside the horizon gives needs_review | Without it, the gap budget outlives the schedule and the forecast drops after the schedule ends (owner's quarterly-tax case) |
| 2026-11-13 | 3      | Suggestions list contributing schedules with a link to their replace page; the schedules non-goal is reworded | A persistent gap next to a schedule often means the schedule amount is out of date; full adjust_schedule proposals are deferred to level 1 |
| 2026-11-13 | 3      | monthly_impact and impact_share use the final rounded target | The impact must match the suggested amount |
