# Budget/Schedule Concept Redesign Specification

## 1. Purpose

Redefine how YAFFA models the "schedule" and "budget" transaction concepts, and restructure the implementation to remove the confusion and validation gaps found in the current design. This document is self-contained and intended as an implementation handoff for a coding agent.

See [background.md](background.md) for the current-model analysis, the problems that motivated this change, and the principle this specification implements. This document does not restate that rationale — it defines what to build.

## 2. Goals

- Remove the `budget` boolean/flag from `Transaction` entirely — no replacement flag, not even an opt-out.
- Make a scheduled standard withdrawal/deposit transaction's categorized items **always** count toward both account-balance forecasting (unchanged) and the category budget-vs-actual comparison (new — previously required a separate opt-in flag).
- Introduce a new standalone `Budget` entity (category + target amount + its own period definition, no linked transaction) for targets that have no backing transaction.
- Preserve the existing structural exclusions: transfers never contribute to category budgeting (no items), investment transactions never contribute to category budgeting (no items) but still contribute to account-balance forecasting via `schedule`.
- Make category budget totals transparently traceable to their constituent `Budget` rows (and schedule-derived items), so that multiple contributing entries for the same category — including an account-scoped row alongside an account-agnostic one — are visible rather than hidden inside an opaque sum.

## 3. Non-Goals

- Scenario planning (base + worst-case/best-case budget variants) — out of scope for this pass; see background.md for why the `Budget` entity boundary still supports it later.
- No polymorphic merge of `TransactionSchedule` and the new `Budget` period definition into one shared table — the recurrence *math* is shared via a small extracted helper, but the tables stay separate to avoid a migration on the existing, working `transaction_schedules` table for no behavior change on the schedule side.
- No change to the "enter instance"/"skip instance" schedule workflow.

## 4. Functional Requirements

### FR-1: Remove the `budget` flag from `Transaction`

`schedule` remains the only planning-mode boolean on `Transaction`. A standard withdrawal/deposit with `schedule=true` and categorized items is, by that fact alone, both a forecast input and a budget-comparison input. No second flag exists to set or explain.

### FR-2: Category budget-vs-actual chart sums every contributing source, regardless of account

`ReportApiController::budgetChart()`'s "budget" series becomes the sum, per category/period, of:

1. Schedule-flagged standard withdrawal/deposit transactions' items for the requested categories, projected across periods via the existing schedule-occurrence generation (query changes from `byScheduleType('budget')` to `byType('standard')->where('schedule', true)`; transfers are already structurally excluded since they never have items).
2. **All** standalone `Budget` rows for the requested categories, projected via the shared recurrence helper (see FR-5) — **regardless of `account_id`**. An account-scoped `Budget` row (e.g. groceries via a specific credit card) and an account-agnostic one (e.g. a generic groceries target) for the same category are both included in the same total. The system does not attempt to detect or exclude overlap between them — see background.md, "Account Scoping for Budgets," for why this is by design rather than a defect to prevent.

The endpoint must also expose, per category/period, which underlying `Budget` rows (and their `account_id`, if any) contributed to the total, to satisfy FR-7. This is a response-shape change from the current endpoint, so `resources/js/reports/budgetchart.js` will need updating (see Section 8) — superseding the original assumption that this endpoint's contract could stay frozen.

### FR-3: Account-balance budget bucket reads only standalone budgets, attributed per account

`CalculateAccountMonthlySummary::getAccountBalanceBudgetData()` simplifies to read only from standalone `Budget` rows. The `schedule=true` case is already fully covered by the existing forecast bucket, so the previous `budget_only` scope distinction disappears entirely (there is no longer a "budget-only transaction" case).

Each `Budget` row feeds `account_monthly_summaries` according to its own `account_id`: a row with `account_id` set populates that specific account's bucket (`account_entity_id` = the budget's account); a row with `account_id = null` populates the existing account-agnostic bucket (`account_entity_id = null`, already supported by the table). A single account's own balance/forecast view reads only its own bucket; the account-agnostic bucket rolls up into whole-portfolio aggregation but is not attributed to any one account.

### FR-4: New `Budget` entity

A `Budget` represents a category-level spending/income target with no backing transaction:

- `category_id` (required) — a budget is always category-scoped.
- `account_id` (nullable) — budgets remain account-agnostic by default. When set, the row additionally feeds that specific account's own balance-forecast bucket (FR-3) on top of counting toward its category's total (FR-2); it is not exclusive with an account-agnostic row for the same category.
- `currency_id` (nullable).
- `amount` (required, target amount for the period).
- `comment` (nullable).
- `frequency` (`DAILY|WEEKLY|MONTHLY|YEARLY`), `interval` (default 1), `start_date` (required), `end_date` (nullable), `count` (nullable), `inflation` (nullable) — the period definition. No `next_date`, no `automatic_recording`, no `active` pointer: a standalone budget target is evaluated in aggregate per period, it does not advance instance-by-instance the way a schedule does.

### FR-5: Shared recurrence helper

