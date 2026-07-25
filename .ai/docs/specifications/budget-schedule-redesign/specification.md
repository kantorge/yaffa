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
- Fix the forecast/schedule-instance calculation performance problems documented in [forecast-performance.md](forecast-performance.md) (the `replicate()`-per-occurrence relation drop and the per-month `Investment::find()` N+1) as part of this redesign's scope, landing before the inflation work (FR-8) is layered onto the same code paths — see FR-9. Scenario planning and debt tracking remain out of scope (Non-Goals; [future-directions.md](future-directions.md)).

## 3. Non-Goals

- Scenario planning (base + worst-case/best-case budget variants) — out of scope for this pass; see [future-directions.md](future-directions.md) for the recommended overlay/delta architecture that keeps this redesign compatible with it later.
- No polymorphic merge of `TransactionSchedule` and the new `Budget` period definition into one shared table — the recurrence *math* is shared via a small extracted helper, but the tables stay separate to avoid a migration on the existing, working `transaction_schedules` table for no behavior change on the schedule side.
- No merge of `transaction_schedules` into `transactions` itself, despite only a small percentage of rows ever having a schedule. This was considered and rejected: profiling the actual forecast performance problem (see [forecast-performance.md](forecast-performance.md)) found the cost is dominated by `Eloquent::replicate()` dropping loaded relations and an `Investment::find()` N+1 inside a per-month loop — nothing pointed to the `transactions`/`transaction_schedules` join itself being a bottleneck. Widening the hottest table in the app with ~9 mostly-null columns is not justified without evidence it solves a real problem; if profiling ever does implicate that join specifically, revisit this then.
- `inflation` stays on **both** `transaction_schedules` and `budgets` as two independent columns — this is intentional, not accidental duplication left over from not merging the tables. A real schedule can legitimately need its own escalation (e.g. a contractually rent-indexed payment), and a standalone budget target can separately need one (e.g. "assume grocery costs rise 3%/year") — they are the same *calculation* (FR-8) applied to two different, independently-editable things, not one concept that should live in only one place.
- No change to the "enter instance"/"skip instance" schedule workflow.

## 4. Functional Requirements

### FR-1: Remove the `budget` flag from `Transaction`

`schedule` remains the only planning-mode boolean on `Transaction`. A standard withdrawal/deposit with `schedule=true` and categorized items is, by that fact alone, both a forecast input and a budget-comparison input. No second flag exists to set or explain.

Because `budget` is gone, `Transaction::byScheduleType($type)` — a dynamic scope whose only remaining meaningful values are `schedule` (`schedule=true`) and `none` (`schedule=false`), all other branches (`budget`, `budget_only`, `both`, `any`, `schedule_only`) having depended on the removed column — is replaced entirely by a single `isSchedule()` scope (`where('schedule', true)`); the `schedule=false` case is expressed as a plain inline `where('schedule', false)` at the few call sites that need it, rather than kept as a named scope. Every current caller must be updated, including two not otherwise touched by this redesign: `app/Console/Commands/CalculateTransactionScheduleActiveFlags.php` (currently `byScheduleType('any')`, used to find every transaction needing its `TransactionSchedule.active` flag recalculated — becomes `isSchedule()`, since only real schedules have that flag) and `app/Http/Controllers/API/TransactionApiController.php::getScheduledItems()` (the `type=any`/`type=schedule`/`type=none` values it forwards into `byScheduleType()` narrow to just `schedule`/`none`; see FR-6 for how it also starts returning `Budget` rows).

### FR-2: Category budget-vs-actual chart sums every contributing source, regardless of account

`ReportApiController::budgetChart()`'s "budget" series becomes the sum, per category/period, of:

1. Schedule-flagged standard withdrawal/deposit transactions' items for the requested categories, projected across periods via the existing schedule-occurrence generation (query changes from `byScheduleType('budget')` to `byType('standard')->isSchedule()` — see FR-1; transfers are already structurally excluded since they never have items).
2. **All** *active* standalone `Budget` rows for the requested categories, projected via the shared recurrence helper (see FR-5) — **regardless of `account_id`**. An account-scoped `Budget` row (e.g. groceries via a specific credit card) and an account-agnostic one (e.g. a generic groceries target) for the same category are both included in the same total. The system does not attempt to detect or exclude overlap between them — see background.md, "Account Scoping for Budgets," for why this is by design rather than a defect to prevent. An inactive `Budget` (its recurrence rule produces no more future occurrences as of the observation date — see FR-4) is excluded, mirroring how the existing forecast bucket already filters on `transactionSchedule.active = true`.

The endpoint must also expose, per category/period, which underlying `Budget` rows (and their `account_id`, if any) contributed to the total, to satisfy FR-7. This is a response-shape change from the current endpoint, so `resources/js/reports/budgetchart.js` will need updating (see Section 8) — superseding the original assumption that this endpoint's contract could stay frozen. Its own drill-down table, which today lists contributing transactions by re-querying `GET /api/v1/transactions/scheduled-items?type=any`, must stop doing so once the `any` `byScheduleType()` branch is removed (FR-1) — it should instead be driven directly by the contributing-rows data this endpoint now returns.

