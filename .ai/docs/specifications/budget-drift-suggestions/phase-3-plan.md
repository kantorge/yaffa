# Phase 3 + 3t plan

Branch `feat/budget-suggestions-phase-3` from `feat/budget-suggestions` (2b merged, `c7a71727`). Phase 3t (translations) goes on the same branch as a separate commit, since both phases touch the same strings. Role files: `frontend`, `testing`.

## Files

**New**
- `resources/js/reports/components/BudgetSuggestions.vue`: run controls, both run buttons, recommendation list, collapsed verdict list. Options API, self-contained island mounted in the existing `#budgetChartFormApp` area. Loading, error and empty states for each run. *(Changed 2026-11-13: each item also shows the monthly impact and its share of monthly expenses; the list keeps the server order.)*
- `resources/js/reports/budgetSuggestions.js` (pure helpers, with a `node:test` file next to it): last-complete-month default, grouping units by verdict, building the `BudgetForm` prefill from a suggestion and the picked candidate.

**Changed**
- `resources/views/reports/budgetchart.blade.php`: card above the chart for the component.
- `resources/js/reports/budgetchart.js`:
  - Mount the component. It emits `select-category` (tick the category in jstree, `reloadData()`, then draw the series) and `open-budget-form`.
  - New dashed "Recommended budget" `LineSeries`, fed from the response's `series` (monthly). `updateChart()` sums it per quarter/year like `budget`, and skips it when no run is loaded.
  - **Moving-average fix:** `computeMovingAverage` ignores the open period and uses exactly 12 months, 4 quarters or 5 years of complete periods (the window the detector uses). Future periods carry the last value, as now.
- `BudgetForm.vue`: `show()` gets an optional prefill (category, account, amount, start date, frequency/interval), so a suggestion opens the existing replace modal (`budget_id` set) or the new modal (`create`). No change to its save requests.
- `resources/views/categories/form.blade.php`: checkbox "Calculate budget suggestions" (on by default). When the parent's switch is off, the child shows "excluded via parent" and the checkbox is disabled.
- `lang/*.json`, `lang/*/` as needed (3t): every new string in `en`, `fr`, `hu`, `pl`.

## Behaviour

- Run controls: data-through month (default last complete month), sensitivity, bias; last choice kept in `localStorage`.
- "Check selected categories" sends `category_ids[]`, "Review all categories" sends `all=1`. Request goes to `api.v1.reports.budget-suggestions`.
- Each recommendation shows category, current vs suggested amount, effective date, L, E, "k of n", data-through. A candidate picker appears when `candidates.length > 1`. Actions:
  - Create new budget period: opens the prefilled form. After save, the list drops the item and the chart reloads.
  - Hide: removes it from the list in memory only.
  - Stop monitoring: sets `calculate_budget_suggestions` to false (new endpoint, see Backend), then removes the item.
- Units without a suggestion: collapsed `<details>` grouped by verdict.
- Clicking a recommendation selects its category in the tree and reloads the chart with the recommended series (the series comes from a `category_ids[]` run, so clicking in a "Review all" result triggers a second, single-category run).

## Tests

- JS unit tests for the helpers (`vendor/bin/sail npm run test:js`), including the corrected moving average (extract the function so it can be tested; it is pure).
- Pest browser test in `tests/PEST/` for one flow only: select a category, run a check, see a recommendation, open the prefilled modal. Only if the dev data or a seeded fixture makes it deterministic; I will say so in the handoff if skipped.
- Feature test for the category form toggle (submit with and without the checkbox, saved value) once Q1 is decided.
- Verification: `vendor/bin/sail npm run dev`, ESLint on `resources/js`, `check-translations.php --base=feat/budget-suggestions`. Owner UI check follows (workflow checkpoint after 3).

## Backend (owner-approved, small)

- Column renamed to `calculate_budget_suggestions` (boolean, default true); 2a migration edited in place and the dev DB migrated again. Model, service, tests and docs are already updated.
- `CategoryRequest`: rule `boolean` for `calculate_budget_suggestions`, and `prepareForValidation` defaults a missing value to `0`, as for `active`. Careful: the default must apply only to the web form, so I will check that the existing category PATCH/API paths do not use this request.
- New `PATCH /api/v1/categories/{category}/budget-suggestions` (`CategoryApiController::patchBudgetSuggestions`, `abilities:write`, `Authorize('update')` like its siblings), validating `calculate_budget_suggestions` as a required boolean. `patchActive` stays untouched. One row in `ApiAbilityEnforcementTest` and one feature test.

## Assumptions (shout if wrong)

- Quarter/year chart aggregation for the recommended series sums the monthly values, like `budget`.
- "Hide" is not remembered across page loads (list only, per the spec).
- 3t also translates the 2b API validation message ("Provide either category_ids or all=1."), wrapped in `__()`.

Out of scope: threshold tuning (the owner checkpoint after 2b is still open in the decisions log), the queued fallback, docs (phase D).
