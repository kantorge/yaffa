# Flows: API Access & Two-Factor Authentication

Only flows that touch permissions, data integrity, external side effects, or operational safety are documented here.

## 1. Create a Personal Access Token

- **Actor:** an authenticated, session-based (first-party) user, viewing `/user/settings`.
- **Precondition:** user is logged in via the session cookie and email-verified.
- **Success outcome:** a new `personal_access_tokens` row exists, scoped to the requesting user, with the requested (or ability-filtered) abilities and an expiry no later than the configured max lifetime; the plaintext token is returned exactly once.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. User clicks "Create Token" in `ApiTokenManager.vue`, optionally checks "Allow write access" and/or "Allow account & security settings changes" (read is always included, shown as a fixed note), and sets an optional expiry. | Browser (client-side only) | None yet — client-side checkbox state is a UX convenience, not a security boundary. | None. |
| 2. `POST /api/v1/users/me/tokens` `{ name, abilities[], expires_at? }` | Browser → server | `auth:sanctum` + `verified`, then `ApiTokenApiController::middleware()`'s closure: `abort(403)` if `$request->user()->currentAccessToken() instanceof PersonalAccessToken` — i.e. **denies bearer-token callers outright**, session-only. Deny case: a valid-but-bearer-authenticated caller gets a 403, not a scoped response. | None yet. |
| 3. `ApiTokenRequest` validates `name` (length bounds), `abilities` (non-empty array, each value in `ApiTokenAbility::values()`), `expires_at` (future, ≤ `now()+api_token_max_lifetime_days`). | Server (validation layer) | Deny case: 422 with field errors; malformed/unknown ability values are rejected, not silently dropped. | None. |
| 4. Controller filters `$validated['abilities']` through `$user->currentAccessToken()->can($ability)` before creating the token. | Server (defense-in-depth) | For a session request, `TransientToken::can()` is always `true`, so this is currently a no-op in practice (nothing is filtered out) — it only becomes load-bearing once a bearer-token caller could reach this endpoint, which step 2's gate currently prevents entirely. | None. |
| 5. `ApiTokenService::create()` rejects an empty (post-filter) ability array; clamps `expires_at` to the configured max if absent or beyond it. | Server | Deny case: `InvalidArgumentException` if abilities end up empty. | Row inserted in `personal_access_tokens` via `$user->createToken()`. |
| 6. Response returns `{ id, name, abilities, expires_at, token }` — `token` (plaintext) only present here, never again. | Server → browser | — | Vue holds the plaintext in memory only; user must acknowledge "I have copied or saved this token" before the modal can close (`acknowledged` checkbox gates the Close button). |

## 2. Revoke a Personal Access Token

- **Actor:** the owning session-authenticated user.
- **Precondition:** the token id exists and belongs to the caller.
- **Success outcome:** the row is hard-deleted; the token stops authenticating on its next use.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `DELETE /api/v1/users/me/tokens/{id}` | Browser → server | Same session-only gate as creation (step 2 above) — a bearer token cannot revoke tokens at all, including its own. | None yet. |
| 2. `ApiTokenService::revoke()` runs `$user->tokens()->where('id', $id)->delete()` — scoped to the caller's own tokens relation. | Server | Ownership is enforced by the query scope, not a separate Policy class (documented pattern, matches `$user->transactions()` etc. elsewhere in the app). Deny case: another user's token id (or a non-existent id) matches zero rows → controller throws `ModelNotFoundException` → **404**, not 403 — deliberately avoids confirming other users' token ids exist. | Row deleted if owned; no-op (still 404) otherwise. |

## 3. Enroll in 2FA

