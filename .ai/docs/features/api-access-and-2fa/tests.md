# Test Coverage: API Access & Two-Factor Authentication

| Use case | Rule (doc) | Expected behavior (+ deny case) | Evidence | Type | Status |
|---|---|---|---|---|---|
| Token creation | `permissions.md` §Auth-Mode Matrix | Session request can create a token; unauthenticated request denied | `ApiTokenApiController.php:61-91` | integration | existing |
| Token creation — empty abilities | `permissions.md` §Ability Scope Model | `abilities: []` rejected with 422 | `ApiTokenRequest.php:24-28` | integration | existing |
| Token creation — unknown ability | `permissions.md` §Ability Scope Model | Value outside `ApiTokenAbility::values()` rejected with 422 | `ApiTokenRequest.php:29-31` | integration | existing |
| Token creation — `expires_at` in the past | `flows.md` §1 step 3 | Rejected with 422 | `ApiTokenRequest.php:32-37` | integration | existing |
| Token creation — `expires_at` beyond max lifetime | `flows.md` §1 step 3 / `variables.md` `API_TOKEN_MAX_LIFETIME_DAYS` | Rejected with 422 | `ApiTokenRequest.php:32-37` | integration | existing |
| Token creation — no `expires_at` given | `variables.md`, SPECIFICATION.md §Token Lifecycle | Clamped to `now()+api_token_max_lifetime_days` | `ApiTokenServiceTest.php:40-55` | unit | existing |
| Token creation — `expires_at` beyond max, service layer | SPECIFICATION.md §Token Lifecycle | Clamped, not rejected, at the service layer (Form Request already blocks this at the HTTP layer — this pins the service's own defense-in-depth clamp) | `ApiTokenServiceTest.php:26-38` | unit | existing |
| Token creation — empty abilities, service layer | `permissions.md` §Ability Scope Model | `InvalidArgumentException` | `ApiTokenServiceTest.php:16-24` | unit | existing |
| Token revocation — owner | `flows.md` §2 | Row deleted, 204 | `ApiTokenApiControllerTest.php:99-108`, `ApiTokenServiceTest.php:73-82` | integration + unit | existing |
| Token revocation — non-owner | `flows.md` §2, `permissions.md` | 404 (not 403), row untouched | `ApiTokenApiControllerTest.php:110-120`, `ApiTokenServiceTest.php:84-94` | integration + unit | existing |
| Token listing — user isolation | `permissions.md` §Scope Derivation | Only the caller's own tokens returned | `ApiTokenServiceTest.php:57-71` | unit | existing |
| Token listing — plaintext never re-shown | `flows.md` §1 step 6 | `token` field absent from list response | `ApiTokenApiControllerTest.php:40` | integration | existing |
| **Bearer token cannot manage tokens** | `architecture.md` Trust Boundaries, `permissions.md` §Auth-Mode Matrix | A full-abilities (`['*']`) bearer token gets 403 on `GET /tokens` | `ApiTokenApiControllerTest.php:122-130` | integration | existing |
| **Narrow bearer token cannot mint a broader token** | `flows.md` §1 step 4, `permissions.md` §Ability Scope Model | A `read`-only token attempting to create a `settings`-scoped token gets 403, no row created | `ApiTokenApiControllerTest.php:132-144` | integration | existing |
| 2FA status default | `flows.md` §3 | `enabled: false` for a fresh user | `TwoFactorEnrollmentTest.php:15-24` | integration | existing |
| 2FA enroll → confirm → enabled + one-time codes | `flows.md` §3 | Full happy path, codes present in response | `TwoFactorEnrollmentTest.php:26-50` | integration | existing |
| 2FA confirm with wrong code (first-time enrollment) | `flows.md` §3 step 4 | 422, `enabled` stays `false` | `TwoFactorEnrollmentTest.php:52-66` | integration | existing |
| 2FA status unauthenticated | `flows.md` §3 | Denied | `TwoFactorEnrollmentTest.php:68-73` | integration | existing |
| 2FA disable — wrong password | `flows.md` §4 | 422, 2FA stays enabled | `TwoFactorDisableTest.php:22-34` | integration | existing |
| 2FA disable — correct password | `flows.md` §4 | 200, 2FA disabled | `TwoFactorDisableTest.php:36-49` | integration | existing |
| Recovery-code regeneration — wrong password | `flows.md` §4 | 422 | `TwoFactorDisableTest.php:51-62` | integration | existing |
| Recovery-code regeneration — correct password | `flows.md` §4 | New codes returned, differ from prior set | `TwoFactorDisableTest.php:64-80` | integration | existing |
| Login unaffected when 2FA disabled | `flows.md` §5 step 3a | Session established immediately | `TwoFactorLoginChallengeTest.php:39-50` | integration | existing |
| Login shows challenge when 2FA confirmed | `flows.md` §5 step 3b | Guest, challenge view rendered | `TwoFactorLoginChallengeTest.php:52-64` | integration | existing |
| Wrong password still rejected with 2FA on | `flows.md` §5 step 1-2 | Guest, validation error, 2FA-blind | `TwoFactorLoginChallengeTest.php:66-78` | integration | existing |
| Challenge — wrong code rejected | `flows.md` §5 step 4 | Guest, error on `2fa_code` | `TwoFactorLoginChallengeTest.php:80-101` | integration | existing |
| Challenge — correct TOTP completes login | `flows.md` §5 step 5 | Authenticated | `TwoFactorLoginChallengeTest.php:103-125` | integration | existing |
| Challenge — recovery code accepted and single-use | `flows.md` §5 step 5, SPECIFICATION.md §Recovery Codes | Login succeeds; same code rejected on reuse | `TwoFactorLoginChallengeTest.php:127-163` | integration | existing |
| Break-glass disable command | `permissions.md` §2FA State | Disables 2FA for a known email; no-op if already disabled; fails (exit 1) for unknown email | `DisableTwoFactorAuthCommandTest.php` (all 3 cases) | integration | existing |
| API rate limiter keys by user, not shared | `architecture.md` Trust Boundaries | Two different users get two different limiter keys | `RateLimiterTest.php:15-32` | unit | existing |
| API rate limiter falls back to IP when unauthenticated | `architecture.md` Trust Boundaries | Two different IPs get two different limiter keys | `RateLimiterTest.php:34-45` | unit | existing |
| `SCRAMBLE_PROD_AUTH` full allow/deny matrix | `flows.md` §6, `variables.md` | All 6 combinations of `none`/`user`/`guest` × guest/unverified/verified visitor | `ViewApiDocsGateTest.php` (all 6 cases) | integration | existing |
| **2FA re-confirm on an already-enabled account** | `flows.md` §3 step 4, `permissions.md` §Hardening | `POST /two-factor/confirm` on an already-confirmed account is rejected (422) without validating the submitted code or returning recovery codes | Security audit finding #1, fixed — `TwoFactorApiController.php` `confirm()` | integration | existing |
| **2FA re-enroll on an already-enabled account** | `flows.md` §3 step 2, `permissions.md` §Hardening | `POST /two-factor/enroll` on an already-confirmed account is rejected (422) without touching the existing secret | Security audit finding #2, fixed — `TwoFactorApiController.php` `enroll()` | integration | existing |
| Bearer token needs `settings` ability to reach 2FA mutating endpoints | `permissions.md` §Auth-Mode Matrix | A token without `settings` gets 403 on `enroll`/`confirm`/`disable`/`regenerate-recovery-codes`; a token with `settings` is allowed; `show` remains reachable without it | Security audit finding #3, fixed — `TwoFactorApiController::middleware()` | integration | existing |
| Bearer token cannot use unscoped abilities on other `/api/v1/*` endpoints | `permissions.md` § Full Ability Enforcement — Implementation Plan | A token without a route's required ability is denied on all ~24 remaining controllers; a token with it is not blocked by the ability check | `ApiAbilityEnforcementTest.php` (86 cases: one deny + one allow per representative route) | integration | existing |

## Existing coverage

The suite is genuinely solid for the paths it covers: both controllers' happy/deny paths, ownership isolation on tokens, the full login step-up state machine (including recovery-code single-use), the rate limiter's per-user keying, the Scramble docs gate's full 3×2 matrix, the break-glass command, and — as of this hardening pass — the two Critical 2FA findings and the missing ability gate. `ApiTokenApiControllerTest` already pinned the two "a bearer token must not be able to escalate itself" rules that make the token-management feature safe; `TwoFactorEnrollmentTest` and the new `TwoFactorApiControllerAccessTest` now pin the equivalent guarantees for 2FA management:

- `TwoFactorEnrollmentTest::test_confirm_on_already_enabled_account_is_rejected_without_leaking_recovery_codes`
- `TwoFactorEnrollmentTest::test_enroll_on_already_enabled_account_is_rejected_without_wiping_existing_secret`
- `TwoFactorApiControllerAccessTest` (6 cases: 4 deny-without-`settings`, 1 allow-with-`settings`, 1 `show` remains reachable without `settings`)
- `ApiAbilityEnforcementTest` (data-provider-driven, 43 representative routes × 2 = 86 cases: one deny-without-ability, one allow-with-ability, per controller)

All pass against the fixed code. This is above-average coverage for the paths it covers, and the feature is now complete: full ability enforcement across all ~24 remaining `API` controllers has shipped and is pinned by the tests above, in addition to each controller's pre-existing feature test file (re-run unmodified except where a test used `Sanctum::actingAs($user)` with no abilities as a generic "authenticate this user" helper — those calls were updated to pass `['*']` so they keep testing business logic rather than being incidentally blocked by the new ability gate; see `permissions.md`'s checklist for the affected files).