### FR-3: Account-balance budget bucket reads only standalone budgets, attributed per account

`CalculateAccountMonthlySummary::getAccountBalanceBudgetData()` simplifies to read only from standalone, *active* `Budget` rows (`Budget.active = true`, FR-4) — mirroring the existing pattern in `getAccountBalanceForecastData()`/`getInvestmentValueForecastData()` of filtering on `transactionSchedule.active = true`, not a new rule. The `schedule=true` case is already fully covered by the existing forecast bucket, so the previous `budget_only` scope distinction disappears entirely (there is no longer a "budget-only transaction" case).

Each `Budget` row feeds `account_monthly_summaries` according to its own `account_id`: a row with `account_id` set populates that specific account's bucket (`account_entity_id` = the budget's account); a row with `account_id = null` populates the existing account-agnostic bucket (`account_entity_id = null`, already supported by the table). A single account's own balance/forecast view reads only its own bucket; the account-agnostic bucket rolls up into whole-portfolio aggregation but is not attributed to any one account.

### FR-4: New `Budget` entity

A `Budget` represents a category-level spending/income target with no backing transaction:

- `category_id` (required) — a budget is always category-scoped.
- `account_id` (nullable) — budgets remain account-agnostic by default. When set, the row additionally feeds that specific account's own balance-forecast bucket (FR-3) on top of counting toward its category's total (FR-2); it is not exclusive with an account-agnostic row for the same category.
- Currency is **not** a stored column. A budget's currency must never diverge from its linked account's currency, so rather than storing a `currency_id` that could drift out of sync (requiring validation to keep it honest), it is computed: when `account_id` is set, the effective currency is always that account's current currency; when `account_id` is null, it's the user's base currency. Expose this as a read-only accessor/relation (e.g. `Budget::currency()` resolving through `account.currency` or the base currency), not a fillable field — there is nothing for `BudgetRequest` to validate here because there is nothing for the user to set. If an account's currency is ever changed, every budget attached to it picks up the new currency automatically and correctly, with no reconciliation step needed.
- `amount` (required, target amount for the period).
- `comment` (nullable).
- `frequency` (`DAILY|WEEKLY|MONTHLY|YEARLY`), `interval` (default 1), `start_date` (required), `end_date` (nullable), `count` (nullable), `inflation` (nullable) — the period definition. No `next_date` and no `automatic_recording`: a standalone budget target is evaluated in aggregate per period, not advanced instance-by-instance the way a schedule is, and there is no transaction for it to auto-record.

`active` (boolean, **not** fillable — always computed, never user-set) — mirrors `TransactionSchedule.active`, which the codebase already computes automatically on create/update (`TransactionSchedule::booted()`) and keeps current via a periodic recalculation job/command. A `Budget` is active if, relative to the date of evaluation, its recurrence rule (`frequency`/`interval`/`start_date`/`end_date`/`count`) yields at least one occurrence on or after that date — computed via the shared recurrence helper (FR-5), the same underlying check `TransactionSchedule::isActive()` falls back to once its `next_date` shortcut doesn't apply. Unlike a schedule, a `Budget` has no `next_date` to short-circuit the check, so its `active` flag is always derived purely from the recurrence rule itself. An inactive `Budget` (rule exhausted — past `end_date`/`count`, or a recurrence that no longer produces future occurrences) is excluded from both the budget-vs-actual chart (FR-2) and the account-balance budget bucket (FR-3), exactly as an inactive schedule is already excluded from the forecast bucket today.

Known friction accepted deliberately: a user who wants an account-agnostic budget in a currency other than their base currency has no direct way to express that, since an account-agnostic `Budget` always resolves to the base currency. The workaround is to create a real account in the desired currency and attach the budget to it — an existing capability, not a new one — accepting a small amount of UI friction in exchange for making currency divergence structurally impossible rather than merely validated against.

### FR-5: Shared recurrence helper

Extract the period-math currently inline in `TransactionSchedule` (`getRecurrence()`, `isActive()`, the `recurr`/`Recurr\Rule` usage) into a small reusable component (e.g. `app/Services/RecurrenceRuleService.php`) consumed by both `TransactionSchedule` and `Budget`, so the `recurr` integration is not duplicated across the two tables.

### FR-6: Standalone Budget management lives on the existing schedules report page (no separate page)

`Budget` rows are managed from the same page as today's schedule report (`resources/views/reports/schedule.blade.php` / `resources/js/reports/schedules.js`), not a new standalone page — this also answers where the feature is reachable from, resolving what was an open question in an earlier draft of this spec.