- **Actor:** authenticated, session-based, email-verified user; `SANDBOX_MODE` must be off.
- **Precondition:** user does not already have confirmed 2FA.
- **Success outcome:** enrollment (`POST .../enroll`) creates an unconfirmed secret and returns the QR/otpauth URI; a subsequent confirmation (`POST .../confirm`) with a valid code sets `two_factor_confirmed_at` and generates and returns recovery codes once.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `TwoFactorSettings.vue` renders the sandbox-mode warning instead of the enroll button when `window.YAFFA.config.sandbox_mode` is true — client-side convenience only. | Browser | — | None. |
| 2. `POST /api/v1/users/me/two-factor/enroll` | Browser → server | `auth:sanctum` + `verified` + Sanctum's `abilities:settings` (a bearer token needs the `settings` ability; no-op for session requests). Server-side `config('yaffa.sandbox_mode')` re-check → 403 regardless of client state. **Then**: `hasTwoFactorEnabled()` check → 422 if 2FA is already confirmed. | If already-enabled check fails: no state change. Otherwise `$user->createTwoFactorAuth()` generates and stores an *unconfirmed* secret. Response includes the QR/otpauth URI + secret — sensitive, but pre-confirmation and single-user-scoped. |
| 3. User scans the QR / enters the secret in an authenticator app, submits the current 6-digit code. | Browser (out-of-band: authenticator app) → server | — | None until submitted. |
| 4. `POST /api/v1/users/me/two-factor/confirm` `{ code }` | Browser → server | Same `abilities:settings` + `sandbox_mode` gates as step 2. `TwoFactorConfirmRequest` requires `code`. **Then**: `hasTwoFactorEnabled()` check → 422 if already enabled, *without* calling `confirmTwoFactorAuth()` at all — `laragear/two-factor`'s `confirmTwoFactorAuth()` otherwise short-circuits to `true` (skipping code validation) once already enabled, which previously let this step return live recovery codes for an arbitrary/wrong code (fixed; see `permissions.md` "Hardening"). Otherwise `$user->confirmTwoFactorAuth($code)` verifies the TOTP against the stored secret. Deny case: wrong code → 422, secret stays unconfirmed, no recovery codes issued. | On success: `two_factor_confirmed_at` set; recovery codes generated and returned **once** in the response body (`recovery_codes`). |
| 5. Frontend requires the "I've saved these codes" checkbox before the modal can close. | Browser | Client-side UX gate only — codes are not re-fetchable via any endpoint after this response. | None. |

## 4. Disable 2FA / Regenerate Recovery Codes

- **Actor:** authenticated, session-based, email-verified user with 2FA confirmed; `SANDBOX_MODE` off.
- **Success outcome (disable):** 2FA state cleared, login reverts to single-factor.
- **Success outcome (regenerate):** all prior recovery codes invalidated; a fresh set returned once.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. Frontend prompts for the current password via a modal (`promptForPassword`) before calling either endpoint — client-side UX, not the real gate. | Browser | — | None. |
| 2. `POST /api/v1/users/me/two-factor/disable` or `.../recovery-codes` `{ password }` | Browser → server | `auth:sanctum` + `verified` + Sanctum's `abilities:settings` + server-side `sandbox_mode` check (403) + `TwoFactorPasswordRequest`'s `current_password` validation rule — re-verifies the account password server-side regardless of what the client already asked. Deny case: wrong password → 422, no state change. `regenerateRecoveryCodes` additionally 422s if 2FA isn't enabled at all. | `disable`: `$user->disableTwoFactorAuth()`. `regenerateRecoveryCodes`: new codes generated, old ones invalidated, returned once. |

## 5. Login Step-Up (2FA Challenge)

