# Permissions: Budget/Schedule Redesign

## Enforcement Model

No database row-level security — Laravel + MySQL, all access control enforced in application code (middleware, Policies, Form Requests, query-level ownership scoping). There is a single user role in scope: **authenticated + email-verified user**, identical to the rest of the app (see `.ai/docs/features/api-access-and-2fa/permissions.md`). There is no admin/staff role, and **no shared/household access model** — a `Budget` and a `TransactionSchedule` (via its parent `Transaction`) are both strictly single-user resources, scoped by a `user_id` foreign key with no sharing mechanism anywhere in the codebase.

Both auth modes from the API-access feature apply unchanged here: a session request (`TransientToken`, `tokenCan()` always `true`) or a bearer token (`PersonalAccessToken`, reach scoped by its `abilities` array). `BudgetApiController` is one of the ~24 domain controllers gated by the project-wide `read`/`write`/`settings` ability split (see that feature's `permissions.md`) — `index`/`getItem` require `read`, `store`/`update`/`destroy` require `write`. No `settings`-only endpoints exist on this controller.

## Resource × Operation × Layer Matrix

### `Budget`

| Operation | Route/Method | Ability (Sanctum) | Policy method | FormRequest | Controller-level check | DB-level scoping |
|---|---|---|---|---|---|---|
| List own budgets | `GET /api/v1/budgets` → `index` | `read` | `viewAny` → **always `true`** | — | Query explicitly `where('user_id', ...)` via `$user->budgets()` (`BudgetApiController.php:52`) | `budgets.user_id` FK |
| View one budget | `GET /api/v1/budgets/{budget}` → `getItem` | `read` | `view` → `isOwnItem()` (real check) | — | `Gate::authorize('view', $budget)` (`BudgetApiController.php:67`) | Route-model-bound by id only — **no** `user_id` scoping at the query level, so a cross-user id resolves the model and is rejected by the Policy (403), not by a 404 |
| Create | `POST /api/v1/budgets` → `store` | `write` | `create` → **always `true`** | `BudgetRequest` — real ownership check on `category_id`/`account_id` (`Rule::exists(...)->where('user_id', ...)`) | `Gate::authorize('create', Budget::class)` (no-op) | `user_id` auto-set from `$user->budgets()->create()`, not client-supplied (`user_id` not in `$fillable`) |
| Update | `PATCH /api/v1/budgets/{budget}` → `update` | `write` | `update` → `isOwnItem()` (real check) | `BudgetRequest` — same ownership check, re-validated on every update | `Gate::authorize('update', $budget)` (`BudgetApiController.php:99`) | Same route-binding-then-403 shape as `view` |
| Delete | `DELETE /api/v1/budgets/{budget}` → `destroy` | `write` | `delete` → `isOwnItem()` (real check) | — | `Gate::authorize('delete', $budget)` (`BudgetApiController.php:116`) | Same shape |
| `active` flag | N/A (never client-writable) | — | — | Not in `BudgetRequest`'s validated fields at all | — | `Budget::booted()` hooks + `CalculateBudgetActiveFlag` job — no HTTP path sets it |

**The load-bearing ownership check is split across two layers, not one:**
- **Which `Budget` row you may act on** (view/update/delete an *existing* row) is enforced by `BudgetPolicy::isOwnItem()` — a real check.
- **Which `category_id`/`account_id` you may *attach* to a `Budget`** (on create and update) is enforced entirely by `BudgetRequest`'s validation rules, not the Policy — `BudgetPolicy::create()` returns `true` unconditionally and never inspects the request body, so if `BudgetRequest`'s `Rule::exists()` checks were ever removed or weakened, the Policy would not catch a user attaching another user's category/account to their own budget.

**Existence-confirming 403 vs. 404 (worth a security audit's attention):** `view`/`update`/`destroy` all resolve the `Budget` via plain implicit route-model binding (no user-scoping global scope on the model) before the Policy runs. A request for another user's budget id therefore returns **403** (proving the row exists) rather than **404** (as the sibling API-access feature deliberately chose for personal-access-token ids, specifically to avoid confirming existence — see that feature's `flows.md` #2). This is the same shape `TransactionPolicy`/`TransactionApiController` already use elsewhere in the app (id existence is not treated as sensitive for financial-record ids), so it's consistent with the codebase's existing convention, not a new inconsistency introduced by this feature — but it is a real, checkable behavior difference from the token-management flow.

### `TransactionSchedule` (via its parent `Transaction`)

Unchanged by this redesign except for the new `by_day`/`by_month`/`catch_up_schedule` fields riding through the existing path. There is no dedicated `TransactionSchedulePolicy` — a schedule is edited exclusively as an embedded `schedule_config` on its parent `Transaction`, so `TransactionPolicy` (`app/Policies/TransactionPolicy.php`) is the sole authorization surface, identical in shape to `BudgetPolicy` (`isOwnItem()` = `$user->id === $transaction->user_id`, `viewAny`/`create` always `true`).

| Operation | Enforcement |
|---|---|
| Create/edit a schedule (via `storeStandard`/`updateStandard`/`storeInvestment`/`updateInvestment`) | `TransactionApiController`'s `abilities:write` middleware + `TransactionRequest`'s `$ownedCategoryRule`/`$ownedAccountRule`/`$ownedPayeeRule`/`$ownedInvestmentRule` (same pattern as `BudgetRequest`) + `TransactionPolicy` where explicitly invoked |
| Enter a due instance / catch up a missed schedule | `TransactionApiController::handleSourceTransactionUpdates()` — **double-scoped**: the source transaction is fetched via `->where('user_id', $user->id)->firstOrFail()` (404 on cross-user id) *and* `Gate::authorize('update', $sourceTransaction)` runs on top (`app/Http/Controllers/API/TransactionApiController.php:835-841`) — belt-and-suspenders, unlike `Budget`'s single-layer (Policy-only) check |
| `next_date` must be a genuine rule occurrence | `TransactionRequest::nextDateOccursOnRule()` (`app/Http/Requests/TransactionRequest.php:64-99`) — data-integrity validation, not an authz check, but prevents a client from persisting a `next_date` that doesn't match the configured recurrence (which `TransactionSchedule::occursOn()`/`RecurrenceRuleService::occursOn()` both trust verbatim once persisted) |

## Full Ability Enforcement Cross-Reference

`BudgetApiController` was added to the project-wide `abilities:*` mapping documented in `.ai/docs/features/api-access-and-2fa/permissions.md` § "Full Ability Enforcement — Implementation Plan": `read` for `index`/`getItem`, `write` for `store`/`update`/`destroy`. Pinned by `tests/Feature/API/ApiAbilityEnforcementTest.php` (`budgets.index` → `read`, `budgets.store` → `write`, confirmed present in that test's data provider).

## What's Deliberately Unclear / Not Fully Verified

- **Whether every `Budget`-reading endpoint's implicit route-model binding is consistent about 403-vs-404 across the whole controller** was checked directly (`getItem`/`update`/`destroy` all confirmed 403-shaped via Policy-after-binding) but not exhaustively fuzzed against malformed/negative ids — standard Laravel binding behavior is assumed, not independently re-verified here.
- **`ReportApiController::budgetChart()` and `TransactionApiController::getScheduledItems()` never call `Gate::authorize()` on individual `Budget`/schedule rows** — both rely entirely on inline `where('user_id', $request->user()->id)` query scoping. This is consistent with how the rest of `ReportApiController` already works (aggregate reports don't policy-check every contributing row), but it does mean `BudgetPolicy` is bypassed entirely for these two read paths — worth confirming a security audit is comfortable treating query-level scoping as equivalent to Policy enforcement here, since that is a explicit statement, not a hedge: no code path was found where these two endpoints could leak another user's row, but the *mechanism* differs from the single-resource endpoints.
