# Phase 1 plan

Written alongside the build, because the owner asked for phase 1 to be built directly.

- **Extract** `app/Services/BudgetChartService.php::chart(User, Collection $categories, ?accountSelection, ?accountEntity)`. The body is moved from `ReportApiController::budgetChart()` unchanged. The controller keeps request parsing, child-category resolution and the JSON envelope.
- **`CurrencyTrait::allCurrencyRatesByMonth()`** gets an optional `$userId`, because it read `auth()` and the service must run for a given user (artisan, jobs).
- **Batched aggregation** `aggregateByCategory(User)`: one grouped SQL query for actuals; schedules and Budget rows loaded and projected once. It returns base-currency series per category and month. It covers scope `any` only.
- **Tests:** `tests/Feature/API/BudgetChartRegressionTest.php` has golden-file tests (`tests/Fixtures/budget_chart_*.json`, recorded from the pre-extraction controller) plus an equivalence test of `aggregateByCategory` against `chart()`.
- **Measure** on the dev database.
