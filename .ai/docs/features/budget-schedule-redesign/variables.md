# Variables: Budget/Schedule Redesign

## No New Environment Variables, Config Values, or Secrets

This feature introduces no new `.env` variables, no new `config()` reads, no new cache keys, no new queue names, and no new API keys/credentials.

Verified by grepping every new/changed file in this feature's scope (`app/Models/Budget.php`, `app/Services/BudgetService.php`, `app/Services/InflationCalculator.php`, `app/Services/RecurrenceRuleService.php`, `app/Http/Controllers/API/BudgetApiController.php`, `app/Http/Requests/BudgetRequest.php`, `app/Policies/BudgetPolicy.php`, `app/Jobs/CalculateBudgetActiveFlag.php`) for `config(`, `Cache::`/`cache(`, and `->onQueue(` — none found:

- **No config-driven behavior.** `InflationCalculator`'s compounding rate is entirely per-row (`Budget.inflation`/`TransactionSchedule.inflation`, both user-set, nullable), not a global/config-level rate. `RecurrenceRuleService`'s `RECURRENCE_VIRTUAL_LIMIT` (100,000) is a hardcoded class constant, not a config value.
- **No new queue names.** `CalculateBudgetActiveFlag` and the `CalculateAccountMonthlySummary` dispatches from `BudgetService` use `dispatch()`/`ShouldQueue` with no `->onQueue()` call — they land on the application's existing default queue, same as every other queued job in the codebase.
- **No new cache keys.** The `account_monthly_summaries` table this feature writes into (via `CalculateAccountMonthlySummary`) is a pre-existing, unchanged caching mechanism (see `.ai/docs/specifications/budget-schedule-redesign/specification.md` Non-Goals) — this feature repoints one of its data sources (FR-3) but adds no new cache layer of its own.
- **No new secrets or third-party credentials.** `Budget` and the recurrence/inflation services are pure internal domain logic — no outbound calls, no stored credentials.

## Pre-Existing Variables This Feature Reads

None directly. No code inside `app/Models/Budget.php`, `app/Services/BudgetService.php`, `app/Services/InflationCalculator.php`, `app/Services/RecurrenceRuleService.php`, `app/Http/Controllers/API/BudgetApiController.php`, `app/Http/Requests/BudgetRequest.php`, or `app/Policies/BudgetPolicy.php` checks `config('yaffa.*')` or any other config namespace. The two cron entries this feature relies on (`app:cache:transaction-schedule-active-flags`, `app:record-scheduled-transactions`) are registered in `routes/console.php` inside the existing `if (config('yaffa.runs_scheduler'))` block (`routes/console.php:15-65`), alongside every other scheduled command in the app — same main-container-vs-worker gating as everything else, not a new toggle.
