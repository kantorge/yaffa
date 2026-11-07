# Local Form Selects: Status and Handoff

Read [`specification.md`](specification.md) first (goals, decisions D1-D9, API contract, ranking rules). This file says
what exists, what is left, and how to do the remaining phases without the original conversation.

Work branch: `feature/local-transaction-form-selects` (created from `develop`). At the time of writing, Phases 1 and 2
were implemented but **not committed**; run `git status` / `git log` to see the real state before starting.

## 1. Environment and commands

All PHP/Node commands run through Sail (see root `CLAUDE.md`). If Sail is not running: `vendor/bin/sail up -d`, wait
about 20 seconds for MySQL.

| Task | Command |
| --- | --- |
| JS unit tests (ranking) | `node --test resources/js/shared/lib/select-data/ranking.test.js` (the folder's `package.json` `{"type":"module"}` lets plain node load the ESM files) |
| API feature tests | `vendor/bin/sail artisan test --compact tests/Feature/API/V1/ReferenceDataApiV1Test.php` and `--filter=ApiAbilityEnforcement` |
| Build assets (needed before browser tests, and no `public/hot` may exist) | `vendor/bin/sail npm run build` |
| Pest browser tests | `vendor/bin/sail php vendor/bin/pest --testsuite=Browser --filter="TransactionFormStandard"` (about 90 s for the standard form files) |
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
  - **Single select only** (no `multiple`, no `create`). See Phase 3.
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

Each phase is independently shippable. Do them in order unless noted; Phase 5 is independent.

### Phase 3: categories and tags in item rows (`TransactionItem.vue`)

Goal (spec 6.2): once a payee is selected, the item category dropdown lists "Usual for this payee" first, any category
is findable by typing, tags search locally, no requests.

Starting point (from the read-through of the original code; re-read the file, it was not changed yet):
`resources/js/transactions/components/form/TransactionItem.vue` builds two `createRemoteSelect`s in `mounted()`:
the category select (`/api/v1/categories`, params `{q, payee: this.payee}`, label `full_name`; `onChange` tracks whether a
recommended category was accepted and emits `update:category_id`; preset via non-silent `setSelected` from the saved
category or the AI recommendation) and the tag select (multi, `create: true`, `/api/v1/tags` `{q}`; emits `update:tags`
with existing tag ids and new tags as typed text; initial tags via `setSelected`). It also clears and re-presets
selects in a later code path (around lines 496-518) and destroys both in `beforeUnmount`. Items are rendered by
`TransactionItemContainer.vue`, which passes the `payee` prop (`payeeId`).

Steps:

1. **Extend `createLocalSelect` for multi-select with creation** (it is single-select today). Add `multiple` (set
   `element.multiple = true` before init, `mode: 'multi'`, `remove_button` plugin with the same title as
   `createRemoteSelect`) and `create` (copy `create`, `createFilter` and the `option_create` render from
   `createRemoteSelect`). In multi mode `onChange` receives the array, as in the remote version. Keep single-select
   behaviour and the default render unchanged. Alternatively keep tags on `createRemoteSelect` and only do categories;
   decide with the owner. The spec prefers local tags (D2).
2. In `TransactionItem.vue` call `loadSelectData()` (shared memoized promise, no extra requests when the form already
   loaded it). Lock the item selects until it resolves (`lockSelect`/`unlockSelect`), then preset.
3. Category select: `suggestions` = one group "Usual for this payee" built from
   `suggestCategories(data.categories, data.usage, {payee})` where `payee` is the preloaded payee row for
   `this.payee` (`data.payees.find(p => p.id === Number(this.payee))`; `payee.category_id` is the default). Items are
   `{id, text: full_name}`. `search(term)` = `searchEntities` over categories using `full_name` as the searched name
   (build `{id, name: c.full_name, active}` objects). The select must re-evaluate when the `payee` prop changes; since
   suggestions are computed on every open, nothing else is needed. Inactive categories are excluded from suggestions;
   inactive categories are not searchable (as before). Categories deferred for the selected payee
   (`data.usage.deferred_categories` rows with that `payee_id`) are also filtered out of `search(term)` (owner decision).
4. Keep every existing behaviour of the row: recommendation preset and "learn recommendation" tracking, the emitted events,
   `learnRecommendation`, the silent clear/preset path, and destroying selects in `beforeUnmount`. Note: **category
   suggestions must not include or hide the AI-recommended category differently than before.**
5. Tags: local search over `data.tags` (active only, `id` + `name`); a typed new name still creates a tag (value = raw
   text). After a successful save, tags created on the fly are not in the preloaded list: acceptable (reload refetches);
   optionally `upsertEntity('tags', ...)` is not possible before the server assigns ids, so skip.
6. `applySavedTransaction` already updates `payee_categories`, so the next transaction in the session ranks correctly
   (it is called from `TransactionFormStandard.vue`'s create-success handler).
8. Tests: update the Pest browser tests that pick categories and tags
   (`TransactionFormStandardStandaloneTest.php`: "creates a new tag and keeps existing ones in a transaction item",
   "does not select the highlighted item category when the dropdown..."; the standard modal and template tests) and add
   one that asserts the "Usual for this payee" group order for a seeded payee with history. Add ranking unit tests if you
   change `suggestCategories`.

### Phase 4: investment form (`TransactionFormInvestment.vue`)

Goal: account and investment selects local, currency from preloaded data, no GETs on selection.

Starting point (current code): account select `createRemoteSelect` on `/api/v1/accounts/investment` with params
`{q, transaction_type, currency_id: this.investment_currency?.id}`; investment select on `/api/v1/investments` with
`{query, active: 1, currency_id: this.account_currency?.id, limit: 10, sort_by: 'name', sort_order: 'asc'}`, `mapResult`
adding `symbol` and `currency_id`, custom option render `name (symbol)`. On investment select it sets
`investment_currency = {id}` immediately (to avoid a filtering race) and then GETs `investments.show`; on account select
it GETs `/accounts/{id}` for the currency. Presets: `getDefaultAccountDetails`/`getDefaultInvestmentDetails` (GETs) and
`created()` reads `?account=` and `?investment=` from the URL. `mounted()` finishes with
`Promise.all([accountReady, investmentReady]).then(markFormClean)`; the `transaction` watcher does the same;
`clearInvestmentDropdown()` clears the investment select.

Steps (mirror what Phase 2 did in `TransactionFormStandard.vue`; use it as the reference implementation):

1. In `created()` set `this.selectData = null` and add `loadReferenceData()` / `selectDataError` / retry exactly like the
   standard form (consider extracting the shared pieces into a small mixin or helper in `shared/lib/select-data` only if
   it does not need reactive state; two copies are acceptable, a third is not).
2. Accounts: candidates = **active accounts whose `currency_id` equals the selected investment's currency** (all accounts
   when no investment is selected). Rank with `investmentAccountUsage(usage, {investmentId})` + `rankByUsage`. The old
   endpoint accepted `transaction_type` for ranking; add an optional transaction-type filter to the investment helpers if
   you want parity (the `type` field is on each `investment` usage row). Verify against
   `AccountApiController::getAccountListForInvestments` that no further restriction (e.g. account type) applied.
3. Investments: candidates = **active investments whose `currency_id` equals the selected account's currency** (all when
   no account). Rank with `investmentUsage(usage, {accountId})` + `rankByUsage`. Items carry `symbol`; give the select a
   `renderOption` showing `name (symbol)` with `escape()` on every user value (see the existing render). Searching should
   also match the symbol: extend `searchEntities` (or map `alias` to symbol) so the symbol is matched, keeping the
   alias-badge logic sane.
