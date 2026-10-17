# Testing Context - YAFFA (`tests/`)

See root [CLAUDE.md](../CLAUDE.md) for project overview and commands.
See [.ai/agents/testing.agent.md](../.ai/agents/testing.agent.md) for full testing rules.

## Test Level Guide

| Level          | Location         | Use when                                 |
| -------------- | ---------------- | ---------------------------------------- |
| Unit           | `tests/Unit/`    | Pure isolated logic, no DB or HTTP       |
| Feature        | `tests/Feature/` | HTTP endpoints, policies, validation, DB |
| Browser (Pest) | `tests/PEST/`    | Critical E2E user journeys only          |
| Browser (Dusk) | `tests/Browser/` | **Legacy** — no new Dusk tests           |

Prefer Feature tests. Use browser tests sparingly — only when a full browser interaction is essential.

**All new tests are written in Pest 5**, whatever their level. New Unit/Feature tests use Pest syntax in
`tests/Unit/`/`tests/Feature/`, next to the existing PHPUnit classes (don't convert those unless the owner
approves). New browser tests use `pest-plugin-browser` (Playwright) in `tests/PEST/`.

## Running Tests

```bash
# Single file
vendor/bin/sail artisan test --compact tests/Feature/SomeTest.php

# Filter by name
vendor/bin/sail artisan test --compact --filter=testMethodName

# Full suite (Unit + Feature; the Browser suite is excluded by default)
vendor/bin/sail artisan test --compact

# Pest browser tests (needs built assets and no public/hot; Chromium lives in node_modules)
vendor/bin/sail npm run build
vendor/bin/sail php vendor/bin/pest --testsuite=Browser [--group=critical] [--filter=...]
```

First-time setup inside Sail: `vendor/bin/sail npx playwright install chromium` (no extra system
packages needed in the Sail 8.4 image). Failure screenshots go to `tests/Browser/Screenshots/`
(path fixed by the plugin, git-ignored). Files in `tests/Unit`/`tests/Feature` that use Pest syntax must
be run with `pest`/`artisan test` — plain `vendor/bin/phpunit` can't load them.

## Conventions

- Pest 5 for new tests; existing class-based PHPUnit tests stay as they are
- Test names describe behavior: `it('rejects a transaction without an account')`
- Arrange / Act / Assert structure
- One behavior per test method
- Use factories for all test data; check for existing factory states before adding new ones
- Reuse the **generic test user** and their seeded assets by default
- UI/text assertions: use English locale unless testing locale-specific behavior
- Seed the database once per class where possible (`setUpBeforeClass` / `RefreshDatabase`)

## Pest Browser-Specific (`tests/PEST/`)

- Runs the app in-process against the `testing` DB. `tests/Pest.php` applies `RefreshDatabase` with
  seeding (`Tests\PEST\Support\BrowserTestCase::$seed`): migrate + `DatabaseSeeder` once per run, each
  test in a rolled-back transaction. Use the seeded demo user (`demo@yaffa.cc`)
- Log in with `actingAs($user)`, not the login form (except for login tests)
- Config overrides: `config([...])` — never `setConfig()`/duskapiconf
- Groups: `->group('critical')` / `->group('extended')`
- Prefer IDs and `data-testid` selectors; no `sleep()`/`wait(n)`/`retry()` — wait on a condition
- Mirror the Dusk layout (`tests/PEST/Pages/...`); shared helpers go in `tests/PEST/Support/`
- Each browser test's own closure must call `visit(` (the plugin scans the closure source for it to start
  Playwright and the in-process server). A helper may take the page, but must not be the only place `visit()` is called

## Dusk-Specific (legacy)

- Prefer `data-testid` selectors over CSS class or text selectors
- No `sleep()` — use `waitFor` and `waitUntilMissing` instead
- Focus on E2E journeys, not duplicating feature-level coverage
- **`setConfig()`/`getConfig()` (via `alebatistella/duskapiconf`, `UsesDuskApiConfig` trait) persist overrides to `storage/app/duskapiconf_tmp.txt`.** The package's service provider re-applies that file's contents to `config()` on *every* non-production boot — any `artisan` command, not just Dusk runs — until the file is deleted; only an explicit `resetConfig()` call (or deleting the file) clears it. `tests/DuskTestCase.php::tearDown()` now deletes this file unconditionally after every Dusk test (survives assertion failures/exceptions, since `tearDown()` still runs), so this should no longer happen — but if a test run is killed outright (Ctrl+C, OOM), `tearDown()` never fires and the file can still leak. Symptom: a `config('yaffa.*')` value (e.g. `sandbox_mode`) is unexpectedly stuck at whatever a past Dusk test last set it to, in `artisan tinker`/`artisan test`/anywhere — not just within Dusk itself. Fix: `rm storage/app/duskapiconf_tmp.txt` (or check its contents first if you want to know what was overridden).

## Do NOT

- Remove existing tests without explicit user approval
- Weaken assertions to make tests pass
- Introduce testing frameworks other than PHPUnit (legacy) and Pest 5
- Write new Dusk tests
- Add arbitrary delays or flaky environment-dependent behavior