One additional, distinct issue surfaced only by the full suite run: `tests/Unit/Http/Controllers/API/InvestmentApiControllerTest.php::test_investment_list_only_returns_users_own_investments` re-authenticates mid-test via plain `$this->actingAs($user2)` (no guard arg) after a prior request in the same test already flipped the container's default auth driver to `sanctum` (via `Authenticate::shouldUse()`). That makes the second `actingAs()` call set the user directly on Sanctum's cached `RequestGuard` instance, bypassing the `withAccessToken(new TransientToken)` wrapping `Guard::__invoke()` normally does — so `currentAccessToken()` was `null` for the second user, and the new `abilities:read` check correctly 401'd it. Fixed by switching both `actingAs()` calls in that test to `Sanctum::actingAs($user, ['*'])`, which attaches a mocked token directly to the user model before assigning it to the guard. This is a general hazard for any test that swaps the authenticated user mid-method via plain `actingAs()` on an API route — worth keeping in mind if it recurs elsewhere.

## Implemented: Full Ability Enforcement (see `permissions.md`)

`abilities:*` middleware was added to every `API` controller per `permissions.md`'s implementation plan, and pinned by a single data-provider-driven test class rather than ~24 separate test files (a `RefreshDatabase` + `Sanctum::actingAs()` integration test per route would have been ~50 nearly-identical methods).

