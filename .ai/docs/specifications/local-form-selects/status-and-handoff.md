# Local Form Selects: Status and Handoff

Read [`specification.md`](specification.md) first (goals, decisions D1-D9, API contract, ranking rules). This file says
what exists, what is left, and how to do the remaining phases without the original conversation.

Work branch: `feature/local-transaction-form-selects` (created from `develop`). Phases 1-5 are implemented and
committed (Phase 3: `7838cce5`; Phase 4: `8bf82219`); Phase 5 is implemented but not yet committed; Phase 6 is left. Run `git log` to see the real state.

## 1. Environment and commands

All PHP/Node commands run through Sail (see root `CLAUDE.md`). If Sail is not running: `vendor/bin/sail up -d`, wait
about 20 seconds for MySQL.

| Task | Command |
| --- | --- |
| JS unit tests (ranking) | `node --test resources/js/shared/lib/select-data/ranking.test.js` (the folder's `package.json` `{"type":"module"}` lets plain node load the ESM files) |
| API feature tests | `vendor/bin/sail artisan test --compact tests/Feature/API/V1/ReferenceDataApiV1Test.php` and `--filter=ApiAbilityEnforcement` |
| Build assets (needed before browser tests, and no `public/hot` may exist) | `vendor/bin/sail npm run build` |
| Pest browser tests | `vendor/bin/sail php vendor/bin/pest --testsuite=Browser --filter="TransactionFormStandard"` (about 90 s for the standard form files; `TransactionFormInvestment` about 55 s). If it fails with `PlaywrightOutdatedException`, the browsers are missing **inside the Sail container** (host installs do not count): run `vendor/bin/sail npx playwright install chromium` |
| JS lint | `npx eslint resources/js/shared resources/js/transactions` (`--fix` for prettier) |
| PHP style / static analysis | `vendor/bin/sail bin pint --dirty`, `vendor/bin/sail php vendor/bin/phpstan analyse <files> --memory-limit=1G` |
| Translations check | `php .github/scripts/check-translations.php --base=develop` |

Project rules that apply (root and `resources/js/CLAUDE.md`, `app/CLAUDE.md`, `tests/CLAUDE.md`): Vue **Options API**
only (no `<script setup>`), no new dependencies, no new JS framework, new tests in **Pest** (no new Dusk), run the minimum
affected tests then ask whether to run the full suite, every translatable string goes into **all** `lang/*.json`
(`en` first) with a reminder to the user that a native speaker must review the other languages, never touch `.env`.
Browser test conventions: wait on conditions with `waitUntil`, never `sleep`; use ids over classes.

## 2. What is done

### Phase 1: backend and data module

Backend (all additive, mobile contract untouched):

- `app/Http/Controllers/API/ReferenceDataApiController.php`
  - `index()`: accounts get `alias`; payees get `alias` and `category_id` (via eager-loaded `config`, columns
    `id,name,alias,active,config_type,config_id`); new `investments` block built from `Investment::query()`
    (`id,name,symbol,currency_id,active`); delta mode also returns `ids.investments`. Docblock notes the payee
    `category_id` lag in delta mode.
  - `usage()` (new): returns `ReferenceUsageService::forUser()`.
- `app/Services/ReferenceUsageService.php` (new): four queries (standard pairs, investment pairs, payee→category top 5,
  deferred categories). Constants `WINDOW_MONTHS = 12`, `CATEGORIES_PER_PAYEE = 5`. Only non-scheduled transactions.
- `routes/api.php`: `GET /reference-data/usage` named `api.v1.reference-data.usage` (next to `reference-data`).
- `app/Http/Controllers/API/MetaApiController.php`: `features.reference_usage = true`.
- Tests: `tests/Feature/API/V1/ReferenceDataApiV1Test.php` (two new tests: new fields + usage aggregation incl. 12-month
  window and per-user isolation), `tests/Feature/API/ApiAbilityEnforcementTest.php` (usage route, `read` ability).
- Docs: `.ai/docs/features/mobile-app-support/architecture.md` ("Reference data and summary") describes the new fields,
  the usage endpoint and the ranking rules for a native client.

Frontend, `resources/js/shared/lib/select-data/`:

- `ranking.js`: pure helpers, no Vue/axios. Exports: `GROUP_SIZE`, `normalize`, `aggregateUsage`, `standardUsage`,
  `investmentUsage`, `investmentAccountUsage`, `rankByUsage` (returns `{mostUsed, recent, fill}`), `searchEntities`
  (adds `matchedVia: 'name'|'alias'`), `categoryFullName`, `suggestCategories`, `applySavedTransaction`.
- `index.js`: re-exports `ranking.js`; `loadSelectData()` (memoized promise of `{accounts, payees, investments, tags,
  categories (with computed full_name), currencies: Map by id, usage}`; a failed load is not remembered so a retry
  refetches); `upsertEntity(kind, row)` where `kind` is a key of the data object (`accounts`, `payees`, ...).
- `ranking.test.js` (11 node tests), `package.json` (`{"type":"module"}`).

### Phase 2: createLocalSelect and the standard form

- `resources/js/shared/lib/tom-select/index.js`: new exports `createLocalSelect`, `lockSelect`, `unlockSelect`.
  `createLocalSelect(element, {suggestions, search, placeholder, allowClear, renderOption, onSelect, onClear, onChange})`:
  - `suggestions(): [{id, label, items:[{id,text,...}]}]` for an empty search; `search(term): items[]` once typing
    (already matched and ranked by the caller). Both run on every open/keystroke.
  - Implemented through Tom Select's `load` callback running **synchronously** (`clearOptions()` keeps the selected
    option, `clearOptionGroups()`, `addOptionGroup`, `addOption({... optgroup})`, `callback()`), `lockOptgroupOrder`,
    and an `instead search` hook that returns only the ids of the current list, in order (same trick as the remote select).
  - Default option render: name, muted alias when `matchedVia === 'alias'`, "inactive" badge when `active === false`.
  - Also supports `multiple`/`create` (added for tags in Phase 3) and `searchLabel: () => string` (label of the search
    results group, default "Matches"; the investment form uses it for the currency suffix).
  - `setSelected`/`clearSelect` (with `silent`) work on the returned instance. Element ids and `.tomselect` access are kept.
