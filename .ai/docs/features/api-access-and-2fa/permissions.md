# Permissions: API Access & Two-Factor Authentication

## Enforcement Model

No database row-level security — Laravel + MySQL, all access control enforced in application code (middleware, Form Requests, query-level ownership scoping). MySQL grants are undifferentiated; the app's DB user can read/write any row.

There is a single user role in scope: **authenticated + email-verified user**. Every endpoint in this feature requires `auth:sanctum` + `verified`. There is no admin/staff role anywhere in this feature.

What's new here is a **second authentication mode** on top of the existing single role: a request can now be authenticated either as a **session** (`TransientToken`, full reach, `tokenCan()` always `true`) or as a **bearer token** (`PersonalAccessToken`, reach nominally scoped by `abilities`, ownership still `user_id`-scoped underneath). Both modes resolve to the same `User` and the same role — the distinction that matters for this feature is *which authentication mode may call which endpoint*, not a role hierarchy.

## Authentication-Mode × Endpoint Matrix

| Endpoint | Session request | Bearer-token request | Enforced via |
|---|---|---|---|
| `GET/POST/DELETE /api/v1/users/me/tokens*` (token management) | Allowed | **Denied (403)**, regardless of the token's abilities | `ApiTokenApiController::middleware()` closure — `abort(403)` if `currentAccessToken() instanceof PersonalAccessToken`. This is the one place in the app that discriminates on auth mode itself, not on abilities. Rationale: a bearer token, however narrowly scoped, must never be able to mint itself a broader-access replacement token. |
| `GET /api/v1/users/me/two-factor` (`show`, read-only) | Allowed | **Allowed** — every token has baseline `read` | Only `auth:sanctum` + `verified`; no ability check needed since `read` is unconditional on every token. |
| `POST /api/v1/users/me/two-factor/{enroll,confirm,disable,recovery-codes}` (4 mutating actions) | Allowed | **Denied (403)** unless the token was granted the `settings` ability | `TwoFactorApiController::middleware()` applies Sanctum's `abilities:settings` to these four actions — a no-op for session requests (`TransientToken::can()` always `true`). `disable`/`regenerateRecoveryCodes` additionally require `current_password`; `enroll`/`confirm` additionally reject the request outright (422) if 2FA is already enabled, closing a prior bug where either endpoint could be called on an already-enabled account without a password (see "Hardening" below). |
| All other `/api/v1/*` endpoints (accounts, transactions, categories, payees, tags, investments, reports, imports, config, settings — ~24 controllers) | Allowed, full reach | **Denied without the matching ability.** | **Status: DONE.** See "Full Ability Enforcement — Implementation Plan" below for the per-controller mapping (the code pattern, first proven on `TwoFactorApiController`, was mechanically repeated across all remaining controllers) and `tests/Feature/API/ApiAbilityEnforcementTest.php` for the pinning tests. |

## Full Ability Enforcement — Implementation Plan (MVP requirement — implemented)

**Status: done.** The ability picker shipped, abilities are validated and stored, and the mechanism proven correct and low-risk on `TwoFactorApiController` (see `architecture.md` Trust Boundaries) has been mechanically repeated across the other ~24 `API` controllers and is now pinned by `tests/Feature/API/ApiAbilityEnforcementTest.php`.

### Why this was required, not deferred

The original design (see SPECIFICATION.md history) treated this as a "Phase 2, separate PR" follow-up, accepted as a disclosed MVP gap. That framing was **retracted**: shipping a token-scoping UI that visibly lets a user pick `read`-only vs `write` vs `settings`, while every endpoint except two silently ignored that choice, was a materially misleading security control — a user who deliberately minted a `read`-only token for a reporting script had no way to know it was actually a full-access credential. This section (and the mapping below) remains the authoritative reference for exactly which ability gates which action.

### The mechanism (already proven, just needs repeating)

**Updated for `release/v4`:** this section originally described a `HasMiddleware`-interface/static-`middleware()`-method pattern. The Laravel 13 upgrade (landed on this branch after this feature and the budget/schedule redesign were first built) replaced that across the codebase with PHP 8 class/method attributes — `Illuminate\Routing\Attributes\Controllers\Middleware` and `...\Authorize`. Verified current: 26 of the 27 controllers in `app/Http/Controllers/API/` now use the attribute form; only `ApiTokenApiController` still implements `HasMiddleware`/`middleware()`, because its auth-mode check is a closure (an `abort(403)` if the resolved token is a `PersonalAccessToken`), and the attribute form only accepts a named middleware string — it can't express an inline closure. `TwoFactorApiController` (the controller this section originally used as the worked example) now looks like this:

```php
#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:settings', only: [
    'enroll', 'confirm', 'disable', 'regenerateRecoveryCodes',
])]
class TwoFactorApiController extends Controller
{
    // ...
}
```

`abilities:<name>` still resolves to Sanctum's `CheckAbilities` middleware (aliased in `bootstrap/app.php` as `abilities`/`ability`) exactly as before — it calls `$request->user()->tokenCan($ability)`, a no-op `true` for session requests (`TransientToken::can()` always returns `true`) and a real check against the `abilities` column for bearer tokens. `only`/`except` still scope the check to specific controller methods, so a single controller can require different abilities for different actions — only the declaration syntax (class attribute instead of an array entry inside a static method) changed. Likewise, the `Gate::authorize(...)` calls this doc originally showed inline inside controller methods are now `#[Authorize('ability', Model::class|'routeParam')]` method attributes (e.g. `BudgetApiController::store()` carries `#[Authorize('create', Budget::class)]` rather than calling `Gate::authorize()` in its body) — see `.ai/docs/features/budget-schedule-redesign/permissions.md` for a worked example with exact line numbers.

**The work per controller was: add one or more `Middleware(...)` attributes above the class, scoped by method name via `only`/`except`.** No route changes, no new middleware classes, no changes to session-request behavior (verified: session requests pass every `abilities:*` check trivially). This mechanism note is about *how* the gate is declared, not *whether* it fires — the actual ability-to-controller mapping in the table below was independently re-checked against the current code and still matches.

### The ability-to-endpoint mapping