4. Currency lookup is local: `currencies.get(entity.currency_id)` replaces both GETs, and the "set currency immediately"
   workaround is no longer needed.
5. Inactive entities: accounts via `api.v1.account-entities.patch-active`, **investments via
   `api.v1.investments.patch-active`** (see `routes/api.php` ~line 265 for the body). Reuse the checkbox/restore pattern
   from the standard form. Default is active-only, inactive found by search (old behaviour: `active: 1`).
6. Presets: replace the GETs with local lookups chained on `selectDataReady`; keep the URL param presets
   (`?account=`, `?investment=`) and `data-loaded` semantics (`Promise.all` includes `selectDataReady`).
7. After a successful create save call `applySavedTransaction(this.selectData.usage, {...response.data.transaction,
   config_type: 'investment'})` (not on edit).
8. Keep: the `existingPriceForDate`/store-price logic, `transactionTypeChanged`, schedule handling, `isDirty`.
9. Tests: run `TransactionFormInvestmentStandaloneTest.php` and `TransactionFormInvestmentModalTest.php`, fix selector/timing
   assumptions (see gotchas 2 and 3), add translation keys if you add strings.

### Phase 5: "Apply usual items" redesign

Goal (D6): fewer, realistic categories and amounts without invented fractions.

Starting point: `resources/js/transactions/components/form/TransactionItemContainer.vue`,
`applyStandardTransactionItems()` calls `getPayeeCategoryStats(payee, transactionType)`
(`resources/js/payee/payee-stats-api.js`, `GET /api/v1/payees/{id}/category-stats`, backend
`PayeeStatsApiController::categoryStats` + `PayeeCategoryStatsService`, 6 months, response `{payee_id, payee_name,
categories:[{category_id, usage_count, category_full_name}], deferred_category_ids, period_months}`) then
`buildItemsFromStats()` (proportional split by `usage_count`, `roundAmount` to `ITEM_AMOUNT_SCALE = 4` decimals, the
remainder goes to the most used category, `.filter(amount > 0)`).

