# Testing Plan: Pest 5 Browser Tests

This is the testing half of the Tom Select migration (see [`specification.md`](specification.md)).
Audience: the testing agent. It overrides `.ai/agents/testing.agent.md` and `tests/CLAUDE.md`
where they conflict. Those files are updated in Phase 0 (spec §10).

## 1. Tooling (Phase 0)

### Dependencies (approved by the user as part of this migration)

| Where | Package | Constraint | Notes |
| --- | --- | --- | --- |
| composer `require-dev` | `pestphp/pest` | `^5.2` | Requires PHPUnit `^13.3.4`; the lockfile is on 13.3.3, so it will bump |
| composer `require-dev` | `pestphp/pest-plugin-laravel` | `^5.0` | Laravel `^13.23` |
| composer `require-dev` | `pestphp/pest-plugin-browser` | `^5.0` | Needs `ext-sockets` (present in the Sail 8.4 image; add it to the CI `setup-php` extensions) |
| npm `devDependencies` | `playwright` | latest | Browsers installed with `npx playwright install chromium` (add `--with-deps` in CI) |

`laravel/dusk` and `alebatistella/duskapiconf` stay, because the remaining Dusk tests use them.
`roquie/laravel-dusk-select2` is removed in Phase 4 (spec §9).

### Layout and configuration

- `tests/Pest.php` binds `Tests\TestCase` and `Illuminate\Foundation\Testing\RefreshDatabase` to
  `tests/PEST`. Browser defaults: Chromium, viewport 1920×1080 (same as Dusk, because several
  assertions depend on `md`+ layout; for example the tag select is hidden below `md`), a sensible
  timeout, and a screenshots path under `tests/PEST/` that's git-ignored.
- Existing PHPUnit class-based Unit/Feature tests need **no** changes. Pest runs them as they are.
  Because all **new** tests are written in Pest (spec §10), `tests/Pest.php` also binds
  `Tests\TestCase` to `Unit` and `Feature`. The binding only affects function-style Pest files,
  so class-based tests are unaffected. Prove this with one trivial Pest feature test in Phase 0.
- `phpunit.xml`: add a `Browser` testsuite (`./tests/PEST`, suffix `Test.php`) and keep it **out
  of the default run**, so `artisan test` still runs only Unit + Feature (spec AC-7). Verify the
  exclusion mechanism works with PHPUnit 13, for example `defaultTestSuite`.