Extract the period-math currently inline in `TransactionSchedule` (`getRecurrence()`, `isActive()`, the `recurr`/`Recurr\Rule` usage) into a small reusable component (e.g. `app/Services/RecurrenceRuleService.php`) consumed by both `TransactionSchedule` and `Budget`, so the `recurr` integration is not duplicated across the two tables.

### FR-6: Standalone Budget management UI

A new page for creating/editing/listing standalone `Budget` rows (category, amount, optional account, frequency/interval/dates/inflation), reachable from the existing schedules report page (which becomes schedule-only once the budget filter/column is removed).

### FR-7: Budget total transparency

Wherever a category's summed budget figure is shown (the budget-vs-actual chart, and any future budget summary view), the user must be able to see which `Budget` rows — and their `account_id`, if any — were summed to produce it. This is the safeguard against unintended double counting described in background.md ("Account Scoping for Budgets"): the system does not deduplicate or enforce non-overlapping `Budget` rows for the same category, so visibility into a total's composition is required, not optional. At minimum, this means a breakdown/drill-down (e.g. a chart tooltip or details panel) listing each contributing row's amount, category, and account (or "no account").

## 5. Data Model Changes

### New table: `budgets`

`id`, `user_id` (FK), `category_id` (FK, required), `account_id` (nullable FK to `account_entities`), `currency_id` (nullable FK), `amount` (decimal), `comment` (nullable varchar), `frequency` (varchar), `interval` (int, default 1), `start_date` (date), `end_date` (nullable date), `count` (nullable int), `inflation` (nullable double), timestamps.

### `transactions` table

Drop the `budget` column (`tinyint(1) NOT NULL DEFAULT 0`). This is destructive; run only after the data backfill (Section 7) is verified, and only with explicit confirmation at execution time per `app/CLAUDE.md`'s migration rules (always implement `down()`, no destructive changes without explicit user confirmation).

## 6. Backend Components to Update

- `app/Models/Transaction.php` — drop `budget` from `$fillable`/casts; simplify `byScheduleType()` to a plain schedule true/false check (drop `budget`, `budget_only`, `both`, `any` branches entirely).
- `app/Models/Budget.php` (new) — model, casts, recurrence helper usage.
- `app/Services/RecurrenceRuleService.php` (new, or a trait) — extracted period-math, used by `TransactionSchedule` and `Budget`.
- `app/Services/BudgetService.php` (new) — CRUD + occurrence projection.
- `app/Http/Requests/BudgetRequest.php` (new) — `category_id` required+owned, `account_id` nullable+owned, `amount` required numeric `gt:0`, plus the frequency/interval/start/end/count/inflation rules already used for `schedule_config` today.
- `app/Http/Requests/TransactionRequest.php` — drop `budget` validation and the `$isBasic` budget branch.
- `app/Policies/BudgetPolicy.php` (new) — ownership check, mirroring `TransactionPolicy`.
- `app/Http/Controllers/BudgetController.php` (new, Blade page) and `app/Http/Controllers/API/BudgetApiController.php` (new, CRUD API + list endpoint), thin, delegating to `BudgetService`.
- `app/Http/Controllers/API/TransactionApiController.php` / `app/Http/Controllers/TransactionController.php` — drop the `|| $transaction->budget` conditions (`storeStandard`, `updateStandard`, the `enter`/`createFromDraft` reset paths).
- `app/Services/TransactionService.php` — drop the budget branch in `recalculateSummaryStandard()`/`recalculateSummaryInvestment()`; update the explanatory comment.
- `app/Http/Controllers/API/ReportApiController.php` — rewire `budgetChart()` per FR-2.
- `app/Jobs/CalculateAccountMonthlySummary.php` — rewire `getAccountBalanceBudgetData()` per FR-3.
- `app/Models/Account.php`, `app/Models/Payee.php`, `app/Models/AccountMonthlySummary.php`, `app/Services/TransactionItemMergeService.php`, `app/Listeners/ProcessTransactionUpdated.php` — drop the `->where('budget', ...)` / `!$transaction->budget` conditions accompanying `schedule` checks.
- `app/Http/Controllers/CategoryController.php`, `app/Services/PayeeCategoryStatsService.php` — drop the `where('transactions.budget', ...)` branches.

## 7. Data Backfill (one-time Artisan command)

- Transactions currently `schedule=true` (regardless of the old `budget` value): no data movement needed. They start contributing to the budget chart once the chart query changes to key off `schedule` alone (FR-2) and the `budget` column is dropped.
- Transactions currently `schedule=false, budget=true` (pure budget-only fake transactions): for each, create one `Budget` row per distinct `category_id` among its `transactionItems` (summing amounts if a category appears in multiple items on the same transaction), carrying over the non-null side of `account_from_id`/`account_to_id` into `Budget.account_id` (null if both are null), and the linked `TransactionSchedule`'s frequency/interval/start_date/end_date/count/inflation. After verification, delete the original fake transactions.

## 8. Frontend Components to Update