Two rules, applied per controller method (derive exact method names from `routes/api.php`, cross-referenced against each controller's own method list):

1. **Every `GET`-method action → `abilities:read`** — *except* the four "config/credential" controllers listed below, whose `GET` actions require `abilities:settings` instead (they expose configuration/credential metadata, not financial data — no legitimate use case wants "read-only access to my Google Drive OAuth config" as a separate tier from "can change my Google Drive OAuth config").
2. **Every non-`GET` action → `abilities:write`** — *except* actions on the config/credential controllers and the two cross-controller "maintenance" routes, which require `abilities:settings` instead.

This works because `read` is unconditionally present on every issued token (`ApiTokenRequest::prepareForValidation()`), so a `write`- or `settings`-scoped token always also holds `read` — single-ability gates are sufficient, no `CheckForAnyAbility`/`ability:` combinations needed anywhere in this rollout.

#### Controllers gated entirely by `settings` (all actions, including `GET`)

| Controller | Why |
|---|---|
| `AiProviderConfigApiController` | Holds AI provider API keys |
| `AiUserSettingsApiController` | Account-level AI preferences |
| `GoogleDriveConfigApiController` | Holds Google OAuth credentials |
| `InvestmentProviderConfigApiController` | Holds investment-price-provider API keys |
| `UserApiController` | `changePassword`, `updateSettings`, `getPreference`, `setPreference` — account credentials/config, not financial data |

#### Cross-controller "maintenance" route overrides (→ `settings`, regardless of the controller's default)

These two actions live on otherwise-`write`-default domain controllers but are operator-level operations with no natural per-record equivalent — override them individually via a third `Middleware::class` entry `only: [...]` on that one method, don't reclassify the whole controller:

| Route name | Controller::method | Default (controller) | Override |
|---|---|---|---|
| `maintenance.clear-currency-cache` | `CurrencyRateApiController::clearCache` | `write` | `settings` |
| `maintenance.cleanup-ai-document-old-files` | `AiDocumentApiController::cleanupOldFiles` | `write` | `settings` |

`AccountEntityApiController::recalculateAccountMonthlySummaries` (route `maintenance.recalculate-account-monthly-summaries`) is **not** in this table — it's `write`, same as the rest of its controller. It was originally classified as a `settings` override alongside the two above, but that was reconsidered: unlike clearing a shared cache or deleting old files, recalculating monthly summaries is a side effect that already happens implicitly on every transaction create/edit (a `write`-scoped token triggers it constantly just by doing normal transaction writes). Gating the *explicit, bulk* version of the same recomputation behind `settings` while the *implicit, per-transaction* version only needs `write` was an inconsistent bar for the same underlying operation — a token that can already cause this to run everywhere via ordinary write traffic gains no meaningful extra reach by being able to trigger it directly for all of its own accounts at once. `clearCache` and `cleanupOldFiles` don't have that property (nothing about routine `write` usage implicitly clears the currency cache or deletes old AI document files), so they keep the `settings` gate.

#### All other controllers (`read`/`write` split per the two rules above)

`AccountApiController`, `AccountEntityApiController`, `AccountGroupApiController`, `AiDocumentApiController` (except the override above), `BudgetApiController` (`read`: `index`, `getItem`; `write`: `store`, `update`, `destroy` — added later, during the budget/schedule redesign, once its `BudgetApiController` existed to gate), `CategoryApiController`, `CategoryLearningApiController`, `CurrencyRateApiController` (except the override above), `FileImportProfileApiController`, `ImportApiController` (single action `parse` → `write`), `InvestmentApiController`, `InvestmentGroupApiController`, `InvestmentPriceApiController`, `InvestmentPriceProviderApiController`, `OnboardingApiController`, `PayeeApiController`, `PayeeStatsApiController`, `ReportApiController` (all-`GET`, so entirely `read`), `TagApiController`, `TransactionApiController`.

`ApiTokenApiController` and `TwoFactorApiController` are already done (session-only, and `settings`-gated, respectively) — do not touch their `middleware()` again as part of this rollout.

### Rollout order (as executed)

1. `AiProviderConfigApiController`, `GoogleDriveConfigApiController`, `InvestmentProviderConfigApiController` — third-party credential controllers, highest blast radius if a leaked token can read/rotate them.
2. `UserApiController` — `changePassword` in particular (account takeover surface).
3. The two maintenance-route overrides.
4. `AiUserSettingsApiController`.
5. Everything else — the domain controllers, in any order, since they're already ownership-scoped and the only thing changing is which ability unlocks them.

### Per-controller checklist (completed for all ~24 controllers)

- [x] Read the controller's method list and cross-reference `routes/api.php` to build the exact `only: [...]` arrays for `read`/`write` (and `settings` where applicable).
- [x] Add `use Illuminate\Routing\Controllers\Middleware;` if not already imported.
- [x] Extend the `middleware()` array with one `new Middleware('abilities:read', only: [...])` and one `new Middleware('abilities:write', only: [...])` entry (a `settings` entry instead, for the five config/credential controllers; an additional third entry for the two maintenance-route overrides).
- [x] Run `vendor/bin/sail bin pint --dirty` and `vendor/bin/sail bin phpstan analyse` on the touched files.
- [x] Run each controller's existing feature test file — a handful needed updating because they used `Sanctum::actingAs($user)` (no abilities passed, which Sanctum defaults to `[]`) as a generic "authenticate as this user" helper rather than to test ability scoping; those calls were updated to `Sanctum::actingAs($user, ['*'])` so they keep testing business logic, not the new ability gate. Session requests (`actingAs()` on the web/session guard, or the SPA in production) were unaffected either way.
- [x] Add the ability-enforcement tests: `tests/Feature/API/ApiAbilityEnforcementTest.php` (one deny-without-ability case, one allow-with-ability case, per controller, data-provider-driven per `tests.md`).

### Non-goals for this rollout (scope was not expanded)

- Per-resource abilities (`accounts:read`, `transactions:write`, etc.) were not introduced — that model was deliberately rejected in favor of the three-tier `read`/`write`/`settings` split (see SPECIFICATION.md "Ability Model"). Only the three existing enum values are used.
- `ApiTokenAbility` itself, `ApiTokenRequest`, `ApiTokenManager.vue`, and the token-creation flow were not touched — those were already correct and complete; this rollout only added enforcement to the ~24 *other* controllers.
- Existing Policies and `$user->accounts()`-style ownership scoping were not touched — ability checks are a layer on top of, not a replacement for, existing per-record authorization (see "Scope Derivation" below). A `write`-scoped token still can't touch another user's data; that boundary is unrelated to this work.

## Ability Scope Model (`App\Enums\ApiTokenAbility`)

A closed, three-value enum: `read`, `write`, `settings`. `read` is included on every token unconditionally (the baseline — even write-only operations need to read related accounts/payees/categories/tags to reference them); `write` (all financial data) and `settings` (account/security changes) are independent, orthogonal additions on top, chosen via two checkboxes in the creation UI. No wildcard case exists in the enum.

| Rule | Enforced via |
|---|---|
| A token's `abilities` must be a non-empty subset of `ApiTokenAbility::values()` | `ApiTokenRequest` (`Rule::in(...)`, `min:1`) |
| A token can never be granted an ability the requesting credential doesn't itself hold | `ApiTokenApiController::store()` filters `$validated['abilities']` through `$user->currentAccessToken()->can($ability)` — currently a no-op for session requests (`TransientToken::can()` is always `true`), since bearer-token callers can't reach `store()` at all (see matrix above) |
| Abilities are recorded on every issued token, shown to the user, and now enforced on every `API` controller | **Done — see "Full Ability Enforcement — Implementation Plan" above.** |

## Scope Derivation

- **Session requests:** scope = the logged-in `User`; no token row involved (`TransientToken` is synthetic, not a DB row).
- **Bearer-token requests:** scope = `PersonalAccessToken::tokenable` (the `User` who created it) + the token's `abilities` JSON column. Resolved by Sanctum's guard from the `Authorization: Bearer` header; no separate lookup in this feature's own code.
- Every ownership check underneath (which transactions/accounts/etc. a request can see or modify) uses the *resolved user*, identically for both auth modes — this feature does not introduce or change any cross-user data-isolation logic.

## 2FA State (not an authz check, but access-relevant)

| Field | Meaning | Who can read/write it |
|---|---|---|
| `two_factor_confirmed_at` (via `laragear/two-factor`'s trait/table) | Whether login requires a second factor | Read: `TwoFactorApiController::show` (`hasTwoFactorEnabled()`). Write: only via `confirm`/`disable`, both requiring the caller to already be the authenticated owner (`disable` additionally requires the current password). |
| Recovery codes | One-time-use login fallback | Returned in the HTTP response body exactly once (at `confirm` and at `regenerateRecoveryCodes`), never re-servable afterward. Stored via `laragear/two-factor`'s `recovery_codes` column, cast `encrypted:collection` — reversible with `APP_KEY` (`Crypt`/AES-256-CBC), not one-way hashed (verified in `vendor/laragear/two-factor/src/Models/TwoFactorAuthentication.php`; the package matches submitted codes by plaintext comparison, which requires them to be recoverable). A combined `APP_KEY` + database compromise exposes all users' recovery codes in plaintext — an accepted upstream-package tradeoff, not something this feature's own code controls. |
| Break-glass disable | Operator-only escape hatch when a user is locked out | `php artisan app:user:disable-2fa {email}` — a server-access-only console command, not reachable via any API/UI. Logs a `Log::warning` with user id/email on every use, so it's auditable in application logs even though there's no in-app audit trail entry. |

## Hardening (fixed after initial security review)

Two bugs were found and fixed in `TwoFactorApiController` before this feature shipped, both reachable by *any* authenticated caller regardless of token ability (i.e. not gated by the `settings`-ability fix above, since both existed independently of it):

- **`confirm` no longer trusts `confirmTwoFactorAuth()`'s already-enabled short-circuit.** `laragear/two-factor`'s `confirmTwoFactorAuth()` returns `true` without checking the submitted code at all once `hasTwoFactorEnabled()` is true. The controller previously forwarded that `true` straight into a response containing the account's live recovery codes — meaning any caller could retrieve them by posting an arbitrary `code` to an already-enabled account. The controller now checks `hasTwoFactorEnabled()` itself and returns 422 before ever calling `confirmTwoFactorAuth()` in that state.
- **`enroll` no longer allows re-enrollment over an already-enabled account.** `createTwoFactorAuth()` unconditionally flushes the existing secret and clears `enabled_at`, so calling `enroll` a second time silently disabled a victim's real 2FA and handed the caller a fresh secret to confirm as their own — with no password re-entry, unlike `disable`. `enroll` now returns 422 if `hasTwoFactorEnabled()` is already true; re-enrollment requires disabling first (password-gated).

Both are now covered by regression tests in `tests.md`.

## Risks

- **Ability enforcement across the ~24 remaining controllers has shipped — no longer an open risk.** "Scoped token" is now a backend-enforced guarantee, not just a UI/UX promise: every `API` controller applies an `abilities:*` middleware gate matching `permissions.md`'s mapping. A `read`-only token is actually read-only everywhere, not only by convention on the two controllers that were enforced first.
- **Rollout narrowed already-issued tokens' effective reach with no re-issuance.** Every token created before the rollout now enforces exactly what its `abilities` column already said (which the user already selected at creation) — no new tokens needed to be minted, but a token a user was unknowingly relying on for broader access (because enforcement didn't exist yet) will now get 403s on endpoints outside its recorded abilities. This is the *intended* fix, not a regression, but it's a real behavior change on upgrade — worth a release note. See `variables.md` Pre-Go-Live Checklist.
