# Flows: Budget/Schedule Redesign

Only flows that touch permissions, data integrity, or side effects are documented here. Pure UI rendering (e.g. the merged schedules-and-budgets table simply displaying rows already fetched) is skipped.

## 1. Create a Standalone Budget

- **Actor:** an authenticated, email-verified user, from the "Schedules and Budgets" report page (`resources/js/reports/components/BudgetForm.vue`, action `new`).
- **Precondition:** user is logged in; owns the category (and, if scoped, the account) being referenced.
- **Success outcome:** a new `budgets` row exists, `active` computed automatically, and the account-balance forecast bucket it feeds is recalculated.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. User clicks "New Budget," fills category, optional account, transaction type, amount, and a recurrence rule (frequency/interval/start/end/count/by_day/by_month/inflation). | Browser (client-side) | None yet. | None. |
| 2. `POST /api/v1/budgets` `{ category_id, account_id?, transaction_type, amount, ... }` | Browser → server | `auth:sanctum` + `verified` + `abilities:write` (class-level `#[Middleware('abilities:write', only: ['store', 'update', 'destroy'])]`, `app/Http/Controllers/API/BudgetApiController.php:16-23`). Deny case: a bearer token without `write` gets 403 before the controller runs. | None yet. |
| 3. `#[Authorize('create', Budget::class)]` attribute on `store()` (`app/Http/Controllers/API/BudgetApiController.php:72`) | Server | `BudgetPolicy::create()` (`app/Policies/BudgetPolicy.php:40-43`) — returns `true` unconditionally for any authenticated user; this is **not** where ownership is enforced. Laravel's `Illuminate\Routing\Attributes\Controllers\Authorize` resolves the Policy call declaratively before the method body runs — functionally the same timing as an in-body `Gate::authorize()` call, just expressed as a method attribute rather than a line of code inside `store()` (this codebase-wide syntax change landed with the Laravel 13 upgrade). | None. |
| 4. `BudgetRequest` validates `category_id`/`account_id` ownership (`Rule::exists(...)->where('user_id', ...)`, `app/Http/Requests/BudgetRequest.php:33-40`), `transaction_type` restricted to `withdrawal`/`deposit`, `amount` `gt:0`, and the recurrence-rule shape via `ValidatesRecurrenceRule`. | Server (validation layer) | **This is the real ownership gate for category/account.** Deny case: 422 if `category_id` belongs to another user, or `account_id` references another user's account or a payee (the rule requires `config_type = 'account'`). | None. |
| 5. `BudgetService::store()` — `$user->budgets()->create($data)`. | Server | `user_id` is set from the `$user->budgets()` relation, not from request data (`user_id` is not in `Budget::$fillable`). | Row inserted. `Budget::booted()`'s `creating` hook computes `active` synchronously (`app/Models/Budget.php:105-107`) via `RecurrenceRuleService::hasOccurrenceOnOrAfter()`. |
| 6. `BudgetService::recalculateAccountBalanceBudget()` dispatches `CalculateAccountMonthlySummary`. | Server (queue) | — | Queued job (`account_balance-budget` task) recalculates the account-specific bucket (or the account-agnostic bucket if `account_id` is null) in `account_monthly_summaries`. |
| 7. `201` response with the created `Budget` (category/account not eager-loaded here — `index`/`getItem` load them, `store`/`update` don't). | Server → browser | — | Vue re-renders the merged schedules+budgets DataTable via its own reload. |

## 2. Edit a Standalone Budget

- **Actor:** the owning user.
- **Precondition:** `budget` id exists; caller owns it.
- **Success outcome:** the row is updated; both the old and new account-balance forecast buckets are recalculated if `account_id` changed.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `PATCH /api/v1/budgets/{budget}` | Browser → server | `auth:sanctum` + `verified` + `abilities:write`. | None yet. |
| 2. `#[Authorize('update', 'budget')]` attribute on `update()` (`app/Http/Controllers/API/BudgetApiController.php:87`) | Server | `BudgetPolicy::update()` → `isOwnItem()` (`app/Policies/BudgetPolicy.php:48-51`), a plain `$user->id === $budget->user_id` check. **Deny case: 403**, not 404 — Laravel's implicit route-model binding resolves `$budget` (no user-scoping global scope on the `Budget` model) before the policy runs, so a guessed id for another user's budget confirms existence via a 403 rather than reporting 404. | None on deny. |
| 3. `BudgetRequest` validates as in flow 1 (full replace-style validation, not partial). | Server | Same ownership checks on `category_id`/`account_id`. | None. |
| 4. `BudgetService::update()` — `$budget->fill($data); $budget->save();` | Server | `updating` hook recomputes `active`. | Row updated. |
| 5. Dual recalculation: the *original* `account_id`'s bucket is always recalculated; if `account_id` changed, the *new* one is recalculated too (`BudgetService::update()`, `app/Services/BudgetService.php:34-39`). | Server (queue) | — | Up to two `CalculateAccountMonthlySummary` jobs queued, so neither the vacated bucket nor the newly-fed one goes stale. |

## 3. Delete a Standalone Budget

- **Actor:** the owning user.
- **Success outcome:** row hard-deleted; its forecast bucket recalculated (so the deleted budget's contribution is removed from `account_monthly_summaries`, not just left stale).

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `DELETE /api/v1/budgets/{budget}` | Browser → server | `auth:sanctum` + `verified` + `abilities:write`. | None yet. |
| 2. `#[Authorize('delete', 'budget')]` attribute on `destroy()` (`app/Http/Controllers/API/BudgetApiController.php:98`) | Server | `BudgetPolicy::delete()` → `isOwnItem()`. Deny case: 403 (same existence-confirming shape as flow 2). | None on deny. |
| 3. `BudgetService::delete()` | Server | — | `$budget->delete()` (hard delete — `Budget` has no `SoftDeletes` trait). On success, `recalculateAccountBalanceBudget()` is dispatched with the *pre-delete* `account_id`, captured before the row is removed (`app/Services/BudgetService.php:48-55`), queuing a recalculation job. A DB-level failure is caught and reported (`report($e)`), returning a 422 with an error message rather than a 500. |

## 4. Active-Flag Recalculation (Daily Scheduled Job)

- **Actor:** the system (cron), no HTTP request.
- **Precondition:** none — runs unconditionally.
- **Success outcome:** every `TransactionSchedule.active` and every `Budget.active` reflects the current date.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `routes/console.php` — `Schedule::command(CalculateTransactionScheduleActiveFlags::class)->dailyAt('00:00')`. | System cron | N/A — no request context, no auth. | Command runs. |
| 2. `CalculateTransactionScheduleActiveFlags::handle()` (`app/Console/Commands/CalculateTransactionScheduleActiveFlags.php:30-45`) — `Transaction::with('transactionSchedule')->isSchedule()->lazy()` dispatches `CalculateTransactionScheduleActiveFlag` per row; `Budget::lazy()->each(...)` dispatches `CalculateBudgetActiveFlag` per row. Both use `lazy()` (chunked cursor iteration) rather than loading every row into memory at once. | System | N/A — operates across **all users'** rows; there is no per-user scoping at this layer (correct, since it's a system-wide maintenance job, not a user-triggered action). | One job queued per active-schedule transaction and per `Budget` row in the whole database. |
| 3. `CalculateBudgetActiveFlag::handle()` (`app/Jobs/CalculateBudgetActiveFlag.php:32-36`) — `$this->budget->active = $this->budget->isActive(); $this->budget->saveQuietly();` | System (queue worker) | — | `saveQuietly()` persists without re-firing model events (so the `updating` hook that would otherwise recompute `active` again doesn't double-run) and without dispatching `CalculateAccountMonthlySummary` — this job only refreshes the flag, it does not itself trigger a forecast recalculation. |

## 5. Budget-vs-Actual Chart Computation

- **Actor:** the owning user, viewing the budget chart report (`resources/views/reports/budgetchart.blade.php`, `resources/js/reports/budgetchart.js`).
- **Precondition:** user is authenticated.
- **Success outcome:** per-category, per-period `actual`/`budget`/`forecast` series, each `budget`/`forecast` period annotated with its contributing rows (FR-7 breakdown).

| Step | Boundary crossed | Authz check | Side effect (none — read-only) |
|---|---|---|---|
| 1. `GET /api/v1/reports/budget-chart?...` | Browser → server | `auth:sanctum` + `verified` + `abilities:read` (`ReportApiController` is entirely `GET`, so `read`-gated throughout). | — |
| 2. `ReportApiController::budgetChart()` builds three sources, each independently scoped to `$request->user()->id`: (a) real historical `TransactionItem`s (`schedule=false`) for `actual`; (b) `Transaction::byType('standard')->isSchedule()->whereHas('transactionSchedule', active=true)` for the schedule-derived `forecast`/budget contribution; (c) `Budget::where('active', true)->whereIn('category_id', ...)` for the standalone contribution (`app/Http/Controllers/API/ReportApiController.php:56-310`). | Server | User-scoping is inline (`where('user_id', ...)`) on every query — no `Gate::authorize()` call per row, since this is an aggregate report, not a single-resource fetch. Trusts the same `$categories` collection (already filtered to the user's own category tree by `CategoryService::getChildCategories()`) for all three sources. | — |
| 3. Schedule-derived items and `Budget` rows are summed **separately** into `forecastCompact`/`budgetCompact`, each inflation-adjusted (`InflationCalculator::applyAnnualRate()`) per occurrence, then merged into `dataByPeriod[period]['forecast']`/`['budget']`. | Server | Data-integrity note: a scheduled transaction's own items are counted **only once** (via the `forecast` series) — never re-summed via the `budget` bucket, since `Budget` rows are a structurally separate table with no link back to any `Transaction`. Multiple `Budget` rows for the same category (e.g. account-scoped + account-agnostic) **are** summed together — intentional, per `background.md`. | — |
| 4. Response includes `budgetBreakdown`/`scheduleBreakdown` per period — each contributing row's amount, category, and (for `Budget`) `account_id`/`account_name`. | Server → browser | — | Satisfies FR-7: the total's composition is inspectable, not opaque. |

## 6. Enter a Due Scheduled Instance (Optionally Catching Up a Missed Schedule)

- **Actor:** the owning user, from the account/schedule view.
- **Precondition:** the source transaction is a real schedule (`schedule=true`) owned by the caller.
- **Success outcome:** a real historical transaction is recorded at the schedule's base (non-inflation-adjusted) amount; the source schedule's `next_date` advances by one occurrence, or — if `catch_up_schedule` was requested — repeatedly until on/after today.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. User opens the "enter instance" form for a due schedule, optionally checking "catch up to today" if the schedule has fallen behind. | Browser | None yet. | None. |
| 2. `POST /api/v1/transactions/standard` (or `/investment`) `{ action: 'enter', id: <source transaction id>, catch_up_schedule?, ... }` | Browser → server | `auth:sanctum` + `verified` + `abilities:write`. | None yet. |
| 3. `TransactionApiController::handleSourceTransactionUpdates()` — `Transaction::where('id', ...)->where('user_id', $user->id)->firstOrFail()` then `Gate::authorize('update', $sourceTransaction)` (`app/Http/Controllers/API/TransactionApiController.php:835-841`). | Server | Double-scoped: the query itself is `user_id`-filtered (a cross-user id 404s via `firstOrFail()`, not 403 — different shape from the `Budget` policy flows above) *and* `TransactionPolicy::update()` runs on top. | None on deny. |
| 4. If `catch_up_schedule`: `TransactionSchedule::catchUpToDate()` (`app/Models/TransactionSchedule.php:168-186`) repeatedly calls `getNextInstance()` until `next_date` is on/after today, capped at 10,000 iterations as a defensive guard against a pathological rule. Otherwise: `skipNextInstance()` (single-step, unchanged from before this redesign). | Server | Deny case: catch-up hitting the iteration cap returns `false` → controller throws `RuntimeException`, no partial state persisted (the loop only calls `save()` once, at the end, after the `while` completes or is aborted). | `transaction_schedules.next_date` updated (single `save()` — the `active` flag recomputes once from the *final* `next_date`, not once per skipped occurrence). |
| 5. `TransactionUpdated` event fired for the source transaction with the original schedule config as metadata. | Server | — | Downstream listeners (`ProcessTransactionUpdated`) may trigger forecast recalculation for the affected account, same as any other schedule edit. |
| 6. The new real transaction itself is created via the normal `storeStandard`/`storeInvestment` path (not shown here — unchanged, Non-Goals). | Server | Same `abilities:write` + ownership checks as any transaction create. | Real `transactions` row inserted, dated at the schedule's `next_date` **before** it was advanced — the amount is the schedule's plain base amount, never inflation-adjusted (per `schedules.md` "Where Inflation Applies"). |

## 7. Automatic Recording of Due Scheduled Transactions (Daily Cron)

- **Actor:** the system (cron), no HTTP request.
- **Precondition:** a schedule has `automatic_recording = true` and `next_date <= today`.
- **Success outcome:** a real transaction is recorded for every such schedule, without user interaction.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `routes/console.php` — `Schedule::command(RecordScheduledTransactions::class)->dailyAt('00:05')`. | System cron | N/A. | Command runs. |
| 2. `RecordScheduledTransactions::handle()` (`app/Console/Commands/RecordScheduledTransactions.php:29-49`) — `Transaction::isSchedule()->whereHas('transactionSchedule', next_date<=today AND automatic_recording=true)->get()->each(...)` dispatches `RecordScheduledTransaction` per row. | System | N/A — system-wide, all users, by design (not a user-triggered action). | One job queued per due, auto-recording schedule across the whole database. |
| 3. `RecordScheduledTransaction` job (unchanged by this redesign) materializes the real transaction and advances `next_date`. | System (queue worker) | — | Real transaction inserted; downstream forecast/budget-chart recalculation triggered the same as any transaction write. |

## 8. One-Time Data Migration: Legacy Budget-Only Transactions → `Budget` Rows

- **Actor:** the deploying operator, via `php artisan migrate` (not a user-facing flow).
- **Precondition:** the pre-migration check (formerly `app:check:budget-migration`, now removed — see `architecture.md`) has been run and any flagged issues resolved in a prior 3.x release; a database backup exists (`UPGRADE.md`).
- **Success outcome:** every `schedule=false, budget=true` transaction (and the narrow `schedule=true, budget=true, no-account` hybrid) becomes one `Budget` row per distinct category, and the source transaction is hard-deleted.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `2026_08_05_000002_transform_budget_transactions_to_budgets.php::up()` calls `guardAgainstUnsafeData()` **before any DDL** (`database/migrations/2026_08_05_000002_transform_budget_transactions_to_budgets.php:31,173-223`). | Server (migration runner) | Re-checks all four risk cases inline (zero-item, payee-attributed, stray transfer/investment, currency mismatch). Deny case: throws `RuntimeException`, migration aborts, **no DDL has run yet** — MySQL DDL auto-commits, so this ordering is load-bearing, not cosmetic. | None on deny. |
| 2. `budgetOnlyQuery()->get()->each(convertTransaction(...))`. | Server | Operates across all users' data — this is a system migration, not scoped to a single caller. | Per transaction: one `Budget` row per distinct category among its items (summing same-category item amounts), `account_id` populated only if the non-null side of `account_from_id`/`account_to_id` is a real account (`isAccount()`, never a payee) — otherwise left null. `user_id` set directly (bypassing `$fillable`, since there's no authenticated user in a console context) at `..._000002...php:99`. |
| 3. `$config->delete(); $transaction->delete();` | Server | — | **Hard delete** of the source transaction and its polymorphic config — not soft-deleted, per spec (§7.2, acceptance criterion 7). |
| 4. The following migration (`..._000003_drop_budget_column_and_enforce_account_not_null.php`) re-verifies zero remaining null-account rows, then drops `transactions.budget` and tightens `transaction_details_standard`'s account columns to `NOT NULL`. | Server | Its own guard (`remainingNulls` count, lines 23-37) also runs before any DDL in that migration. Deny case: throws, aborts before touching schema. | Schema changes: index drop/recreate, column drop, `NOT NULL` constraint added. |