- `resources/js/transactions/components/form/TransactionFormStandard.vue` — remove the Budget checkbox/section entirely, the `isBudget` prop passed to `<transaction-schedule>`, and the transfer-disable-while-budget / budget-disable-while-transfer logic.
- New Vue page(s) for `Budget` CRUD, reusing existing DataTable/form patterns.
- `resources/views/reports/schedule.blade.php` + `resources/js/reports/schedules.js` — remove the budget filter toggle and "budget" icon column; add a nav link to the new Budgets management page.
- `resources/js/reports/budgetchart.js` (and its Blade host) — consume the extended `budgetChart()` response (FR-2) and add a breakdown/drill-down (e.g. an expandable tooltip or details panel) listing the contributing `Budget` rows and their accounts per FR-7.

## 9. Documentation Updates

- Split `.ai/docs/assets/transactions/schedules-and-budgets.md` into `.ai/docs/assets/transactions/schedules.md` (schedule stays a transaction concept, documented as always feeding both forecast and category budget comparison) and a new `.ai/docs/assets/budget/budget.md` (the new standalone entity, sibling to `category`/`account`).
- Update cross-references in `standard-transactions.md`, `investment-transactions.md`, `features/reports/budget-and-schedules.md`, `features/dashboard/schedule-calendar.md`.

## 10. Testing Requirements

### Backend Tests

- New `tests/Feature/BudgetApiTest.php` — CRUD, ownership, occurrence projection.
- Update/remove existing tests referencing `Transaction`'s `budget` flag (grep `tests/` for `->budget`).
- Update `ReportApiController` budget-chart feature tests to seed schedule-flagged transactions and standalone `Budget` rows instead of budget-flagged transactions, including: (a) a split-category case (e.g. a telco-bill-style transaction) to confirm a scheduled transaction's own items are never counted twice within the same report; (b) a case with both an account-scoped and an account-agnostic `Budget` row for the same category to confirm the total is their sum (not deduplicated) and that the response exposes the contributing rows per FR-7.
- Update `CalculateAccountMonthlySummary` tests to reflect the simplified budget-bucket source and the per-account vs. account-agnostic attribution (FR-3).

### Manual Verification

- Create a scheduled withdrawal with split categories; confirm it appears in the budget-vs-actual chart with no extra step, and that it is not also double-counted via the standalone budget bucket.
- Create a standalone Budget for an uncertain-target category (e.g. groceries); confirm it also appears in the chart.
- Create both a generic ($400, no account) and an account-scoped ($200, credit card) `Budget` row for the same category; confirm the chart shows their sum ($600), the credit card's own balance forecast includes only its $200, and the breakdown UI (FR-7) shows both contributing rows.
- Confirm transfer and investment transaction forms are unaffected (they never exposed a budget control).

## 11. Acceptance Criteria

1. `transactions.budget` column and all references to it are removed from the codebase.
2. A scheduled standard withdrawal/deposit's categorized items appear in the category budget-vs-actual chart without any additional flag or step.
3. Transfers and investment transactions cannot contribute to category budgeting (structural, not flag-based) but investment schedules still contribute to account-balance forecasting.
4. A standalone `Budget` can be created for a category with no linked transaction and appears correctly in the chart and in account-balance budget projections, whether or not it has an `account_id`.
5. A category's budget-vs-actual total is the sum of every `Budget` row for that category regardless of account, plus schedule-derived items; an account-scoped row and an account-agnostic row for the same category both count, and this is expected behavior, not a defect — the total's composition must be visible to the user per FR-7, not hidden inside an opaque sum.
6. A scheduled transaction's own items are never counted twice within the same report (e.g., once via the forecast bucket and again via the standalone budget bucket) — this is the only "double counting" the redesign guards against; summation across multiple legitimate `Budget` rows is not.
7. Existing `budget=true` data is fully migrated (either folded into ongoing schedule participation, or converted into standalone `Budget` rows) before the `budget` column is dropped.
8. `vendor/bin/sail artisan test --compact`, `./vendor/bin/pint --dirty`, and `./vendor/bin/phpstan analyse` pass.

## 12. Rollout Plan

1. Add the `budgets` migration, `Budget` model, shared recurrence helper, policy, form request, service, and controllers.
2. Rewire `budgetChart()` and `getAccountBalanceBudgetData()` to their new sources (Sections FR-2, FR-3).
3. Run the data backfill command against a copy of production data; verify counts/amounts before proceeding.
4. Remove the `budget` flag from `Transaction`, `TransactionRequest`, `TransactionService`, and all consuming queries/listeners.
5. Drop the `budget` column via a dedicated, explicitly-confirmed migration.
6. Update frontend forms and the schedules report page; add the new Budget management UI.
7. Update `.ai/docs` per Section 9.
8. Add/update tests; run the focused suite, then the full suite if requested.

## 13. Open Questions

1. Exact placement and naming of the new Budget management UI within navigation (standalone menu item vs. a tab on the existing schedules report page).
2. Whether `Budget.currency_id` should default from the linked `account_id` (if set) or from the user's base currency when `account_id` is null.
3. Whether the data backfill should preserve the original fake transactions (soft-deleted, for audit) or hard-delete them once migrated.