Steps:

1. **Backend** (`PayeeCategoryStatsService::buildAggregatedStatsQuery`, ~line 253): additionally return `transaction_count`
   (`count(distinct transaction id)`, since `usage_count` counts items) and `avg_amount` per category (average item
   amount). Keep existing keys unchanged. Add feature tests next to the existing category-stats tests and keep
   `ApiAbilityEnforcementTest` green. (Backend changes need owner awareness: `resources/js/CLAUDE.md` says the frontend
   scope does not modify PHP; this task explicitly includes it.)
2. **Pure helper** (new file next to the container, e.g. `resources/js/transactions/components/form/usualItems.js`, with a
   node test file like `ranking.test.js` and its own folder-level `package.json` only if needed to run under plain node;
   otherwise place it under `resources/js/shared/lib/select-data/`): `buildUsualItems({stats, deferredIds, totalAmount,
   precision, defaultCategoryId})`:
   - drop deferred categories and non-positive counts;
   - drop categories with a share under 10% (named constant), keep at most the top 4 by usage;
   - split `totalAmount` proportionally and round each amount to `precision` decimal places with `decimal.js`
     (`toDecimalPlaces`); when `precision` is 0 also round to a "nice" step (10 units if the total is at least 1000,
     else 1);
   - the remainder (total minus the sum) goes to the item of `defaultCategoryId` if present among the items, otherwise to
     the largest item; drop items that end up at or below 0; the sum of the amounts must equal the total exactly.
   - Tests: HUF-like (precision 0, e.g. 12 345), EUR-like (precision 2), a single category, a remainder that would go
     negative, default category present vs absent.
3. **Precision source**: the account currency's `generic_decimal_precision` (nullable). Pass it from
   `TransactionFormStandard.vue` into `TransactionItemContainer.vue` as a new prop (the form already has
   `from.account_currency` and `to.account_currency`; the currency used for the amount is `from` for withdrawal/transfer
   and `to` for deposit, see `ammountFromCurrencyLabel`). Currency objects come from the preloaded
   `reference-data` currencies, so `generic_decimal_precision` is present. When it is null use the same fallback as the
   money formatting in `resources/js/shared/lib/i18n/format.js` (check what it does; do not invent a default).
4. Pass the payee default category: `payeeCategory.id` in `TransactionFormStandard.vue` (new prop on the container).
5. Replace `ITEM_AMOUNT_SCALE`/`roundAmount`/`buildItemsFromStats` in the container with the helper. Keep the confirm
   dialog for replacing existing items, the loading/empty/error states (spinner, "No statistics available" dialog, error
   toast) and the emitted `replaceTransactionItems`.
6. Distribution is share-based (`usage_count`). `avg_amount` is returned for possible refinement; do not use it unless the
   owner asks.
7. Tests: node tests for the helper; update or add a browser test only if one covers the button today (search
   `tests/PEST` for "usual items").

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

- Not yet tested: reactivating an **account** (only a payee is covered), the Retry flow after a failed preload, and the
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
