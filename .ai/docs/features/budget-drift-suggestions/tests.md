# Tests: Budget drift suggestions

| Area | File |
| --- | --- |
| `budgetChart` unchanged by the extraction (golden files in `tests/Fixtures/budget_chart_*.json`) | `tests/Feature/API/BudgetChartRegressionTest.php` |
| Detector, scope, cutoff, new budgets, conversion, schedules, series | `tests/Feature/Services/BudgetSuggestionServiceTest.php` |
| Endpoint contract, validation, writes nothing | `tests/Feature/API/ReportApiBudgetSuggestionsTest.php` |
| Command (`--json`, `--backtest`) | `tests/Feature/Console/ReviewBudgetSuggestionsCommandTest.php` |
| Category switch | `tests/Feature/CategoryBudgetSuggestionsSwitchTest.php` |
| Ability enforcement | `tests/Feature/API/ApiAbilityEnforcementTest.php` (rows `reports.budget-suggestions`, category switch) |
| JS helpers | `resources/js/reports/budgetSuggestions.test.js`, `movingAverage.test.js` (`vendor/bin/sail npm run test:js`) |
| Browser smoke of "Review all" | `tests/PEST/Pages/Reports/BudgetSuggestionsTest.php` (passes; needs `playwright install chromium` in the sail container, see below) |

Run: `vendor/bin/sail artisan test --compact --filter='BudgetChartRegression|BudgetSuggestion|ReviewBudgetSuggestions|CategoryBudgetSuggestionsSwitch|ApiAbilityEnforcement'`.

Re-record golden files deliberately: `vendor/bin/sail exec -e UPDATE_BUDGET_CHART_GOLDEN=1 yaffa.test php artisan test --filter=BudgetChartRegression`.

Not verified in a browser by an agent: the report panel, the category form, the recommended series (owner checkpoint).

Browser tests: if they fail with `PlaywrightOutdatedException` the browsers are not installed. Run `vendor/bin/sail root-shell -c "npx playwright install-deps chromium"` and `vendor/bin/sail npx playwright install chromium`.
