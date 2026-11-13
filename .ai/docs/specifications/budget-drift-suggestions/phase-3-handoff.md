# Phase 3 and 3t handoff

## Built
- `resources/js/reports/components/BudgetSuggestions.vue`: the "Budget check" card (data-through month, sensitivity, estimate bias, "Check selected categories", "Review all categories"), recommendation list (candidate picker, Create new budget period, Hide, Stop monitoring this category) and the collapsed verdict list. Mounted by `budgetchart.js` in `#budgetSuggestionsApp` (blade, above the chart).
- `resources/js/reports/budgetSuggestions.js` (pure helpers) and `movingAverage.js` (the corrected moving average, extracted from `budgetchart.js`), both with `node:test` files.
- `budgetchart.js`: dashed orange "Recommended budget" series (month/quarter/year), moving-average import, panel wiring.
- `BudgetForm.vue`: `show(budgetId, prefill)`; the replace and new modals open prefilled (amount as a positive number plus type, start date, and for `create` the category and cadence).
- Category switch: `categories/form.blade.php` checkbox "Calculate budget suggestions" (disabled with "Excluded via parent category" when the parent's switch is off), `CategoryRequest` rule, `PATCH /api/v1/categories/{category}/budget-suggestions` (`CategoryApiController::patchBudgetSuggestions`, `abilities:write`).
- 3t: 43 new keys in `en`, `fr`, `hu`, `pl` (JSON), plus the 2b validation message now wrapped in `__()`.

## Interfaces for the next phase (D)
- Route `api.v1.categories.patch-budget-suggestions`, body `{calculate_budget_suggestions: bool}`, returns the category.
- Column `categories.calculate_budget_suggestions` (renamed from `budget_suggestions_excluded`; see the decisions log).

## Deviations from the spec
All in the decisions log (rows dated 2026-11-13, phase 3). Also: no "chart-level" `L` series (as specified); the panel shows `L` and `E` in the list. The "differs from default aggregation" note compares the suggested cadence with the category's `default_aggregation` read from the category tree.

## Measurements
- None for this phase (UI only). A production build compiles (`vite build`, 4 s).

## Tests
- JS: `vendor/bin/sail npm run test:js` (51 pass; new: `movingAverage.test.js`, `budgetSuggestions.test.js`).
- PHP: `vendor/bin/sail artisan test --compact --filter='CategoryBudgetSuggestionsSwitch|ApiAbilityEnforcement|BudgetSuggestion|Category'` 348 pass (new: `tests/Feature/CategoryBudgetSuggestionsSwitchTest.php`, one `ApiAbilityEnforcementTest` row).
- **Not run:** `tests/PEST/Pages/Reports/BudgetSuggestionsTest.php` (smoke test of "Review all"). Pest browser tests, including the existing `BudgetModalTest`, fail here with `PlaywrightOutdatedException`; fixing it needs `npm install playwright@latest` (a dependency change I did not make).
- ESLint: 0 errors (4 old warnings). Pint, PHPStan clean. `check-translations.php --base=feat/budget-suggestions`: 0 errors, 0 warnings. The full suite was not run.

## Review (independent agent) and fixes
Fixed: candidate picker amounts/currency/precision, recommendation not dropped after a save outside the tree selection, `applying` matched by category so a cancelled form cannot hide another item, series ids now equal the chart's ids (parent plus children), monthly series keyed by period text (timezone), dirty baseline of the prefilled new form, moving average counts history from the first actual; extra tests (API create keeps the default, disabled-parent form, `applyRecommended`, moving average).
Not fixed, logged: moving-average vs L gaps (data-through, view granularity) and the series ignoring the picked candidate (decisions log); the category form's parent note does not follow the parent dropdown live (cosmetic; the service excludes children correctly); the replace series anchors on the old row's rrule, so an `INTERVAL>1` or counted row can differ from the saved row (2a decision); the 3t translations are in the same commit as the phase 3 code, not a separate one.

## Known issues and open questions
- **Translations (fr, hu, pl) are machine drafts: a native speaker must review them before release.**
- No UI check done yet in a browser: the owner's checkpoint (`vendor/bin/sail npm run dev`, then the report page, "Review all", the recommended series, the category form) still applies. A Vite dev server I started with that command is running in the background.
- Threshold tuning (checkpoint after 2b) is still open, so a first run will show many repeated recommendations.
- Clicking a recommendation re-selects the category in the tree (unchecking the others), and the recommended line is hidden whenever the chart's categories differ from the checked run.

## Cross-phase change: materiality floor (2026-11-13, separate commit on this branch)
Owner change request: the 0.5% materiality floor hid real, persistent changes in small categories in a user-initiated review, so it is off by default and the list is ranked by monthly impact instead. It touches code from phases 2a and 2b.

- **2a, `BudgetSuggestionService`:** materiality is now |L − E| > tolerance × |E| only. The floor check (with the monthly conversion of quarterly and yearly gaps) runs only when `config('yaffa.budget_suggestions.materiality_floor_share')` is above 0; the config default is `0.0`. `create` suggestions no longer need to clear the floor: they go through the same check. The only verdict the floor ever produced was `no_drift` (in the materiality step of `evaluate()`; `create` units went through the same step), so with the floor at 0 no unit gets a non-`suggestion` verdict because of it. Those units now get `suggestion`.
- **2a, contract:** each `suggestion` gets `monthly_impact` and `impact_share` after `target`; `run` gets `average_monthly_expenses` and `materiality_floor_share`; `units[]` is ordered by the service (suggestions by |monthly_impact| descending, ties by `category_name`, then the rest by `category_name`). `key`, the verdict list, `series`, candidate ranking and `detector_version` (1) are unchanged. No database changes.
- **2b, `budget-suggestions:review`:** the table drops its own sort, follows the service order and adds "Monthly impact" and "% of monthly expenses" columns. `--json` carries the new fields as they are. `--backtest` is unchanged, so expect higher counts for small categories; nothing compensates for that in code.
- **Phase 3, report page:** each recommendation shows the monthly impact (per month) and its share as a percent; the list keeps the server order. New strings (`Monthly impact`, `per month`, `:share of monthly expenses`) are in all four `/lang` files; fr, hu and pl are machine drafts for native-speaker review.
- **Tests:** new cases in `BudgetSuggestionServiceTest.php` (small category suggests by default; none with the floor at 0.005; small `create`; ordering with quarterly ÷ 3; impact share and its `null` case; field order and types) and a contract line in `ReportApiBudgetSuggestionsTest.php`. No existing test asserted floor suppression, so none was removed.
- **Not fixed, observed:** none.

## Cross-phase change: schedule-aware fixes (2026-11-13, separate commit on this branch)
Owner change request from the SZJA case (quarterly schedule of -400,000 to 2045, actuals about -482,000 a quarter): the detector proposed a `create` of -97,000 a quarter without an end date, so the recommended series dropped to -97,000 after the schedule ended; the schedule itself was not reachable from the panel; and the impact (-32,124 a month) came from the unrounded target while the suggested amount was rounded (-97,000, so -32,333). It touches code from phases 2a and 2b.

- **2a, `BudgetSuggestionService`:**
    - `loadExpectations()` keeps its single schedule query (now also eager loading `currency` and the payee accounts) and returns a third element: per category and schedule transaction the id, name, signed per-occurrence amount, currency, cadence and last occurrence (`scheduleEnd()`: UNTIL date, or the final occurrence for a count, via `RecurrenceRuleService`). No per-unit reloads.
    - `evaluate()` collects a unit's contributing schedules (ended before the effective date: dropped). A `create` next to schedules gets the latest end as `end_date`; none if any is open-ended; `needs_review` if that end is before the horizon's last day. `replace` is unchanged.
    - `monthly_impact` / `impact_share` now come from the final `suggested_amount`: `createProposal()` uses `round(delta, 2) ÷ m`, `replaceProposal()` uses `(suggested − current) × rate × Σ inflation multipliers ÷ horizon ÷ m` (a candidate-internal `impact_factor`, not output). The placeholders in `common` keep the documented key order. `target` stays unrounded evidence.
    - `recommendedSeries()` projects a `create` as an unsaved `Budget` (frequency, interval, end date) and expands its `effectiveRrule()` through `RecurrenceRuleService` directly, not `BudgetService::projectOccurrences()`, whose cache key (`budget-occurrences:{id}:{updated_at}:…`) would collide for unsaved rows (null id and timestamp).
    - New `suggestion` fields `end_date` and `schedules[]` (after `candidates`).
- **2b:** `ReviewBudgetSuggestions` and `BudgetSuggestionsRequest` need nothing for this; `--json` and the endpoint pass the new fields through. The `mcp-integration.md` output notes are updated.
- **Phase 3, report page:** `BudgetSuggestions.vue` shows the "Or adjust the schedule instead" block (name, amount, cadence, end, link to `transaction.open`/`replace` in a new tab, re-run note); `buildFormPrefill()` and `BudgetForm.applyPrefill()` carry `end_date` for a `create`. New strings (8) in all four `/lang` files; fr, hu and pl are machine drafts for native-speaker review.
- **Tests:** nine new cases in `BudgetSuggestionServiceTest.php` (SZJA incl. series, count, open-ended, inside horizon, ended schedule, no expectation, split schedule, replace lists schedules, impact invariant with three biases) and a JS test for the end-date prefill. The `scheduleFor()` helper takes recurrence overrides and a second item. No existing test needed changes.
- **Measurement ("Review all"):** dev data, user with 128 categories, 99 units, 63 suggestions: 21 queries, 1.9–2.1 s warm. Under the 5 s target.
- **Not fixed, observed:**
    - for a unit with a Budget row and a schedule, the whole gap goes to the Budget row (`replace`); the schedule is only listed
    - a unit's schedules are assumed to share the unit's sign; a mixed-sign schedule just lists its signed amount
    - `check-translations.php` warns that `from :date` and `New budget, suggested cadence: :cadence` are unused in code (not from this change)