The report's single listing merges two row sources into one table: `Transaction` rows with `schedule=true` (as today) and standalone `Budget` rows (new). The existing left-hand filter sidebar (`x-tablefilter-sidebar-switch`) is extended to filter by row type (Schedule / Budget) in place of today's now-meaningless `budget` boolean filter/column; the existing `Active` filter applies to both row types uniformly, since `Budget` now has its own automatically-computed `active` flag (FR-4). Columns that don't apply to a `Budget` row (there is no payee, no `next_date`, no schedule-only "enter/skip instance" actions — Non-Goals) render blank/muted for that row rather than the column being conditionally hidden — the same convention the table already uses today for an empty category cell.

The page's "Actions" card gains a "New Budget" entry alongside the existing "New scheduled standard/investment transaction" actions. Row-level contextual actions (today: edit/enter/skip) branch by row type: a `Budget` row offers edit/delete only (via `BudgetApiController`); a `Transaction` schedule row keeps its existing edit/enter-instance/skip-instance actions unchanged (Non-Goals, Section 3).

### FR-7: Budget total transparency

Wherever a category's summed budget figure is shown (the budget-vs-actual chart, and any future budget summary view), the user must be able to see which `Budget` rows — and their `account_id`, if any — were summed to produce it. This is the safeguard against unintended double counting described in background.md ("Account Scoping for Budgets"): the system does not deduplicate or enforce non-overlapping `Budget` rows for the same category, so visibility into a total's composition is required, not optional. At minimum, this means a breakdown/drill-down (e.g. a chart tooltip or details panel) listing each contributing row's amount, category, and account (or "no account").

### FR-8: Inflation calculation (flat annual rate)

Implement the previously-unused `inflation` field on both `TransactionSchedule` and `Budget`: an annual percentage rate that compounds once per calendar-year boundary relative to the year of `start_date` — the amount for the start year is the base `amount`; the moment a projection crosses into the next calendar year (even if `start_date` was December 31st, with almost none of the start year elapsed), the amount compounds by `(1 + inflation / 100)`, and again at each subsequent calendar-year boundary.

This must be implemented as a small, standalone, reusable calculation (e.g. a pure function such as `applyAnnualRate(amount, ratePercent, startDate, targetDate)`), deliberately kept separate from FR-5's calendar-occurrence logic — FR-5 determines *which periods are active*; this determines *what multiplier applies* to a given period. The separation is intentional: the same compounding calculation is expected to be reused for investment price growth and currency rate drift assumptions later (see [future-directions.md](future-directions.md)), neither of which has any relationship to `TransactionSchedule`'s occurrence rule.

It must be applied everywhere a recurring amount is projected forward: `Transaction::scheduleInstances()`, the schedule-derived side of `budgetChart()` (FR-2), the standalone-`Budget` side of `budgetChart()` (FR-2), and `CalculateAccountMonthlySummary`'s forecast/budget bucket methods (FR-3).

Per-year explicit override values (a different rate per calendar year, rather than one flat rate) are explicitly deferred — see future-directions.md. The flat-rate implementation must not preclude adding per-year overrides later as a fallback-compatible enhancement (i.e. a year with no explicit override falls back to the flat rate).

### FR-9: Fix forecast/schedule-instance calculation performance (prerequisite for FR-8)

Fix the two concrete performance defects documented in [forecast-performance.md](forecast-performance.md), confirmed by profiling (16-19s per job at roughly 1/10th of reported production scale):

1. `Transaction::scheduleInstances()` replaces its per-occurrence `$this->replicate()` (a full Eloquent clone, which silently drops already-loaded relations — e.g. `config` — forcing every virtual instance to independently re-query them) with a lightweight, non-Eloquent representation (e.g. a plain DTO/array) carrying only the fields the forecast aggregation actually consumes (date, account direction, amount, investment id, resolved `config`). No Eloquent casts, relations, or model events are needed for a value that is summed and discarded.
2. `CalculateAccountMonthlySummary::getInvestmentValueForecastData()` stops calling `Investment::find()`/`getLatestPrice()` once per investment *per forecast month*; instead, all investment prices needed for the run are fetched once, up front, into an in-memory map, reusing the batching pattern already used for currency rates elsewhere in the codebase (`CurrencyTrait::allCurrencyRatesByMonth()`).

This is a prerequisite for FR-8, not parallel, independent work: FR-8 adds a compounding-multiplier calculation inside these same two hot paths, and implementing it against the current unfixed code would add per-occurrence/per-month cost to an already-pathological loop instead of fixing it. FR-9 must land, be verified against forecast-performance.md's profiling method (query-count assertions, not just wall-clock), and ship before FR-8's implementation begins — see Section 12.

Scenario planning and debt-tracking forecasting ([future-directions.md](future-directions.md)) are not part of this fix — neither exists yet, so there is nothing of theirs to make fast. FR-9 only fixes the existing, already-shipped baseline forecast/schedule-instance calculation.

## 5. Data Model Changes

### New table: `budgets`