- `resources/sass/_custom.scss` (`.ts-dropdown.ts-dropdown-grouped`, class added by `createLocalSelect`): `.ts-dropdown-content { max-height: min(60vh, 22rem) }` and
  `.optgroup-header` styling, both scoped to the grouped class.
- `resources/js/transactions/components/form/TransactionFormStandard.vue`:
  - `created()` sets `this.selectData = null` (non-reactive on purpose) and calls `loadReferenceData()`.
  - `loadReferenceData()` sets `this.selectDataReady` (promise), stores `this.selectData`, unlocks locked selects, sets
    `selectDataError` on failure; `retrySelectData()` re-runs it and re-applies the presets.
  - Helpers: `getEntities(type)`, `findEntity`, `toSelectItem`, `selectableEntities(type)` (excludes the other side's id),
    `suggestionGroups(type)` (5+5 + fill via `standardUsage` + `rankByUsage`; the payee side is anchored on the selected
    account), `searchEntities(type, term)`.
  - `initializeAccountSelect(type)` now builds a `createLocalSelect` (locked with "Loading…" while data is missing).
    `onAccountPicked(type, item)` = user pick (handles reactivation); `onAccountSelected(type, id)` reads currency
    (`selectData.currencies`) or payee default category (`selectData.categories[].full_name`) locally and returns a
    promise chained on `selectDataReady`; `getDefaultAccountDetails` resolves names locally (no GET); `presetAccount`
    unchanged in behaviour. `getAccountApiUrl` was removed.
  - `markFormClean` waits on `selectDataReady` too (in `mounted()` and the `transaction` watcher), so `data-loaded` implies ready.
  - Inactive handling: data `reactivated`, `deactivateAfterSave`; `restoreReactivated()` after successful create/edit
    save; `notifyReactivatedStayActive()` on cancel; checkbox `#checkbox-deactivate-reactivated` in the Details card.
  - `setPayee()` upserts the new/reactivated payee into the local data, then presets it.
  - After a successful **create** save: `applySavedTransaction(this.selectData.usage, {...transaction, config_type: 'standard'})`.
- `lang/en.json`, `hu.json`, `fr.json`, `pl.json`: nine new keys (raw translations, need native review, appended at the
  end of each file, not in sorted position): "Most used", "Recent", "Matches", "Loading…", "Could not load the account and
  payee lists.", "Retry", "Deactivate again after saving: :names", "Could not reactivate :name.", "Reactivated items stay
  active, because the transaction was not saved." ("Other" and "inactive" already existed.)
- Tests: new browser test "finds an inactive payee by its alias, reactivates it for the transaction and deactivates it
  again after saving" in `tests/PEST/Pages/Transactions/TransactionFormStandardModalTest.php`; test helper
  `standardStandaloneSwitchType` in `TransactionFormStandardStandaloneTest.php` now waits for SweetAlert to return focus
  to the clicked type button (see gotchas).
- Docs: `.ai/docs/features/remote-select/architecture.md` has a "Local selects" section.

Verified at the end of Phase 2: all `TransactionFormStandard*` browser tests, plus `TransactionTemplate`,
`ManualEntryClosesDocument`, `AccountShow`, `PayeeMerge` (14 passed), API tests, ranking tests, Pint, PHPStan on the new
PHP files. `ModalStandard.vue` needed **no change**: the form inside it is mounted at page load and starts the preload itself.

### Phase 3: categories and tags in item rows

Done in `7838cce5` (`TransactionItem.vue` uses `createLocalSelect` for categories and tags). See the commit for details.

### Phase 4: investment form

`resources/js/transactions/components/form/TransactionFormInvestment.vue` mirrors the standard form:

- `created()` sets `this.selectData = null` and calls `loadReferenceData()` (`selectDataReady`, `selectDataError`,
  `retrySelectData()`, Retry alert in the Details card). Selects are locked with "Loading…" until the data arrives.
- Account and investment selects are `createLocalSelect`s. `accountCandidates()` / `investmentCandidates()` filter by the
  other side's currency (`investment_currency` / `account_currency`; all when nothing is selected) and exclude inactive
  ones unless searching. Investments are mapped with `alias = symbol`, so search matches the symbol; the option render
  is `name (symbol)` plus an "inactive" badge, everything escaped.
- Ranking: `investmentAccountUsage(usage, {investmentId, transactionType})` (new optional `transactionType`, falls back
  to all types when that type has no history; node test added) and `investmentUsage(usage, {accountId})`, then
  `rankByUsage`. Groups: Most used, Recent, Other.
- Group labels: `groupLabel()` adds " - matching investment currency" (account select) or " - matching account
  currency" (investment select) to "Other" and "Matches" while the other side is selected.
- Currencies come from `selectData.currencies`; no GETs on selection, the "set currency immediately" workaround is gone.
  Presets (`getDefaultAccountDetails`, `getDefaultInvestmentDetails`, URL params) resolve locally on `selectDataReady`;
  `markFormClean` waits on it in `mounted()` and the `transaction` watcher.
- Inactive entities: `reactivateIfInactive()` / `setActive()` use `api.v1.account-entities.patch-active` for accounts and
  `api.v1.investments.patch-active` for investments; `#checkbox-deactivate-reactivated`, `restoreReactivated()` after a
  successful create or edit, `notifyReactivatedStayActive()` on cancel.
- After a successful create: `applySavedTransaction(usage, {...transaction, config_type: 'investment'})` (not on edit).
- Translations (all four `lang` files, raw hu/fr/pl): "Could not load the account and investment lists.", "matching
  investment currency", "matching account currency".
- Verified: all `TransactionFormInvestment*` and `TransactionFormStandard*` browser tests, `TransactionTemplate`,
  `ManualEntryClosesDocument`, `AccountShow`, ranking node tests, ESLint, translations check. The existing investment
  tests needed no changes. No new browser test was added for the investment form.
- Also in this work: `playwright` bumped to `^1.65.0` (separate commit `4963f706`).

### Deviations from the original plan (already reflected in the spec)

- Usage is one windowed dataset (12 months) instead of all-time per-entity counts plus separate pair counts.
- `reference-data` has no category `full_name`; the client computes it from `parent_id`.
- The category fallback uses totals from the stored top-5-per-payee rows (no global category counts exist).
- No `ModalStandard` warm-up (not needed).

## 3. Gotchas learned (read before touching the selects)

1. **Tom Select 2.6.2 internals**: `loadedSearches` is only ever reset (never set) in core, so `load()` always runs;
   `clearOptions()` keeps the selected options; groups need `lockOptgroupOrder: true` to keep insertion order; the
   `instead search` hook is how the dropdown is restricted to the current list. Look at
   `node_modules/tom-select/src/tom-select.ts` (`load`, `loadCallback`, `refreshOptions`) before changing this.
2. **Focus-restore race in browser tests**: SweetAlert returns focus to the previously focused element (the clicked
   transaction-type button) ~100 ms after it closes. A dropdown opened in that window loses focus and closes. The old
   remote select hid this because it opened slowly. Real users are not affected. Any test that opens a select right
   after `confirmSwal()` must first wait for focus to return (see the helper in `TransactionFormStandardStandaloneTest.php`).
   `confirmSwal()` itself only waits for `.swal2-container` to disappear.
3. **Tests wait on `data-loaded`/`.tomselect`**: `standardStandaloneReady()` only waits for the `.tomselect` instances, not
   the data. The selects are locked until the data arrives; the first interaction can race with it. Prefer waiting for
   `#transactionFormStandard[data-loaded="true"]` in new tests.
4. `this.selectData` and `this.selectDataReady` are assigned in `created()` and are **not in `data()`**: not reactive by
   design (large object, read on demand by select callbacks). Do not read them in templates/computed expecting updates.
5. Dates: `applySavedTransaction` takes the **raw** API transaction (date string) and reads `config`,
   `transaction_type`, `transaction_items[].category_id`. Call it **before** `processTransaction()` mutates the object, or
   pass a copy. `processTransaction` converts the date to a `Date`; `toYmd` in `ranking.js` handles both.
6. Edits must not call `applySavedTransaction` (double counting).
7. The `payee.category_id` default category may lag in `updated_since` delta mode (see spec 5.1). The forms use the full
   load, so this only matters to the mobile client.
8. Translations: keys are not in sorted order in the lang files, so appending is fine; `check-translations.php` flags
   missing keys per language.
9. Sail must be running for tests; Pest browser tests need a fresh `npm run build` after any JS/Vue/SCSS change.

## 4. What is left, per phase

Phases 3-5 are done (see section 2 and above). Each remaining phase is independently shippable.

### Phase 5: "Apply usual items" redesign (done)

- Backend: `PayeeCategoryStatsService` also returns `transaction_count` (distinct transactions) and `avg_amount` per
  category; existing keys unchanged. Test assertions added in `tests/Feature/PayeeStatsApiControllerTest.php`.
- Helper `buildUsualItems({stats, deferredIds, totalAmount, precision, defaultCategoryId})` in
  `resources/js/shared/lib/select-data/usualItems.js` (6 node tests in `usualItems.test.js`):
  drops deferred categories and non-positive counts, drops categories under a 10% share (`MIN_SHARE`), **no fixed cap**
  (the share threshold bounds it to 10 items; a top-4 cap was tried and removed as arbitrary). Splits by `usage_count`
  share, rounds with `decimal.js` to `precision` places (precision 0 and total >= 1000 rounds to steps of 10). The
  remainder goes to the default category if among the items, else the largest item; non-positive items are dropped and
  the amounts sum exactly to the total.
- `TransactionItemContainer.vue` uses the helper (new props `amountPrecision`, `defaultCategoryId`); confirm dialog and
  loading/empty/error states kept. `TransactionFormStandard.vue` computes `amountPrecision` from the amount currency's
  `generic_decimal_precision`, falling back to Intl's default digits for that currency when null.
- `avg_amount` is returned but unused (share-based distribution; refine only if the owner asks).
- Not covered by a browser test (none exists for the button).

### Phase 6: measure and decide on caching (D7)

1. On realistic data (the owner's database or a large seeded one) measure: response time and gzipped size of
   `GET /api/v1/reference-data` and `/reference-data/usage`, and time-to-interactive of the selects. If the usage payload is
   large, tighten the caps (`CATEGORIES_PER_PAYEE`, `WINDOW_MONTHS`) in `ReferenceUsageService`.
2. If `reference-data` is slow, add a per-user version counter cache following
   `app/Services/CategoryWaterfallCacheService.php` (`Cache::increment` on a version key, version in the cache key) with
   `#[ObservedBy]` observers like `app/Observers/CategoryObserver.php`. `AccountEntity`, `Account`, `Payee`, `Tag`,
   `Investment` and `Currency` have no such observers today, and merges/imports only invalidate if they fire Eloquent
   events (otherwise bump explicitly). The existing ETag hashes the live data, so a cache must not break it. Add
   invalidation tests (create/rename a payee, category and tag; the next response must change).
3. Confirm with the mobile app owners that the client ignores unknown keys (the app source is **not** in this repository;
   the contract is `.ai/docs/features/mobile-app-support/architecture.md`, the OpenAPI spec, and the API tests).

## 5. Known gaps and open questions

- Not yet tested: reactivating an **account** or an **investment** (only a payee is covered, in the standard form), the
  group-label suffix in the investment form, the Retry flow after a failed preload (both forms), and the
  planned "enter two transactions in one modal session and see the ranking update" browser test (the unit test for
  `applySavedTransaction` exists; the UI-level one does not).
- Deferred categories: hidden from both suggestions and search for the selected payee (decided, spec 6.2 item 4).
- The taller dropdown applies only to `createLocalSelect()` selects (`ts-dropdown-grouped` class, D9).
- The raw hu/fr/pl translations need native review before release.
- Payload size and response time are unmeasured (Phase 6).
- Cosmetic: `reactivated` entries are restored only on a successful save; closing the browser tab leaves them active.
- Pre-existing, unrelated: `resources/js/transactions/components/display/ShowStandard.vue:420` has an ESLint error (unused
  `side` argument); `.ai/docs/product-context.md:54` still says "No native mobile application" (stale).
- `ModalStandard.vue` mounts the standard form at page load, so every page with the modal now triggers the two preload
  requests at load even if the modal is never opened. If that becomes a concern, defer `loadReferenceData()` until the
  modal is first shown (the form's `created()` is where it starts today).

## 6. Test inventory (files that exercise these selects)

Pest browser: `tests/PEST/Pages/Transactions/TransactionFormStandardStandaloneTest.php`,
`TransactionFormStandardModalTest.php`, `TransactionFormInvestmentStandaloneTest.php`, `TransactionFormInvestmentModalTest.php`,
`tests/PEST/Pages/TransactionTemplates/TransactionTemplateTest.php`, `tests/PEST/Pages/AiDocuments/ManualEntryClosesDocumentTest.php`,
plus helpers in `tests/PEST/Support/BrowserTestCase.php` (`chooseTomSelectOption`, `searchTomSelect`,
`assertTomSelectValues`, `clearTomSelect`, `waitUntil`, `confirmSwal`; they rely on `#<id>-ts-dropdown [data-value]`,
`document.querySelector('#id').tomselect` and `.ts-wrapper .ts-control` / `.dropdown-input`, so keep those ids and markup).
Feature: `tests/Feature/API/V1/ReferenceDataApiV1Test.php`, `tests/Feature/API/ApiAbilityEnforcementTest.php`.
Unit (node): `resources/js/shared/lib/select-data/ranking.test.js`, `resources/js/shared/lib/tom-select/queryString.test.js`.
