# API Access Hardening & Optional Two-Factor Authentication

## Feature Summary

Two related hardening initiatives, shipped together because the second reduces the blast radius of the first:

1. **Personal Access Tokens** — let a user mint, scope, and revoke a Sanctum bearer token from their own Settings page, so they can call `/api/v1/*` from outside the browser (scripts, automations, a future mobile client) with the same per-user data isolation the SPA already gets. Today this is impossible: `laravel/sanctum` is installed and `App\Models\User` already uses `HasApiTokens`, but the `personal_access_tokens` migration was never published, and no code anywhere calls `createToken()`. The API only ever authenticates via the first-party session cookie (Sanctum's "stateful SPA" mode).
2. **Optional two-factor authentication (TOTP)** — a per-user opt-in second factor on top of the existing `laravel/ui` session login, using a dedicated 2FA package rather than migrating to Laravel Fortify. Because minting an API token is done from the same session-authenticated Settings page as everything else, enabling 2FA automatically raises the cost of "attacker gets the password, then mints themselves a durable API token."

## Why This Architecture

- Sanctum already underpins the whole API layer (`auth:sanctum` on every one of the ~24 `API` controllers, `EnsureFrontendRequestsAreStateful` wired in `bootstrap/app.php`). Adding personal access tokens is completing a partially-installed feature, not introducing a new auth system.
- Sanctum's guard transparently supports both modes at once: browser requests authenticated via the stateful cookie get a `TransientToken` (`tokenCan()` always `true`), while a bearer-token request gets the real `PersonalAccessToken` row with its own `abilities`. This means ability scoping for issued tokens can be added **without touching the existing SPA's behavior at all** — no existing Vue island needs to change.
- `laravel/ui` vs `laravel/fortify` was already evaluated for this project (see prior discussion): this app has customized `LoginController` (reCAPTCHA hook, `AuthenticatesUsers` trait) and a custom `SendEmailVerificationNotification` listener. Migrating to Fortify to get 2FA "for free" is a bigger, riskier lift than bolting a dedicated TOTP package onto the existing login controller. This spec assumes that decision stands.
- Both features touch the same surface (`/user/settings`, the `User` model, session/login lifecycle), so scoping them into one spec avoids two half-consistent designs landing in sequence.

## Goals / Non-Goals

- Goals:
  - Publish and run the missing Sanctum migration; make `createToken()` actually usable.
  - Let a user create, name, scope (abilities), optionally expire, list, and revoke their own personal access tokens via a Settings UI.
  - Add per-user (not just per-IP) API rate limiting, in all environments.
  - Prune expired tokens on a schedule.
  - Remove the dead legacy `api` guard from `config/auth.php`.
  - Make the auto-generated API docs (`/docs/api`) production access explicitly configurable via a new `SCRAMBLE_PROD_AUTH` env var, instead of the current implicit all-or-nothing behavior.
  - Add optional, user-controlled TOTP 2FA on top of the existing `laravel/ui` login flow, with recovery codes.
  - Keep both features consistent with existing conventions: Services over controller logic, Form Requests for validation, Policies for authorization, `{Noun}ApiController` naming, Vue islands per page (`user/settings.js`).
- Non-Goals:
  - OAuth2 / delegated third-party authorization (Passport). No current need for "app acting on behalf of a user without sharing credentials."
  - Migrating `laravel/ui` to Fortify.
  - SMS/email-based 2FA — TOTP only, to avoid new telco/infra dependencies (consistent with the self-hosted, no-third-party-data-sharing product philosophy).
  - Forcing 2FA org-wide — this is a self-hosted, typically single-user-or-family app; making 2FA mandatory is out of scope.
  - Full per-endpoint ability enforcement across all ~24 API controllers in this iteration (see "Phased Ability Enforcement" below) — MVP ships tokens with a working, safe default and defers the fine-grained scoping refactor.
  - A public/marketplace API or published SDK. This is about a user accessing their own data, not third parties accessing it.

## Assumptions

- Laravel 12 + Sanctum 4.2, already in `composer.json`, are the versions targeted.
- Session-authenticated (cookie/stateful) requests from the SPA continue to have full access, unchanged — no ability check applies to `TransientToken` requests.
- A single deployment serves one user or a small trusted household/family group per instance (per `product-context.md`), so 2FA enrollment/recovery UX can be simple (no admin-assisted recovery flow, no org policy engine).
- The chosen 2FA package must be re-validated for active maintenance and PHP 8.4/Laravel 12 compatibility at implementation time, per this project's existing package-governance rule (see `qif-csv-import/SPECIFICATION.md` "Package governance rule" for precedent) — the package named below is a design-time recommendation, not a locked-in dependency.
- Token abilities are additive security (defense in depth) on top of, not a replacement for, existing Policies — every existing ownership/authorization check (e.g. `FileImportProfilePolicy`, `ImportPolicy`) still runs regardless of how the request authenticated.

## Backend Scope (Laravel)

- Models:
  - `User` — add 2FA package's `TwoFactorAuthenticatable`-style trait; no changes needed for tokens (already has `HasApiTokens`).
  - No new Eloquent models for tokens (Sanctum's built-in `PersonalAccessToken` covers it).

- Migrations:
  - Publish Sanctum's `create_personal_access_tokens_table` (`vendor:publish --tag=sanctum-migrations`) and run it.
  - 2FA package's migration adding `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` to `users` (exact column set depends on chosen package; note candidate packages commonly reuse these Fortify-compatible column names).

- Controllers / APIs:
  - `App\Http\Controllers\API\ApiTokenApiController` (new) — `index`, `store`, `destroy` under `/api/v1/users/me/tokens*`, following the existing `UserApiController` (`/users/me/*`) naming convention.
  - `App\Http\Controllers\Auth\TwoFactorChallengeController` (new, web) — shows the code-entry form mid-login and verifies it; mirrors the existing `App\Http\Controllers\Auth\LoginController` structure (`implements HasMiddleware`, `guest` middleware).
  - `App\Http\Controllers\API\TwoFactorApiController` (new) — `enroll` (generate secret + QR), `confirm` (verify first code, return recovery codes once), `disable` (requires password re-entry), `regenerateRecoveryCodes`.
  - `App\Http\Controllers\Auth\LoginController` (existing, modified) — intercept post-credential-check to redirect into the 2FA challenge when the user has confirmed 2FA, instead of completing login immediately.

- Services / Jobs:
  - `App\Services\ApiTokenService` — wraps `$user->createToken()`/listing/revocation, centralizing ability-list validation and expiry-cap enforcement (keeps the controller thin, per `laravel-backend.agent.md`).
  - No new job/queue work — token pruning runs via the 2FA/Sanctum packages' own Artisan commands on the existing scheduler.

- Policies / Auth:
  - No new Policy class needed for tokens — a token row is only ever resolved through `$user->tokens()`, so ownership is scoped at the query level (same pattern already used for `$user->transactions()`, `$user->payees()`, etc. — see `qif-csv-import/permissions.md` for the precedent of documenting this style of scoping explicitly).
  - New Sanctum ability aliases registered in `bootstrap/app.php`: `'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class`, `'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class` (present in the package but not currently aliased anywhere in this app).
  - `App\Enums\ApiTokenAbility` (new, mirrors the existing `App\Enums\TransactionType` convention) — closed enum of grantable abilities.
  - `Gate::define('viewApiDocs', ...)` (new, added to `AppServiceProvider::bootEvent()` next to the existing `import.parse` gate) — backs the new `SCRAMBLE_PROD_AUTH` env var (see "Production Docs Access Control" below).

- Events / Notifications:
  - Optional: notify the user by email when a new API token is created or a 2FA method is disabled (defense against silent account compromise). Not required for MVP; flagged as a follow-up in "Risks / Open Questions."

## Frontend Scope (Vue + Bootstrap)

- Pages / Routes:
  - No new page routes. Both features are new sections on the existing `/user/settings` page (`resources/views/user/settings.blade.php`, entry `resources/js/user/settings.js`), consistent with how `MyProfile.vue`, `ChangePassword.vue` etc. are already mounted there as independent islands.
  - Login flow gains one new intermediate screen: a 2FA code-entry view, rendered from a new Blade view (e.g. `resources/views/auth/two-factor-challenge.blade.php`) reachable only mid-login (`guest`-gated, same as `LoginController`).

- Components:
  - `ApiTokenManager.vue` (new) — token list (name, ability badges, last used, expires, revoke button) + "Create Token" modal (name, ability checkboxes, optional expiry).
  - `TwoFactorSettings.vue` (new) — enable/disable toggle, QR code + confirmation code input during enrollment, recovery codes display (one-time reveal, "I've saved these" confirmation), regenerate-codes action, disable action (password re-entry).
  - Login-flow: a small non-SPA Blade form (this is a pre-authentication page, not a Vue island — consistent with `laravel/ui`'s existing plain-Blade login/register pages) for the 2FA code / recovery-code entry.

- State management:
  - Page-local state only, same as the rest of the settings page — no cross-island shared store.

- API interactions:
  - `ApiTokenManager.vue` → `GET/POST/DELETE /api/v1/users/me/tokens*`.
  - `TwoFactorSettings.vue` → `POST /api/v1/users/me/two-factor/enroll`, `POST /api/v1/users/me/two-factor/confirm`, `POST /api/v1/users/me/two-factor/disable`, `POST /api/v1/users/me/two-factor/recovery-codes`.

- UX / validation rules:
  - The plaintext token is shown exactly once, immediately after creation, with a copy-to-clipboard button and an explicit "you will not be able to see this again" warning — never re-displayed, never logged.
  - Recovery codes follow the same one-time-reveal pattern.
  - Ability selection defaults to a "Read-only" preset and a "Full access" preset, with an "Advanced" expand for per-resource-group checkboxes, to avoid overwhelming a non-technical self-hosting user with a raw ability list.
  - Token/2FA-disable actions that reduce security (revoke, disable 2FA) get a confirmation dialog; 2FA disable additionally requires re-entering the current password server-side (not just a client-side confirm).

## Data & API Design

- Entities:
  - `PersonalAccessToken` (Sanctum built-in, `personal_access_tokens` table) — `tokenable` (User), `name`, `abilities` (JSON), `expires_at`, `last_used_at`.
  - `User` additions (from 2FA package): `two_factor_secret` (encrypted), `two_factor_recovery_codes` (encrypted JSON array of hashed codes), `two_factor_confirmed_at`.

- Relationships:
  - `User hasMany PersonalAccessToken` via Sanctum's existing `morphMany` (`HasApiTokens::tokens()`), already present.

- Endpoints (method + path):
  - `GET /api/v1/users/me/tokens` — list current user's tokens (never includes plaintext).
  - `POST /api/v1/users/me/tokens` — create a token; request `{ name, abilities[], expires_at? }`; response includes plaintext `token` once.
  - `DELETE /api/v1/users/me/tokens/{id}` — revoke; scoped to `$user->tokens()`, 404 (not 403) if the id doesn't belong to the caller, to avoid confirming other users' token ids exist.
  - `POST /api/v1/users/me/two-factor/enroll` — generates and stores an unconfirmed secret, returns QR/otpauth URI.
  - `POST /api/v1/users/me/two-factor/confirm` — `{ code }`; on success sets `two_factor_confirmed_at`, returns recovery codes once.
  - `POST /api/v1/users/me/two-factor/disable` — `{ password }`.
  - `POST /api/v1/users/me/two-factor/recovery-codes` — `{ password }`; regenerates and returns a fresh set once.
  - `GET /login/two-factor-challenge` / `POST /login/two-factor-challenge` (web, not `/api/v1`) — the mid-login code entry step.

- Payloads (high level):
  - Token creation response: `{ id, name, abilities: string[], expires_at: string|null, token: "<plaintext, shown once>" }`.
  - Token list item: `{ id, name, abilities, last_used_at, expires_at, created_at }` — no `token` field.

## Part A: Personal Access Tokens — Deep Dive

### Ability Model

`App\Enums\ApiTokenAbility` defines a closed, resource-grouped whitelist mirroring the controller groups in `routes/api.php`, e.g.:

```
accounts:read, accounts:write,
transactions:read, transactions:write,
investments:read, investments:write,
categories:read, categories:write,
payees:read, payees:write,
tags:read, tags:write,
reports:read,
imports:write,
settings:write,
```

`ApiTokenRequest` validates `abilities` against `ApiTokenAbility::values()`. `ApiTokenService::create()` rejects an empty ability list (a token must be scoped to *something* — no implicit `['*']` from the UI; `['*']` is only what a "Full access" preset expands to client-side before submission).

### Phased Ability Enforcement

Adding `abilities:` middleware to every action of all ~24 `API` controllers in one pass is a large, mechanical diff with real regression risk (each controller currently declares `middleware()` once for the whole class; per-action ability gating needs `Illuminate\Routing\Controllers\Middleware::class->only([...])`, touched file-by-file).

- **MVP (this iteration):** ship token issuance, listing, and revocation. Issued tokens carry real `abilities` (validated, stored, visible to the user) but **no controller yet enforces them** — functionally equivalent to a full-access token today, same effective reach as a logged-in session, scoped only by existing Policies/`user_id` ownership. This still delivers the core goal (external, revocable, nameable API access) safely, because ownership scoping is unaffected by how the request authenticated.
- **Follow-up (separate PR, tracked but out of scope here):** add `abilities:` middleware per action, prioritized by risk — start with the highest-blast-radius controllers: `GoogleDriveConfigApiController`, `AiProviderConfigApiController` (both handle third-party secrets), the `maintenance.*` routes, and `UserApiController::changePassword`. Read-list endpoints (`accounts.index`, `categories.index`, etc.) are lower priority since they're already ownership-scoped.
- The UI ships the full ability picker from day one regardless, so users choosing "Read-only" today already get the *intended* restriction once enforcement lands — no re-issuance needed later.

### Token Lifecycle

- **Expiration:** `config('sanctum.expiration')` stays `null` (global default, unaffected); per-token expiry is passed explicitly as `createToken()`'s third argument. `ApiTokenRequest` caps `expires_at` at a configurable max span (new `config('yaffa.api_token_max_lifetime_days')`, default 365) — "never expires" is not offered as a UI option to avoid silently-immortal credentials.
- **Pruning:** `Schedule::command('sanctum:prune-expired', ['--hours' => 24])->daily()` added to `routes/console.php`, inside the existing `if (config('yaffa.runs_scheduler'))` block alongside the other scheduled commands.
- **Revocation:** hard delete (`$user->tokens()->where('id', $id)->delete()`), not soft-delete/flag — consistent with Sanctum's model (a deleted `PersonalAccessToken` row simply stops authenticating on its next use).

### Rate Limiting

Replace the current production-only, IP-only throttle (`AppServiceProvider::register()`, `ThrottleRequests::class . ':60:1'` hardcoded into the `api` middleware group) with a named limiter, active in all environments:

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
});
```

registered in `AppServiceProvider::boot()` next to the existing `investment-price-provider` limiter (same pattern already in the codebase), applied via `$middleware->api(append: ['throttle:api'])` in `bootstrap/app.php` (currently only `SetLocale` is appended there). Per-user keying means one user's automation hammering the API doesn't throttle other users on a shared instance, and a stolen-but-still-valid session cookie is rate-limited the same as a token. Expensive endpoints (`imports/parse`, `imports/file-profiles/suggest`) keep their own tighter limiter, matching the risk already called out in `qif-csv-import/architecture.md`'s "Known Risks" section.

### Config Cleanup

Remove the unused `'api' => ['driver' => 'token', 'provider' => 'users', 'hash' => false]` guard from `config/auth.php` — grep confirms no route or middleware references `auth:api`; it's a pre-Sanctum leftover and its presence invites a future contributor to wire something into it by mistake.

### API Documentation

Publish `config/scramble.php` (currently absent, using package defaults) and declare a Sanctum bearer-token security scheme so the auto-generated OpenAPI docs (dedoc/scramble) accurately describe how to call the API with a personal access token — this is the reference material a user creating an automation against their own instance would actually read.

### Production Docs Access Control (`SCRAMBLE_PROD_AUTH`)

**Current behavior:** Scramble's shipped `RestrictedDocsAccess` middleware (`config/scramble.php`'s `middleware` array, applied to `/docs/api`) short-circuits to fully open access whenever `app()->environment('local')`, and otherwise checks `Gate::allows('viewApiDocs')`. This app never defines a `viewApiDocs` gate anywhere, and an undefined Gate ability resolves to `false` — so today, in every non-local environment (including production), `/docs/api` is a hard 403 with no way to turn it on short of editing the vendor middleware or defining the gate by hand. This is effectively today's "none" behavior, just undocumented and unconfigurable.

**New env var:** `SCRAMBLE_PROD_AUTH`, read via a new `config('yaffa.scramble_prod_auth')` entry in `config/yaffa.php` (`env('SCRAMBLE_PROD_AUTH', 'none')`, following the existing `env_verification_required`/`runs_scheduler` pattern already in that file). Three accepted values:

| Value | Effect on `/docs/api` in non-local environments |
|---|---|
| `none` (default) | Always 403 — matches today's de facto behavior, now explicit and documented. |
| `user` | Allowed only for an authenticated **and email-verified** user (`$user !== null && $user->hasVerifiedEmail()`) — same bar as the rest of the app's `verified` middleware. |
| `guest` | Allowed for anyone, authenticated or not. |

**Implementation:** define the gate in `AppServiceProvider::bootEvent()` (alongside the existing `Gate::define('import.parse', ...)`):

```php
Gate::define('viewApiDocs', function (?User $user) {
    return match (config('yaffa.scramble_prod_auth', 'none')) {
        'guest' => true,
        'user' => $user !== null && $user->hasVerifiedEmail(),
        default => false,
    };
});
```

A nullable-typed first parameter is required for the closure to run at all for unauthenticated requests — Laravel's Gate only invokes ability callbacks for guests when the resolved parameter type allows `null`; otherwise an unauthenticated request short-circuits to `false` before the closure runs, which would make `guest` mode inoperable.

Notes and edge cases:

- The `local` bypass in `RestrictedDocsAccess` is unconditional and untouched by this gate — `SCRAMBLE_PROD_AUTH` only ever governs non-local environments, matching its name (`_PROD_`).
- `.env.example` gets a new commented entry (`# SCRAMBLE_PROD_AUTH=none`) with the three accepted values listed, so this doesn't repeat the undocumented-env-var gap already flagged for `IMPORT_MAX_*` in `qif-csv-import/architecture.md`.
- No change to `dedoc/scramble`'s own config or middleware stack is required beyond what "API Documentation" above already proposes (publishing `config/scramble.php` for the security scheme) — the gate is pure application code, using the extension point the package already ships.

## Part B: Optional Two-Factor Authentication — Deep Dive

### Package Decision

Recommended: a dedicated, trait-based TOTP package designed to attach directly to any `Authenticatable` model without requiring Fortify (e.g. `laragear/two-factor`, which uses Fortify-compatible column names and ships recovery codes out of the box). Alternative considered: `pragmarx/google2fa` (+ manual controller wiring) — more manual glue code, but zero opinion on storage/columns if finer control is wanted later.

Per this project's package-governance convention (established in `qif-csv-import/SPECIFICATION.md`): before implementation, confirm the chosen package is actively maintained, PHP 8.4-compatible, and has current releases; if not, fall back to the manual `pragmarx/google2fa` + custom controller approach rather than accepting an unmaintained dependency.

### Enrollment Flow

1. User opens `TwoFactorSettings.vue` on `/user/settings`, clicks "Enable".
2. `POST /api/v1/users/me/two-factor/enroll` generates a secret (unconfirmed), returns a QR code / `otpauth://` URI.
3. User scans with an authenticator app, enters the current 6-digit code.
4. `POST /api/v1/users/me/two-factor/confirm` verifies the code, sets `two_factor_confirmed_at`, generates and returns recovery codes **once**.
5. User must acknowledge ("I've saved these codes") before the modal closes.

### Login Step-Up Flow

`LoginController` currently uses `AuthenticatesUsers` (from `laravel/ui`) largely unmodified apart from `validateLogin()` (reCAPTCHA hook). The standard interception pattern for adding 2FA to this trait without adopting Fortify:

1. Normal credential check proceeds as today (`Auth::attempt()` under the hood).
2. Override the trait's post-login hook (`authenticated($request, $user)`): if `$user->two_factor_confirmed_at` is set, immediately `Auth::logout()` (undo the just-established session), store `$user->id` in a short-lived, purpose-specific session key (not the real auth session), and redirect to the 2FA challenge route instead of `AppServiceProvider::HOME`.
3. `TwoFactorChallengeController` (new, `guest`-gated like `LoginController`) reads the pending user id from that session key, accepts a TOTP code or a recovery code, and on success completes `Auth::login($user, $remember)` for real, then redirects home. On failure, the pending session key is left in place (no partial auth leak) and the attempt is throttled.
4. The pending-login session key has its own short TTL (e.g. 5 minutes) independent of `SESSION_LIFETIME`, so an abandoned challenge doesn't leave a long-lived pre-auth state.

### Recovery Codes

- Generated once at confirmation, and again on demand via "regenerate" (invalidates all previous codes).
- Stored hashed (not reversibly encrypted) — same reasoning as password storage; a code is single-use and consumed (removed from the stored set) on successful use.
- Challenge screen offers "use a recovery code instead" as a secondary option to the TOTP input.

### Rate Limiting

`TwoFactorChallengeController`'s POST route gets `throttle:6,1` (same literal pattern already used for `email/verification-notification` in `routes/web.php`) — a 6-digit TOTP code is only 1,000,000 possibilities, so brute-force throttling is not optional.

### Interaction With API Tokens

Because token creation lives behind the same session-authenticated Settings page, enabling 2FA already protects it — no separate "require 2FA to mint a token" flag is needed for that path alone. As an additional, explicitly optional hardening knob (not required for MVP), a config toggle `YAFFA_REQUIRE_2FA_FOR_API_TOKENS` could force 2FA enrollment before the token UI is reachable at all; left as a documented option rather than a default, since this is a self-hosted app where forcing extra setup steps has a real adoption cost.

## Test Strategy

- Backend tests:
  - `ApiTokenServiceTest` (unit) — ability validation, expiry cap enforcement.
  - `ApiTokenApiControllerTest` (feature) — create/list/revoke, ownership scoping (user A cannot revoke user B's token id — expect 404), plaintext token only present in the create response.
  - `TwoFactorEnrollmentTest` (feature) — enroll → confirm → recovery codes issued once → codes not re-returned on subsequent calls.
  - `TwoFactorLoginChallengeTest` (feature) — login with 2FA enabled requires the challenge step; wrong code rejected and throttled; correct code completes login; recovery code consumes it (second use of the same code fails).
  - `TwoFactorDisableTest` (feature) — disable requires correct password; wrong password rejected.
  - Rate limiter test confirming per-user (not just per-IP) keying on the `api` limiter.
  - `ViewApiDocsGateTest` (unit/feature) — all three `SCRAMBLE_PROD_AUTH` values against guest, unverified-user, and verified-user requests in a non-local environment (`app()->environment()` faked/overridden), asserting the exact allow/deny matrix above; separately assert the `local`-environment bypass still short-circuits regardless of the config value (that branch belongs to Scramble's own middleware, but the interaction is worth a regression test since it's easy to assume the gate governs local too).
- Frontend tests: manual verification of `ApiTokenManager.vue` (create/copy/revoke) and `TwoFactorSettings.vue` (enrollment, recovery code reveal, disable) in the browser per this project's UI-testing convention (no Dusk coverage planned for this feature; Dusk reserved for critical E2E flows per project conventions).
- Edge cases:
  - Creating a token with an empty `abilities` array (rejected).
  - `expires_at` in the past, or beyond `api_token_max_lifetime_days` (rejected).
  - Revoking an already-expired/pruned token id (idempotent 404, not an error).
  - Losing recovery codes with 2FA still enabled and no valid device (documented as an accepted self-hosted-app limitation — see Risks).
- Negative paths:
  - Bearer-token request without a required ability, once Phase 2 enforcement lands on a given controller, gets 403 (Sanctum's `CheckAbilities` default behavior), not a silent pass-through.
  - Session/SPA requests are unaffected by ability checks at every phase (`TransientToken` always passes `tokenCan()`).

## Risks / Open Questions

- **No account-recovery path if a user loses both their authenticator device and recovery codes.** For a self-hosted single-user app there is no support desk to fall back on. Options to resolve before shipping: an artisan command (`php artisan user:disable-2fa {email}`) as a documented break-glass procedure for the operator (whoever has server/DB access), rather than a UI-level bypass. This needs an explicit decision before implementation.
- **Phased ability enforcement (Part A)** means MVP-issued tokens are effectively full-access until the follow-up lands. This is disclosed to the user in the token-creation UI copy, but is a real interim gap worth confirming is acceptable.
- **Email notification on token creation / 2FA disable** was scoped out of MVP (see "Events / Notifications") — worth revisiting once the base flow ships, since it's a common signal for catching account compromise early.
- **2FA package final choice** is not locked — must be re-validated against maintenance/compatibility at implementation start, per standing project convention.
- **`YAFFA_REQUIRE_2FA_FOR_API_TOKENS`** is proposed but not decided as a default-on/default-off/exists-at-all question — currently designed as opt-in, off by default.
- **`SCRAMBLE_PROD_AUTH=guest` is a convenience setting, not a confidentiality concern.** YAFFA is open source, so the schema `/docs/api` would expose (routes, request/response shapes, validation rules) is already fully readable from `routes/api.php`, the `Form Requests`, and the Policies in the public repo — publishing it via Scramble doesn't disclose anything new. The one real property of `guest` mode worth knowing: Scramble's default UI ships a "Try it" button (`hideTryIt: false`, `tryItCredentialsPolicy: 'include'`) that lets a visitor fire live requests at the running instance straight from the docs page — a discoverability/convenience factor (useful precisely for a publicly hosted demo/sandbox instance), not a secrecy leak. `user` mode exists for operators who'd rather keep docs reachable only to their own logged-in instance; `guest` is the right default recommendation for a public demo.

## Acceptance Criteria

- Given the Sanctum migration has been published and run, when a user calls `$user->createToken(...)` (directly or via the new endpoint), then a row is created in `personal_access_tokens` and a plaintext token is returned exactly once.
- Given a user is on `/user/settings`, when they open the API Tokens panel, then they can create a named, ability-scoped, optionally-expiring token, see it listed (without its plaintext value) afterward, and revoke it.
- Given a revoked or expired token, when it is used to call any `/api/v1/*` endpoint, then the request is rejected as unauthenticated (401), consistent with the existing `auth:sanctum` exception handling in `bootstrap/app.php`.
- Given a user has not enabled 2FA, when they log in, then the flow is unchanged from today (no new step).
- Given a user has confirmed 2FA, when they submit correct credentials, then they are redirected to the 2FA challenge and are not granted a session until a correct TOTP or recovery code is submitted.
- Given a user submits 6 incorrect 2FA codes within a minute, when they submit a 7th, then the attempt is throttled regardless of correctness.
- Given the API rate limiter change, when a single authenticated user exceeds 120 requests/minute, then subsequent requests are throttled without affecting other users on the same instance.