**`tests/Feature/API/ApiAbilityEnforcementTest.php`** (integration):

```php
See `tests/Feature/API/ApiAbilityEnforcementTest.php` for the actual implementation. It follows the shape above with three adjustments made during implementation:

- Uses PHPUnit's `#[DataProvider('routeAbilityProvider')]` attribute, not the `@dataProvider` docblock — this project's PHPUnit version (12.x) no longer reads docblock metadata.
- `assertStatus(fn (int $status) => ...)` isn't supported by the installed `Illuminate\Testing\TestResponse::assertStatus()` (int-only); the "not 403" assertion uses `$this->assertNotEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode())` instead.
- Route params that reference a specific owned record (`currency-rates.index`, `investment-prices.index`, `tags.patch-active`, `investments.destroy`, `account-entities.destroy`, `account-groups.destroy`, `investment-groups.destroy`) are resolved to a real, freshly-created row owned by the test's `$user` via a `resolveParams()` helper, rather than a hardcoded id like `1`. `RefreshDatabase` rolls back row *data* between tests but does not reset MySQL's auto-increment counters, so a hardcoded id only happens to exist for the first test in a run — every other test would 404 before even reaching the ability check. The remaining routes (string params like `providerKey`/`topic`, or no params) keep the literal placeholder values shown above.
- This table must be kept in sync with `permissions.md`'s mapping — if a new `API` controller or action is added later, add a row here (and to the real provider) in the same PR.
- This is a floor, not a ceiling: per-controller existing feature tests continue to cover the domain logic; this test class only covers the ability-gate boundary.

## Recommended CI gate

The project already runs `vendor/bin/sail artisan test --compact`; no new tooling is needed, only ensuring the new tests above land in the default (non-guarded) run — they're all `RefreshDatabase` + `Sanctum::actingAs`/`actingAs` integration tests with no external services, so they belong in the standard suite, not a guarded-live lane.

```yaml
# .github/workflows/tests.yml (illustrative — adapt to the repo's actual CI file/runner)
- name: Run test suite
  run: vendor/bin/sail artisan test --compact
```

Branch protection: require this check to pass before merging to `develop`/`main` (if not already configured — this repo's existing PRs suggest CI already gates merges; confirm the two new Critical-finding tests are included in whatever job currently runs `tests/Feature/**`).

## Gaps — documented but unverified

None remaining for this feature. Security audit findings #1–#3 (2FA recovery-code leak, silent 2FA wipe, missing ability gate on `TwoFactorApiController`) were fixed and are pinned by tests — see "Existing coverage" above. The former top-priority gap — personal access token `abilities` not enforced across ~24 of the app's API controllers — has shipped and is pinned by `ApiAbilityEnforcementTest` (see `permissions.md` § Full Ability Enforcement — Implementation Plan).