- **Actor:** anyone attempting `/login` (pre-authentication).
- **Precondition:** correct email/password submitted.
- **Success outcome:** if the account has confirmed 2FA, a session is only established after a correct TOTP or recovery code; otherwise unchanged from pre-feature behavior.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `POST /login` `{ email, password, recaptcha? }` — `guest` + `throttle:6,1` middleware on the route. | Browser → server | `LoginController::validateLogin()`: no `_2fa_login` session key yet → validates full credential set (+ recaptcha if configured). Deny case (bad recaptcha/missing fields): standard validation 422. | None yet. |
| 2. `attemptLogin()` → `TwoFactorLoginHelper::attemptWhen($credentials, null, $remember)`. | Server (delegates to package) | Credentials checked against the guard exactly as `Auth::attempt()` would. Deny case: wrong credentials → `sendFailedLoginResponse()`, same as before this feature, 2FA-blind. | None on failure. |
| 3a. **No confirmed 2FA:** helper behaves like `Auth::attempt()` — login completes. | Server | — | Session established immediately; no new step, per the "unchanged when 2FA disabled" acceptance criterion. |
| 3b. **2FA confirmed:** helper does *not* log the user in. Encrypted credentials flashed into session under `config('two-factor.login.key')`; same `POST /login` response re-renders the challenge view (`auth.two-factor-challenge`). | Server (session-scoped state, not a redirect) | Credentials are correct at this point but **no session exists yet** — the request is still fully unauthenticated from the app's perspective. | Session gains the flashed (encrypted) credential blob, not an auth session. |
| 4. User submits the challenge form (`2fa_code`) — same `POST /login` route, `throttle:6,1` still applies (shared with step 1, since it's one route). | Browser → server | `validateLogin()`: `_2fa_login` key present → requires only `2fa_code`. Helper merges it with the flashed credentials and re-attempts. Deny case: wrong code → validation error re-rendered on the challenge view, flashed credentials retained for another attempt (subject to the shared throttle — 6 attempts/minute across *both* the original login and every retry). | None on failure. |
| 5. Correct TOTP or recovery code → `Auth::login()` completes for real. | Server | A used recovery code is single-use and removed from the stored set on success. | Session established; flashed credential blob cleared. |

## 6. Bearer-Token Request to a Domain Endpoint (e.g. `POST /transactions/standard`)

- **Actor:** a bearer-token caller (any `/api/v1/*` endpoint outside token management and 2FA management).
- **Precondition:** valid, unexpired, unrevoked token.
- **Success outcome:** the request proceeds only if the token holds the ability the target action requires; otherwise 403, before the controller's own logic runs.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `POST /transactions/standard` (or any other action on the ~24 controllers listed in `permissions.md`) with `Authorization: Bearer <token>`. | Browser/script → server | `auth:sanctum` + `verified` — already enforced today. | None yet. |
| 2. `abilities:write` (or `read`/`settings` depending on the action — see `permissions.md`'s mapping) checks `$request->user()->tokenCan('write')`, i.e. whether `'write'` is in the token's stored `abilities` array. | Server | Deny case is 403 (Sanctum's `MissingAbilityException`, which `bootstrap/app.php`'s existing `AuthorizationException` JSON renderer already formats correctly — no new exception handling needed). | None. |
| 3. Existing ownership scoping (`$user->accounts()`, Policies, etc.) — unaffected by this feature, runs identically regardless of auth mode. | Server | Deny case: 403/404 per the existing Policy, same as a session request from a different user. | None. |
| 4. Controller logic executes. | Server | — | Normal write/read side effects for that endpoint. |

Step 2 is implemented on all ~25 controllers per `permissions.md` § "Full Ability Enforcement — Implementation Plan" and pinned by `tests/Feature/API/ApiAbilityEnforcementTest.php` — a token only reaches step 3/4 for actions its recorded abilities actually cover.

## 7. View API Docs (`/docs/api`) in a Non-Local Environment

- **Actor:** any HTTP client (authenticated or not) reaching `/docs/api`.
- **Success outcome:** access is granted or denied per `SCRAMBLE_PROD_AUTH`.

| Step | Boundary crossed | Authz check | Side effect |
|---|---|---|---|
| 1. `GET /docs/api` | Browser → server | Scramble's `RestrictedDocsAccess` middleware: `local` environment → always allowed, gate not consulted. Otherwise → `Gate::allows('viewApiDocs', $user)`. | None (read-only). |
| 2. `Gate::define('viewApiDocs', ...)` in `AppServiceProvider::bootEvent()` evaluates `config('yaffa.scramble_prod_auth')`. | Server (config-driven) | `none` (default) → always false. `user` → `$user !== null && $user->hasVerifiedEmail()`. `guest` → always true. Deny case: 403 for `none`/unmet `user` condition. | None. |
| 3. If `guest` mode and Scramble's default "Try it" UI is active (`hideTryIt: false`, `tryItCredentialsPolicy: 'include'`), a visitor can fire live authenticated-as-themselves requests at the running instance from the docs page. | Browser → server (via the docs UI) | Whatever auth the visitor already has (session cookie, if any) — this is a convenience/discoverability feature, not a new privilege; it cannot act as anyone other than the visitor's own session. | Depends entirely on which live endpoint the visitor exercises via "Try it." |