- Directory mirrors Dusk: `tests/PEST/Pages/Transactions/…`, `tests/PEST/Pages/Reports/…`, and so
  on. Shared helpers go in `tests/PEST/Support/` (autoloaded under `Tests\`).

### Running

- Local, inside Sail: `vendor/bin/sail npm run build`, make sure `public/hot` is absent, then
  `vendor/bin/sail php vendor/bin/pest --testsuite=Browser [--group=critical]`. Phase 0 must
  establish and document the exact command that works with Playwright's browser inside the
  Sail container. If that needs a published Sail Dockerfile, ask the user first (spec §13).
- CI: new `.github/workflows/test-e2e.yml`, modelled on `test-dusk-critical.yml` (PR trigger,
  same `paths` filter plus `tests/PEST/**`, `tests/Pest.php` and `phpunit.xml`) with:
  PHP 8.4 + `sockets`, MySQL, `composer install`, `npm ci`, `npm run build`,
  `npx playwright install --with-deps chromium`, `vendor/bin/pest --testsuite=Browser`.
  No `php artisan serve` and no chromedriver, because Pest serves the app in process. Upload
  screenshots on failure. Either run the `extended` group on push to `develop`, mirroring
  `test-dusk-extended.yml`, or run everything on PRs. Choose based on measured runtime.
- Before converting anything, Phase 0 ships one smoke test (log in as the demo user, open the
  dashboard, assert no console errors) to prove the pipeline locally and in CI.

### Database and state

- Pest browser tests run the app **in the test process** against the `testing` database (from
  `phpunit.xml`), not the dev DB that Dusk used.
- Use `RefreshDatabase` with seeding (`$seed = true` or an equivalent `beforeEach`), so migration
  and `DatabaseSeeder` run **once per run** and each test runs in its own rolled-back transaction.
  This replaces Dusk's `migrate:fresh` + `db:seed` once-per-class `static $migrationRun` pattern.
  Tests keep using the seeded demo user (`demo@yaffa.cc`) and its assets, per the testing rules.
- Don't use `setConfig()`/`duskapiconf` in E2E tests. If a test needs a config override, use
  `config([...])`, which works in process.
- Log in with `actingAs($user)`, not by driving the login form, except in tests about login.

## 2. Conversion rules (Dusk → Pest)

1. **Whole files.** Every Dusk file in §3 is converted in full, including tests that don't touch
   Select2, so no half-Dusk file remains. The Dusk original is deleted in the same PR, once the
   Pest file passes.
2. **1:1 test mapping.** Keep one Pest `it()`/`test()` per Dusk method and a readable version of
   its name, so coverage can be diffed. Merging or splitting tests needs a note in the PR.
3. **Groups carry over**: `#[Group('critical')]` → `->group('critical')`, and the same for
   `extended`.
4. **Assertions stay at least as strong.** Anything a Dusk test asserted (values, labels, DB
   state, URL, visible text) is asserted in Pest too. Don't weaken assertions to get green.
5. **No retries or sleeps.** Drop every `retry(n, …)` wrapper and every `pause()`/`sleep()`.
   Five `retry()` calls exist in these files today, and each one was hiding a race. Don't use
   `wait(n)` either. Rely on Playwright auto-waiting and explicit waits on a condition (an
   element state, an option being present, a value being set). If a race comes back, fix the
   cause (usually a missing "ready" signal in the component) instead of adding a retry.
6. **Stable selectors.** Prefer element IDs and `data-testid`. Tom Select derives stable IDs
   from the original `<select id>` (`{id}-ts-control`, `{id}-ts-dropdown`). For per-row selects
   without an ID (transaction item category and tags), add a `data-testid` to the original
   `<select>` rather than depending on `.ts-*` class structure. Frontend agents should accept
   these attribute additions.
7. **Drive the real widget.** Selecting goes through the UI (open, type, pick the option), not
   `tomselect.setValue()` from JS. Reading the current value with
   `element.tomselect.getValue()` through `script()` is fine.
8. Dusk macros from `tests/Browser/DuskMacros.php` that the converted files use get Pest
   equivalents in `tests/PEST/Support/` only if they're still needed. Don't port unused helpers.

### Tom Select helpers (`tests/PEST/Support/`)

These replace the `roquie` macros (`select2`, `select2ExactSearch`, `select2ClearAll`) and the
`DuskTestCase` helpers (`getSelect2Values`, `getSelect2ValueCount`,
`waitForSelect2ValueCount`, `assertSelect2Values`, `assertSelect2HasNoSelection`). All take the
**original `<select>`'s selector**:

| Helper | Behaviour |
| --- | --- |
| choose option | Open the control, type the search text into the dropdown input, wait for an option whose text **exactly** equals the label, click it, and wait until the value is set |
| choose several | Repeat "choose option" for a multi-select |
| clear | Click the clear button, then wait until the value is empty |
| read values | Return `getValue()` normalised to `string[]` |
| assert values / assert empty | Order-insensitive equality on values, or assert no values |
| wait for ready | Wait until `element.tomselect` exists (replaces `waitForTransactionItemCategorySelect2`) |

Keep this set small. Add a helper only when two or more tests need it.

## 3. Dusk files to convert

| Dusk file (`tests/Browser/Pages/…`) | Tests | Group | Phase | Select2-dependent parts |
| --- | --- | --- | --- | --- |
| `Reports/FindTransactions/FindTransactionsFilterBehaviorTest.php` | 8 | critical | 2 | Presets for tag/category/account/payee selectors loaded from the URL; clear behaviour |
| `Transactions/TransactionFormStandardStandaloneTest.php` | 24 | critical | 3 | Account/payee selects, transaction item category + tags, new-payee modal (PayeeForm), transfer currencies, clone/edit presets |
| `Transactions/TransactionFormStandardModalTest.php` | 9 | critical | 3 | Same widgets inside the modal; the dirty/Esc/close-confirm tests depend on presets settling (spec AC-2) |
| `Transactions/TransactionFormInvestmentStandaloneTest.php` | 24 | critical | 3 | Account/investment mutual currency filtering, URL presets, price storage |
| `Transactions/TransactionFormInvestmentModalTest.php` | 6 | critical | 3 | Same widgets in the modal; "investment is cleared after cancel" |
| `Payees/PayeeListTest.php` | 1 | extended | 3 | Category select in the new-payee modal (PayeeForm) |

72 tests in total. The remaining Dusk files are out of scope, but must still pass after Phase 4
(they must not have depended on the removed `DuskTestCase` helpers; grep confirms only the
files above use them).

## 4. New E2E coverage

These pages are migrated but have **no browser coverage today**. Keep each test minimal: one
happy path per page plus the listed edge case. Behaviour reference: `inventory.md`.

| ID | Phase | Page / inventory row | Test |
| --- | --- | --- | --- |
| T-CAT-MERGE | 1 | Category merge (P1, P2) | Preset source from the URL; target search doesn't offer the source; submit merges (assert DB) |
| T-PAYEE-MERGE | 1 | Payee merge (P3, P4) | Pick both, submit, assert DB; the same payee can't be offered on both sides |
| T-CL | 1 | Category learning (P7–P9) | Category filter narrows the table and clearing restores it; the merge modal select works inside the modal (typing works, dropdown not clipped, Esc closes only the dropdown) |
| T-REPORT-ACC | 1 | Cashflow + budget chart (P10, P11) | `?account=` preset shows the account selected and loads data; clearing updates the URL |
| T-BUDGET | 2 | Budget modal (V11, V12) | Create a budget with category + account; the currency suffix follows the account; edit loads presets |
| T-SEC-1 | 3 | Investment select (V4) | An investment named `<img src=x onerror=window.__xss=1>` shows as literal text in the option list and the selection; `window.__xss` stays undefined (spec AC-4) |
| T-CACHE-1 | 3 | Investment form (V3, V4) | Open the investment dropdown, pick an account in another currency, reopen: only matching-currency investments are listed (spec AC-6). Also the reverse direction. |

Also add one `node:test` file for the factory's query-param serializer (spec FR-1 item 3): an
`undefined` value is omitted, `null` is sent as empty, and `'*'`/`''` terms are passed through.
No new JS test framework; run it with `node --test`, like
`resources/js/investments/lib/investmentReturn.test.js`.

## 5. Visual parity check (AC-3)

Before Phase 1 starts, capture reference screenshots (light and dark) of these screens with
Select2:

- standard transaction form: empty, then with values and tags
- investment form with its dropdown open
- find-transactions filters with chips
- the payee modal
- the budget modal

After each phase, capture the same screens again and compare them manually in the PR. This is a
review aid, not an automated assertion. Don't commit these screenshots.

## 6. Owner review notes

The owner asked to review these by hand during Phase 3 testing, so copy spec §15 (R1–R2) into the
Phase 3 PR description. Don't write tests that lock in the create-tag option position (R1).
Do cover `selectOnClose` (R2) with a test, because it is current behaviour; if the owner later
drops it, that test goes too.

## 7. One-time Pest conversion scan (Phase 5)

Scan the existing PHPUnit tests in `tests/Unit/` and `tests/Feature/` **once** and produce a
report. **Don't convert anything.**

Output: `.ai/docs/specifications/tom-select-migration/pest-conversion-candidates.md`. List only
files where conversion clearly pays off, with the evidence:

- **Simplification**: repeated near-identical test methods or large data providers that would
  become one `it()->with()` dataset; heavy `setUp` boilerplate that `beforeEach` plus Pest's
  Laravel helpers would remove. Give the estimated line reduction.
- **Performance**: slow files (measure with `--profile` or `--log-junit`) where Pest features
  would materially help, for example `--parallel` becoming usable, or shared expensive setup that
  can move to `beforeAll`. Give the measured runtime and the expected gain.
- **Excluded**: conversions that would only change syntax. A list of "everything could be Pest"
  isn't useful.

For each candidate, give the file, the reason, the estimated gain, the risk, and a
recommendation (convert / leave). The owner picks which ones to convert.

## 8. Done criteria

- All converted and new E2E tests pass locally and in CI. They're deterministic: the full E2E
  suite passes **three consecutive times** locally without a change.
- `artisan test --compact` (Unit + Feature) passes and doesn't run E2E.
- The remaining Dusk suites (critical, extended) pass.
- No Select2 helpers, macros or selectors are left in `tests/` (spec §9 grep).
- E2E runtime for the converted files is recorded in the Phase 3 PR description next to the old
  Dusk runtime for the same files.

## 9. Phase 0b: payee form unification tests

The first real Pest work after the Phase 0 setup. All new tests are Pest (spec §10).

**New Pest feature tests** (for example `tests/Feature/PayeeFormRedirectTest.php`):

- `GET account-entity.create?type=payee` redirects to `account-entity.index?type=payee&create=1`.
- `GET account-entity.edit` for the user's own payee redirects to
  `account-entity.index?type=payee&edit={id}`.
- Another user's payee: `edit` → 403, and web `update` → 403 (authorization comes before the
  redirect/404).
- `POST account-entity.store` / `PATCH account-entity.update` with `config_type=payee` → 404, and
  nothing is written.
- Account `create`/`edit`/`store`/`update` are unaffected. Existing tests should already cover
  this; add a test only if there's a gap.

**Coverage that must move before web tests are removed.** `tests/Feature/PayeeTest.php` tests the
web form. These methods lose their subject and are **removed** (the owner approved this as part
of the cleanup):

- `test_user_can_access_create_form`
- `test_user_cannot_create_a_payee_with_missing_data`
- `test_user_can_create_a_payee`
- `test_user_can_edit_an_existing_payee`
- `test_user_cannot_update_a_payee_with_missing_data`
- `test_user_can_update_a_payee_with_proper_data`

`PayeeApiControllerTest` already covers creating and updating through the API, but **not
validation failures**. Add Pest feature tests for the API store/update validation cases that the
removed web tests asserted: missing required data, and whatever else those tests checked. Read
them before deleting. The shared `AuthorizesResourceCrud` tests in `PayeeTest` stay and must
still pass.

**New Pest browser tests** (`tests/PEST/Pages/Payees/`):

- `?create=1` opens the new-payee modal; saving adds the row to the table without a reload.
- `?edit={id}` opens the edit modal with the name, default category, alias and preferred/excluded
  chips loaded; saving persists (assert DB).
- After closing a deep-linked modal, reloading the page doesn't reopen it (the parameter is gone
  from the URL).
- `?edit={id}` with an unknown ID shows an error and no half-filled modal.

**Unaffected (verify only):** `TransactionShowStandardStandaloneTest` (Dusk) asserts that the payee
link's `href` is the `account-entity.edit` URL. Links don't change, so it still passes. Don't
touch it.
