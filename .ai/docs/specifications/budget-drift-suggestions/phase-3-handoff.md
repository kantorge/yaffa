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

## Known issues and open questions
- **Translations (fr, hu, pl) are machine drafts: a native speaker must review them before release.**
- No UI check done yet in a browser: the owner's checkpoint (`vendor/bin/sail npm run dev`, then the report page, "Review all", the recommended series, the category form) still applies. A Vite dev server I started with that command is running in the background.
- Threshold tuning (checkpoint after 2b) is still open, so a first run will show many repeated recommendations.
- Clicking a recommendation re-selects the category in the tree (unchecking the others), and the recommended line is hidden whenever the chart's categories differ from the checked run.