`id`, `user_id` (FK), `category_id` (FK, required), `account_id` (nullable FK to `account_entities`), `amount` (decimal), `comment` (nullable varchar), `frequency` (varchar), `interval` (int, default 1), `start_date` (date), `end_date` (nullable date), `count` (nullable int), `inflation` (nullable double), `active` (boolean, not nullable, system-maintained — see FR-4), timestamps. No `currency_id` column — see FR-4. No `next_date`/`automatic_recording` columns — see FR-4.

### `transactions` table

Drop the `budget` column (`tinyint(1) NOT NULL DEFAULT 0`). This is destructive; run only after the data backfill (Section 7) is verified, and only with explicit confirmation at execution time per `app/CLAUDE.md`'s migration rules (always implement `down()`, no destructive changes without explicit user confirmation).

### `transaction_details_standard` table

Change `account_from_id` and `account_to_id` from nullable to `NOT NULL`. Today they are nullable *only* to support budget-only transactions with an unset account (per the current `TransactionRequest`'s `$isBasic` branching, described in background.md) — every other case (a real historical transaction, a real schedule, a transfer) already requires both. Once budget-only transactions no longer exist (Section 7), there is no remaining legitimate case for either column to be null on a standard withdrawal/deposit/transfer row. This tightens a genuine, no-longer-needed looseness in the schema — but only after Section 7's pre-migration check (below) confirms no other, unexpected null-account rows exist that this redesign didn't anticipate.

## 6. Backend Components to Update

- `app/Models/Transaction.php` — drop `budget` from `$fillable`/casts; replace `byScheduleType()` entirely with a single `isSchedule()` scope (`where('schedule', true)`) — see FR-1; rewrite `scheduleInstances()` to stop cloning a full Eloquent model per occurrence (FR-9).
- `app/Models/Budget.php` (new) — model, casts, recurrence helper usage, a `currency()` accessor/relation resolving through `account.currency` when `account_id` is set, else the base currency (FR-4) — `currency_id` is not fillable/stored — and `booted()` hooks that compute `active` on create/update, mirroring `TransactionSchedule` (FR-4).
- `app/Services/RecurrenceRuleService.php` (new, or a trait) — extracted period-math (`getRecurrence()`) and the future-occurrence check underlying `isActive()`, used by both `TransactionSchedule` and the new `Budget.active` (FR-4).
- A small, separate inflation utility (e.g. `app/Services/InflationCalculator.php`, deliberately not part of `RecurrenceRuleService` per FR-8) — consumed by `Transaction::scheduleInstances()`, `ReportApiController::budgetChart()`, and `CalculateAccountMonthlySummary`'s forecast/budget bucket methods. Built on top of the FR-9-simplified versions of those methods, not the current ones.
- `app/Services/BudgetService.php` (new) — CRUD + occurrence projection.
- `app/Http/Requests/BudgetRequest.php` (new) — `category_id` required+owned, `account_id` nullable+owned, `amount` required numeric `gt:0`, plus the frequency/interval/start/end/count/inflation rules already used for `schedule_config` today.
- `app/Http/Requests/TransactionRequest.php` — drop `budget` validation and the `$isBasic` budget branch.
- `app/Policies/BudgetPolicy.php` (new) — ownership check, mirroring `TransactionPolicy`.
- `app/Http/Controllers/API/BudgetApiController.php` (new, CRUD API), thin, delegating to `BudgetService` — no separate Blade page: Budget management lives inside the existing schedules report page (FR-6).
- `app/Http/Controllers/API/TransactionApiController.php` / `app/Http/Controllers/TransactionController.php` — drop the `|| $transaction->budget` conditions (`storeStandard`, `updateStandard`, the `enter`/`createFromDraft` reset paths); rewrite `getScheduledItems()` to merge `Transaction` schedule rows with standalone `Budget` rows in one response (FR-6), narrowing its `type` query parameter to just `schedule`/`none` now that `any` has no meaning (FR-1).
- `app/Services/TransactionService.php` — drop the budget branch in `recalculateSummaryStandard()`/`recalculateSummaryInvestment()`; update the explanatory comment.
- `app/Http/Controllers/API/ReportApiController.php` — rewire `budgetChart()` per FR-2.
- `app/Jobs/CalculateAccountMonthlySummary.php` — rewire `getAccountBalanceBudgetData()` per FR-3 (including the `Budget.active` filter); fix `getInvestmentValueForecastData()`'s per-month `Investment::find()` N+1 by batch-fetching prices once per run (FR-9).
- `app/Console/Commands/CalculateTransactionScheduleActiveFlags.php` + `app/Jobs/CalculateTransactionScheduleActiveFlag.php` — update the command's query from `byScheduleType('any')` to `isSchedule()` (FR-1), and extend the recalculation to also (re)compute `active` for every `Budget` row (new job, or a branch in the existing one) — see FR-4.
- `app/Models/Account.php`, `app/Models/Payee.php`, `app/Models/AccountMonthlySummary.php`, `app/Services/TransactionItemMergeService.php`, `app/Listeners/ProcessTransactionUpdated.php` — drop the `->where('budget', ...)` / `!$transaction->budget` conditions accompanying `schedule` checks.
- `app/Http/Controllers/CategoryController.php`, `app/Services/PayeeCategoryStatsService.php` — drop the `where('transactions.budget', ...)` branches.
- A new pre-migration check command (e.g. `app:check:budget-migration`, see Section 7.1) — read-only audit, reports the four risk cases before the transforming migration is allowed to run. **Ships separately, in a current 3.x release**, ahead of everything else in this list (which lands together in 4.0.0) — see 7.1.
- Database migration files (see Section 7) for: creating `budgets`; the data transformation itself (7.2, no data-level `down()` — see 7.3); dropping the `budget` column (after 7.2 is verified); making `transaction_details_standard.account_from_id`/`account_to_id` `NOT NULL` (after 7.2 is verified — see Data Model Changes).
- `UPGRADE.md` — new "Upgrade from YAFFA 3.x to 4.x" section per 7.3.

## 7. Migration Strategy

The data transformation must run as **database migration files** (executed automatically by `php artisan migrate` on deploy), not a separately-invoked Artisan command that a deployer could forget to run. This is a change from an earlier draft of this spec, which specified a one-time command; a data migration this significant must be part of the standard, automatic migration pipeline, not an optional manual step.

### 7.1 Pre-migration check (required, run before the transforming migration)

Given the actual current data model has never enforced several of this redesign's assumptions at the database or backend level (see background.md — the transfer/investment budget restriction has only ever been a frontend convention), the naive transformation ("one `Budget` row per distinct category among a budget-only transaction's items") can silently lose or misattribute data in several concrete, real cases:

- **A budget-only transaction with zero `TransactionItem` rows.** `TransactionRequest` validates `items` only as `'array'` — never `required`/`min:1` — so this is a real, currently-possible state, not a hypothetical. The naive transform would produce zero `Budget` rows for it, silently discarding the amount/schedule entirely.
- **A budget-only withdrawal or deposit where the "non-null side" of `account_from_id`/`account_to_id` is a payee, not a real account.** `account_entities.config_type` distinguishes `'account'` from `'payee'` (`AccountEntity::isAccount()`/`isPayee()`); for a withdrawal, `account_to_id` is the payee side, and for a deposit, `account_from_id` is. Carrying either into `Budget.account_id` (which must only ever reference a real account, per FR-4) without checking `isAccount()` first would attach a budget to what's actually a payee.
- **A transfer or investment transaction with `budget=true`.** Business rules say this should never happen, but since it was never enforced at the backend, it isn't structurally impossible in existing data. A transfer has no items at all (structurally incompatible with the category-based transform); an investment transaction with a stray `budget=true` is outside the scope of what Section 7.2 processes at all. Both need surfacing for manual review, not silent skipping.
- **A budget-only transaction whose `currency_id` doesn't match its linked account's current currency.** Since `Budget` no longer stores its own `currency_id` (FR-4), any pre-existing mismatch needs to be resolved (or at least surfaced) before conversion, rather than silently discarded by adopting the account's currency going forward.

Implement this as a dedicated check (an Artisan command is appropriate here, since it's a read-only audit, not a mutation) that reports counts for each case above. **The transforming migration (7.2) must refuse to proceed if any of these are found**, per `app/CLAUDE.md`'s "no destructive changes without explicit user confirmation" — surfacing the problem is not optional, and silently dropping a user's data during a redesign migration is not acceptable.

**This check command ships separately, in a current 3.x release, ahead of the breaking 4.0.0 release that contains the rest of this redesign** — mirroring the existing precedent in `UPGRADE.md` for the 2.x→3.x `transaction_type` migration, where `php artisan app:upgrade:check-3x` was released in the last 2.x version specifically so operators could self-audit before the major upgrade. This lets self-hosted operators run the check well ahead of time, on their current version, with no time pressure to fix flagged data before upgrading.

### 7.2 The transforming migration

- Transactions currently `schedule=true` (regardless of the old `budget` value): no data movement needed. They start contributing to the budget chart once the chart query changes to key off `schedule` alone (FR-2) and the `budget` column is dropped.
- Transactions currently `schedule=false, budget=true` (pure budget-only fake transactions, and confirmed clean by 7.1): for each, create one `Budget` row per distinct `category_id` among its `transactionItems` (summing amounts if a category appears in multiple items on the same transaction), carrying over the non-null side of `account_from_id`/`account_to_id` into `Budget.account_id` **only if that side is a real account** (`isAccount()` — never a payee; leave `account_id` null otherwise), and the linked `TransactionSchedule`'s frequency/interval/start_date/end_date/count/inflation. `active` is not copied from anywhere — it's computed the same way it will be for every future `Budget`, via the model's `booted()` hook (FR-4), from the frequency/interval/start_date/end_date/count being carried over.
- **The original fake transactions must be hard-deleted once converted** — not soft-deleted or archived. They represent nothing once `budget` is removed as a concept, and keeping them around (even "for audit") reintroduces exactly the fake-transaction-as-budget-storage problem this whole redesign exists to remove.

### 7.3 No downgrade path — matches existing precedent, `UPGRADE.md` must document it

`down()` for the transforming migration (7.2) is **not supported**. Reconstructing the original budget-only transactions from `Budget` rows would require preserving lineage from every `Budget` row back to whichever source transaction and category it came from — and even that only stays coherent until a user creates, edits, or deletes a `Budget` row through the new UI, at which point there is no longer a real "original transaction" for a down-migration to reconstruct. Promising reversibility here would be dishonest in the same way it would have been for the YAFFA 2.x→3.x `transaction_type` migration, which set the actual precedent this project already follows: that migration is also irreversible after the legacy table is dropped, and `UPGRADE.md` handles it with an explicit warning and a mandatory backup step rather than a `down()` implementation.

This redesign should follow the same, already-established pattern instead of inventing a new one:

- `UPGRADE.md` needs a new **"Upgrade from YAFFA 3.x to 4.x"** section, structured like the existing 2.x→3.x section: a breaking-changes summary, a step-by-step guide, a mandatory backup step ahead of running migrations, and an explicit statement that there is no native downgrade path.
- The pre-migration check command (7.1) plays the same role `app:upgrade:check-3x` played for the previous major version: run it before upgrading, fix anything it flags, back up the database, then upgrade.
- `down()` for the schema-only parts of this migration (e.g. `Schema::dropIfExists('budgets')`) can still be implemented where it's trivially safe to do so, per `app/CLAUDE.md`'s migration rules — but the *data transformation* itself has no reverse, and the migration file's own comments and `UPGRADE.md` should say so plainly, not imply otherwise.

## 8. Frontend Components to Update

- `resources/js/transactions/components/form/TransactionFormStandard.vue` — remove the Budget checkbox/section entirely, the `isBudget` prop passed to `<transaction-schedule>`, and the transfer-disable-while-budget / budget-disable-while-transfer logic.
- New Vue form/modal component(s) for `Budget` create/edit, reusing existing form patterns — no standalone Budget page (FR-6).
- `resources/views/reports/schedule.blade.php` + `resources/js/reports/schedules.js` — per FR-6: merge `Budget` rows into the existing DataTable (sourced from the rewritten `getScheduledItems()`, see Section 6), replace the `budget` boolean filter/column with a row-type (Schedule/Budget) filter, blank out columns that don't apply to a `Budget` row (payee, next date), and add a "New Budget" action alongside the existing schedule-creation actions.
- `resources/js/dashboard/components/widgets/ScheduleCalendar.vue` and `resources/js/account/show.js` — no functional change, but both call `getScheduledItems()` with `type=schedule`; confirm that value's meaning is unaffected by the `isSchedule()` rewrite (FR-1) and that neither view starts showing `Budget` rows, matching [future-directions.md](future-directions.md)'s principle that only forward-looking projection reports need budget/scenario awareness.
- `resources/js/reports/budgetchart.js` (and its Blade host) — consume the extended `budgetChart()` response (FR-2) and add a breakdown/drill-down (e.g. an expandable tooltip or details panel) listing the contributing `Budget` rows and their accounts per FR-7; its own drill-down table stops sourcing from `getScheduledItems()?type=any` (that value is removed, FR-1) and instead reads the contributing-rows data `budgetChart()` now returns directly.

## 9. Documentation Updates

- Split `.ai/docs/assets/transactions/schedules-and-budgets.md` into `.ai/docs/assets/transactions/schedules.md` (schedule stays a transaction concept, documented as always feeding both forecast and category budget comparison) and a new `.ai/docs/assets/budget/budget.md` (the new standalone entity, sibling to `category`/`account`).
- Update cross-references in `standard-transactions.md`, `investment-transactions.md`, `features/reports/budget-and-schedules.md`, `features/dashboard/schedule-calendar.md`. `features/reports/budget-and-schedules.md` in particular should document the merged Schedule+Budget listing (FR-6) as the single UI for both entities — there is no separate Budget page to describe.

## 10. Testing Requirements

### Backend Tests

- New `tests/Feature/BudgetApiTest.php` — CRUD, ownership, occurrence projection.
- Update/remove existing tests referencing `Transaction`'s `budget` flag (grep `tests/` for `->budget`).
- Update `ReportApiController` budget-chart feature tests to seed schedule-flagged transactions and standalone `Budget` rows instead of budget-flagged transactions, including: (a) a split-category case (e.g. a telco-bill-style transaction) to confirm a scheduled transaction's own items are never counted twice within the same report; (b) a case with both an account-scoped and an account-agnostic `Budget` row for the same category to confirm the total is their sum (not deduplicated) and that the response exposes the contributing rows per FR-7.
- Update `CalculateAccountMonthlySummary` tests to reflect the simplified budget-bucket source and the per-account vs. account-agnostic attribution (FR-3).
- New unit test for the inflation utility (FR-8): a schedule/budget with `start_date` December 15th and a positive `inflation` rate shows the base amount through December 31st and the first compounded amount starting January 1st of the following year — not one year after `start_date`. Also cover multi-year compounding and `inflation = null`/`0` (no-op).
- New test for the pre-migration check (7.1): seed each of the four risk cases (zero-item budget transaction, payee-side-only budget transaction, a transfer/investment transaction with a stray `budget=true`, a currency mismatch between a budget-only transaction and its linked account) and confirm each is detected and reported; confirm the transforming migration refuses to run while any are present.
- New test for the transforming migration (7.2): confirm hard-deletion of converted transactions (not soft-deleted — assert the row is gone, not merely flagged), and confirm the payee-vs-account check (`isAccount()`) correctly leaves `Budget.account_id` null when the only non-null side was a payee.
- New test for `Budget::currency()` (FR-4): confirm it resolves to the linked account's currency when `account_id` is set, the base currency when null, and that it updates automatically (not stale) if the account's currency is changed after the budget was created.
- New test for the `transaction_details_standard` `NOT NULL` migration: confirm it fails loudly (rather than silently truncating/nulling data) if run against a database still containing null `account_from_id`/`account_to_id` rows — i.e. confirm it's genuinely gated on 7.1/7.2 having completed first.
- New test for `Budget::active` (FR-4): confirm it's computed automatically on create/update (never accepted as request input), matches the expected true/false outcome for a representative set of recurrence rules relative to a fixed "now", and is recomputed by the same recalculation job/command that maintains `TransactionSchedule.active` (the updated `CalculateTransactionScheduleActiveFlags`/`CalculateTransactionScheduleActiveFlag`, FR-1).
- Update the schedules-report feature test(s) to cover the merged listing (FR-6): both a `Transaction` schedule row and a standalone `Budget` row appear in the same response, the row-type filter narrows correctly, and columns that don't apply to a `Budget` row (payee, next date) are absent/blank rather than erroring.
- New regression tests for FR-9: assert `Transaction::scheduleInstances()`'s query count does not grow with the number of virtual occurrences generated (catches the relation-drop-triggered re-query regression), and assert `CalculateAccountMonthlySummary::getInvestmentValueForecastData()` issues at most one price lookup per investment per job run regardless of forecast horizon length; also assert output (aggregated monthly totals) is unchanged before/after the optimization for a fixed fixture dataset.

### Manual Verification

- Create a scheduled withdrawal with split categories; confirm it appears in the budget-vs-actual chart with no extra step, and that it is not also double-counted via the standalone budget bucket.
- Create a standalone Budget for an uncertain-target category (e.g. groceries); confirm it also appears in the chart.
- Create both a generic ($400, no account) and an account-scoped ($200, credit card) `Budget` row for the same category; confirm the chart shows their sum ($600), the credit card's own balance forecast includes only its $200, and the breakdown UI (FR-7) shows both contributing rows.
- Create a schedule or Budget with a non-January `start_date` and a nonzero `inflation` rate; confirm the projected amount steps up at the next calendar-year boundary, not on the anniversary of `start_date`.
- Confirm transfer and investment transaction forms are unaffected (they never exposed a budget control).
- Confirm the schedules report page shows both real schedules and standalone Budgets in one table, with the row-type filter working and irrelevant columns (e.g. payee for a Budget row) blank.
- Recalculate forecasts for a dataset with a multi-decade schedule horizon (mirroring forecast-performance.md's profiling setup) and confirm the job completes without the previously-observed 16-19s-per-job spikes.

## 11. Acceptance Criteria

1. `transactions.budget` column and all references to it are removed from the codebase.
2. A scheduled standard withdrawal/deposit's categorized items appear in the category budget-vs-actual chart without any additional flag or step.
3. Transfers and investment transactions cannot contribute to category budgeting (structural, not flag-based) but investment schedules still contribute to account-balance forecasting.
4. A standalone `Budget` can be created for a category with no linked transaction and appears correctly in the chart and in account-balance budget projections, whether or not it has an `account_id`.
5. A category's budget-vs-actual total is the sum of every `Budget` row for that category regardless of account, plus schedule-derived items; an account-scoped row and an account-agnostic row for the same category both count, and this is expected behavior, not a defect — the total's composition must be visible to the user per FR-7, not hidden inside an opaque sum.
6. A scheduled transaction's own items are never counted twice within the same report (e.g., once via the forecast bucket and again via the standalone budget bucket) — this is the only "double counting" the redesign guards against; summation across multiple legitimate `Budget` rows is not.
7. Existing `budget=true` data is fully migrated (either folded into ongoing schedule participation, or converted into standalone `Budget` rows, and hard-deleted from `transactions` once converted — never soft-deleted or archived) before the `budget` column is dropped.
8. The transforming migration (7.2) refuses to run while the pre-migration check (7.1) reports any unresolved risk case (zero-item budgets, payee-attributed budgets, stray transfer/investment `budget=true` rows, currency mismatches) — no user data is silently dropped or misattributed during migration.
9. `Budget` never stores a currency independently of its linked account — `Budget.currency()` is always derived (FR-4), so there is no state in which a budget's displayed currency has drifted from its account's.
10. `transaction_details_standard.account_from_id`/`account_to_id` are `NOT NULL`, and this constraint is only ever added after 7.1/7.2 confirm no legitimate null-account rows remain.
11. Inflation-compounded amounts (FR-8) are correctly reflected in schedule instances, the budget-vs-actual chart, and account-balance/budget forecast projections, stepping up at calendar-year boundaries rather than on `start_date`'s anniversary.
12. `vendor/bin/sail artisan test --compact`, `./vendor/bin/pint --dirty`, and `./vendor/bin/phpstan analyse` pass.
13. FR-9's fix is verified by regression tests asserting `scheduleInstances()` no longer triggers relation re-queries per virtual occurrence and `getInvestmentValueForecastData()` fetches each investment's price at most once per job run — not merely by observing a faster wall-clock time.
14. `Budget.active` is never user-set, is recomputed automatically on create/update and via the periodic recalculation job, and correctly reflects whether the budget's recurrence rule has at least one occurrence on or after the evaluation date.

## 12. Rollout Plan

This project uses real semver releases (tagged `vX.Y.Z`, changelog generated from conventional commits). This redesign is a **major/breaking** change — it drops a column, removes request/response fields other clients could depend on (`budget` on `Transaction`), and changes `budgetChart()`'s response shape — and ships as a single `4.0.0` release, following the same precedent already set by the YAFFA 2.x→3.x `transaction_type` migration (also an irreversible, single-release breaking change with its own `UPGRADE.md` section and pre-upgrade check command).

**Ships early, in a current 3.x release, independent of everything else below:**

1. The pre-migration check command (7.1) — so self-hosted operators can audit their data well ahead of the 4.0.0 upgrade, exactly as `app:upgrade:check-3x` shipped in the last 2.x release ahead of 3.0.0.
2. FR-9's performance fix (the `scheduleInstances()` DTO rewrite and the investment-price batching) — it touches no schema and no API contract, so it isn't a breaking change and doesn't need to wait for 4.0.0. It ships first, on its own, and is verified (functionally equivalent output, plus the query-count regression tests) before any 4.0.0 work begins, since FR-8 builds its inflation logic directly on top of this simplified code — see FR-9.

**Ships together in `4.0.0`, after FR-9 has landed:**

3. Add the `budgets` migration, `Budget` model (including the automatic `active` flag), shared recurrence helper, inflation utility (FR-8, built on the FR-9-simplified `scheduleInstances()`/forecast code), currency accessor, policy, form request, service, and controllers.
4. Extend the existing schedules report page and its `getScheduledItems()` endpoint to also list `Budget` rows (FR-6); replace `byScheduleType()` with the `isSchedule()` scope everywhere it's used, including `CalculateTransactionScheduleActiveFlags` (FR-1).
5. Run the transforming migration (7.2) — converts remaining budget-only transactions to `Budget` rows, hard-deletes the originals. No data-level `down()` (7.3).
6. Rewire `budgetChart()` (both halves of FR-2, plus the FR-7 breakdown) and `getAccountBalanceBudgetData()` (FR-3).
7. Remove the `budget` flag from `Transaction`, `TransactionRequest`, `TransactionService`, and all consuming queries/listeners; remove the Budget checkbox from `TransactionFormStandard.vue`; remove the now-meaningless `budget` filter/column from the schedules report page (replaced by the row-type filter added in step 4).
8. Drop the `budget` column via its own migration; make `transaction_details_standard.account_from_id`/`account_to_id` `NOT NULL` via its own migration (Data Model Changes).
9. Update `.ai/docs` per Section 9, and add the new "Upgrade from YAFFA 3.x to 4.x" section to `UPGRADE.md` per 7.3 (breaking-changes summary, step-by-step guide referencing the 3.x pre-migration check command, mandatory backup step, explicit no-downgrade-path statement).
10. Add/update tests; run the focused suite, then the full suite if requested.

## 13. Open Questions

1. What remediation path a self-hosted admin actually follows when the pre-migration check (7.1) flags rows (e.g. a zero-item budget-only transaction) — fix it through existing transaction-editing UI before re-running the check, or does this need a small dedicated fixup tool? Self-hosted YAFFA instances typically have one admin, so this needs to be something they can reasonably act on unassisted.

_Resolved since the previous draft: Budget management UI placement is no longer open — it lives on the existing schedules report page, merged into the same table (FR-6)._
