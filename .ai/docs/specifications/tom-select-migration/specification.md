# Tom Select Migration Specification

## 1. Purpose

Replace Select2 (jQuery plugin, `select2@4.0.13` + `select2-bootstrap-5-theme`) with
[Tom Select](https://tom-select.js.org/) (`tom-select@^2.6`, no jQuery dependency) across the
application, with **functional and visual parity**. In the same effort, move every browser test
that exercises these widgets from Laravel Dusk to **Pest 5 browser tests** (Playwright), and
remove every Select2 reference from the codebase.

This folder is the implementation handoff. Read in this order:

| File | Audience | Contents |
| --- | --- | --- |
| `specification.md` (this file) | everyone | goals, architecture, phases, acceptance criteria |
| [`inventory.md`](inventory.md) | frontend agent | every Select2 instance and the behaviour it must keep |
| [`testing.md`](testing.md) | testing agent | Pest 5 setup, Dusk → Pest conversion rules, test inventory |

## 2. Background

- Select2 is used in 24 widget definitions: 11 in plain page scripts and 13 in Vue components
  (full list in `inventory.md`). Two of them (P5, P6) disappear with the payee form unification
  (FR-8), so 22 are left to migrate. All are driven imperatively through jQuery
  (`$(el).select2({...})`) and all but a few use AJAX (remote) search.
- Vue components bridge Select2 into Vue by hand: they dispatch synthetic native `change`
  events so `v-model` picks up the value, trigger fake `select2:select` events to run
  side effects when presetting values, and call `select2('destroy')` in `beforeUnmount`.
- Custom support code that exists only because of Select2:
  - `resources/js/shared/lib/select2/index.js`: init plus a monkey-patch of Select2's internal
    AMD `SearchDropdown` module to add a search-input placeholder.
  - `resources/js/shared/lib/i18n/select2.js`: lazy-loads Select2's own locale bundles
    (en/fr/hu/pl).
  - About 100 lines of dark-mode overrides in `resources/sass/_custom.scss`, because the
    Select2 theme hardcodes light-mode colours.
- The Dusk tests that touch these widgets are the main source of browser-test flakiness. They
  depend on a forked macro package (`roquie/laravel-dusk-select2`, served from
  `github.com/kantorge/laravel-dusk-select2`) that includes stale-element retry loops, and several
  tests wrap whole flows in `retry(3, ...)`.
- **Known defect (security):** the investment select in `TransactionFormInvestment.vue` builds
  option HTML from `item.name`/`item.symbol` unescaped and disables escaping
  (`escapeMarkup: m => m`), so an investment name containing HTML is rendered as markup. The
  migration must fix this (see FR-7).

## 3. Goals

- G1. Replace every Select2 instance with Tom Select, keeping current behaviour
  (see `inventory.md`).
- G2. Keep the current look: CoreUI/Bootstrap 5 form-control styling, in light **and** dark mode.
- G3. One shared, jQuery-free factory module used by both plain page scripts and Vue components.
- G4. Introduce Pest 5 (`pestphp/pest`, `pestphp/pest-plugin-laravel`,
  `pestphp/pest-plugin-browser`) and convert every Dusk test file that touches Select2 into
  Pest browser tests.
- G5. Remove all Select2 packages, code, styles, test helpers and the Composer VCS repository
  entry.
- G6. Fix the unescaped investment option rendering.

## 4. Non-Goals

- No backend changes beyond the payee form unification (FR-8, §7). The `name AS text` aliases in `AccountApiController` and
  `TagApiController` exist for Select2's `{id, text}` shape, but they are part of the public
  `/api/v1` contract and stay. Tom Select is configured to read them.
- No conversion of Dusk tests that don't touch Select2. They stay on Dusk, and the Dusk CI
  workflows stay for them.
- No automatic conversion of existing PHPUnit unit/feature tests to Pest. Pest runs them
  unchanged. A one-time scan (Phase 5) reports high-value candidates, and the owner decides which
  ones to convert.
- No new reusable Vue wrapper component (e.g. `RemoteSelect.vue`). Vue components call the shared
  factory in `mounted()`, the same shape as today. That component can be a later refactor.
- No redesign of which fields are selects, what they search, or what they submit.
- No removal of jQuery. DataTables, jsTree and `$.ajax` calls still need it.

## 5. Decisions

| # | Decision | Rationale |
| --- | --- | --- |
| D1 | Tom Select `^2.6`, imported as an ES module | Maintained, no jQuery, supports remote load, multi, create, custom render, Bootstrap 5 theme |
| D2 | One shared factory in `resources/js/shared/lib/tom-select/` | 24 definitions repeat the same AJAX/placeholder/clear config; one place for escaping, i18n and caching rules |
| D3 | Only Tom Select's bundled plugins: `clear_button`, `remove_button`, `dropdown_input` | `dropdown_input` puts the search box in the dropdown, which matches the current Select2 UX |
| D4 | No client-side result caching by default | Several selects filter by state that changes at runtime (other side's currency, other select's value, current transaction type). Tom Select caches per query string by default, which would show stale results. Select2's `cache: true` never behaved that way here. |
| D5 | Server result order is kept (no client re-scoring of remote results) | The API already searches and orders results |
| D6 | Pest browser tests live in `tests/PEST/`, a separate test suite excluded from the default run | `tests/Browser/` belongs to Dusk (`artisan dusk` would try to load Pest files). Browser tests need built assets and Playwright, so `artisan test` must not run them implicitly. |
| D7 | Only Tom Select's escaped rendering (`escape()`) is used for any user-provided text | Fixes the investment XSS and prevents the pattern from coming back |
| D8 | The full-page payee create/edit form is removed; its routes redirect to the payee list, which opens `PayeeForm.vue` in its modal (FR-8) | Two implementations of the same form had already drifted apart. The modal has every field the page has, plus duplicate-name detection. Deleting `payee/form.js` also means one fewer Select2 page to migrate. |

## 6. Functional Requirements (frontend)

### FR-1: Shared factory

Create `resources/js/shared/lib/tom-select/index.js`, which replaces
`resources/js/shared/lib/select2/`. It exports:

- `createRemoteSelect(element, options) → TomSelect`. Options cover what the inventory needs:
  - `url` (string or function returning a string): evaluated on every request so that
    type-dependent endpoints work.
  - `params(term) → object`: extra query parameters, evaluated on every request.
  - `mapResult(item) → { id, text, ...extra }`: default `item => ({ id: item.id, text: item.text ?? item.name })`.
  - `filterResults(results) → results`: for "exclude the other select's value" rules.
  - `placeholder`, `multiple`, `allowClear` (default `true`), `dropdownParent`.
  - `create` (tags), `renderOption`/`renderItem` (escaped render hooks).
  - `selectOnClose` (default `false`).
  - `onSelect(item)`, `onClear()`, `onChange(values)` callbacks.
- `setSelected(ts, items, { silent = false })`: adds option(s) and selects them. It replaces the
  `append(new Option(...)).trigger('change').trigger({type: 'select2:select'})` pattern. When
  not silent it runs the same `onSelect` path a user selection runs, because presets depend on
  those side effects (currency lookups and so on).
- `clearSelect(ts, { silent = false })`: clears the value and options (replaces
  `.empty().val(null).trigger('change')`).

Required factory behaviour:

1. Requests go through `window.axios` (it already carries the CSRF header). Drop the `_token`
   query parameter wherever it only existed to satisfy `$.ajax`, unless the endpoint needs it.
   None is known to need it.
2. Requests are debounced 150 ms (Select2's `delay: 150`).
3. **Opening the dropdown with an empty search runs a request and shows results**, as Select2
   does today. Each instance sends the same "empty term" parameter it sends today (`undefined`,
   `''` or `'*'`, see inventory).
   **Query-string parity is behaviour, not cosmetics.** Select2 serialized through `jQuery.param`,
   which *omits* `undefined` values and sends `null` as an empty string (`key=`). Axios omits
   both. Backends branch on presence: `GET /api/v1/accounts` without `q` returns the user's
   **most-used accounts for the transaction type** (`AccountApiController::getList`,
   `$request->has('q')`), and with `q` (even empty) it runs a search instead. So the factory
   must serialize params the way `jQuery.param` does: omit `undefined`, send `null` as empty.
   Put this in a small pure helper and cover it with one `node:test` file next to the factory,
   following the precedent in `resources/js/investments/lib/investmentReturn.test.js`.
4. No query cache (D4): every open or keystroke refetches, and stale options that aren't
   selected are dropped.
5. `allowClear` shows a clear button. **Clearing always runs `onClear` exactly once**, whether
   the user clicks the clear button, removes the last item, or code calls `clearSelect`. This
   merges today's separate `select2:unselect` and `select2:clear` handlers.
6. The underlying `<select>` keeps its value in sync, so HTML form submission (merge pages,
   payee form page) and code reading `select.value` / `selectedOptions` still work.
7. "No results", "Searching…" and the create option text are translated with `__()`. The
   dropdown search input shows the placeholder `__('Type to search...')` (this key already
   exists in all four `lang/*.json` files). The `select2/dist/js/i18n/*` bundles and the AMD
   monkey-patch are deleted.
8. `selectOnClose: true`: when the dropdown closes by blur or Tab while an option is highlighted
   from a non-empty search, that option is selected. This keeps Select2's `selectOnClose` for
   the one instance that still uses it after Phase 0b: the transaction item category (V5), used
   for fast keyboard entry.
9. Keyboard: Enter selects, Esc closes the dropdown **without** closing a surrounding
   CoreUI/Bootstrap modal, and arrow keys move through options.
10. Instances inside modals pass the modal element as `dropdownParent`, so the dropdown stays
    inside the modal's focus trap and isn't clipped by `.modal-body` overflow.

### FR-2: Vue integration pattern

For each Vue component in the inventory:

- Create the instance in `mounted()` and call `ts.destroy()` in `beforeUnmount()`.
  `TransactionItem.vue` currently leaks listeners through `.off()` ordering, and
  `PayeeForm.vue`, `BudgetForm.vue`, `CategoryLearningForm.vue` and
  `FindTransactionSelectCard.vue` have no teardown today. Add teardown to all of them.
- **Remove `v-model` from Tom Select–managed `<select>` elements.** Write the form field
  explicitly in `onSelect`/`onClear`/`onChange`. Don't dispatch synthetic `change` events to
  feed `v-model`.
- Any `isDirty()`/`markFormClean()` baseline that waits for presets (standard and investment
  transaction forms) still receives a promise that resolves after the preset **and its
  side-effect requests** have settled.

### FR-3: Re-initialisation on transaction-type change

In `TransactionFormStandard.vue`, changing the transaction type swaps the endpoint, placeholder
and request parameters of `#account_from`/`#account_to`. Keep the current behaviour: clear the
value, destroy the instance and create it again with the new config. The factory re-reads `url`,
`params` and `placeholder` from functions, so updating in place is also acceptable if it's
simpler. The visible result must be the same.

### FR-4: Library-agnostic value reads

Code that reads a widget's selection outside its owning component reads the native `<select>`
(`selectedOptions.length`, `value`) or `element.tomselect.getValue()`, never a library-specific
DOM class. The known case is `TransactionItemContainer.itemListShow()`.

### FR-5: Theme and dark mode

- Import Tom Select's Bootstrap 5 SCSS (`tom-select/dist/scss/tom-select.bootstrap5`) in
  `resources/sass/app.scss` **after** CoreUI, so it compiles against the project's CoreUI
  variables. Remove both Select2 imports.
- Replace the Select2 blocks in `_custom.scss`. Colours must come from CoreUI CSS variables
  (`--cui-body-bg`, `--cui-body-color`, `--cui-border-color`, `--cui-secondary-color`,
  `--cui-tertiary-bg`, `--cui-primary`/focus ring) so light and dark mode both follow
  `data-coreui-theme` without a dark-only override block where possible. States that must
  match today, in both themes:
  - closed control with placeholder; closed control with a value
  - focused/open control (focus ring)
  - dropdown with highlighted option, disabled option, "no results" message
  - multi-select chips with remove button; clear button with hover
  - disabled control
  - search input placeholder (lighter, secondary colour at 0.5 opacity)
- Height, font size and border radius match adjacent `.form-select`/`.form-control` fields,
  including in `.input-group` next to the "add payee" button (the control flexes to fill the
  remaining width).
- If a select carries `is-invalid`, the Tom Select control shows Bootstrap's invalid styling.
  Check whether any migrated select binds `is-invalid` dynamically. If one does, make sure the
  class reaches `.ts-wrapper`.

### FR-6: Tags (create)

`TransactionItem.vue`'s tag select keeps its current behaviour:

- Remote search of existing tags.
- Typing a term that doesn't exist offers to create it. The option label is the escaped term
  followed by `(new)` (translated).
- The emitted value is a mixed array: numeric IDs for existing tags and the raw text for new
  tags. The backend already handles this; the format must not change.
- Accepted deviation: Tom Select lists the create option first, while Select2 appended it last.

### FR-7: Escaped rendering (security)

- The investment dropdown shows `name` plus a muted `(symbol)`, both passed through Tom Select's
  `escape()`. The selected item shows the escaped name only.
- No render hook anywhere may put API data into HTML without `escape()`.
- The testing phase covers this (see `testing.md`, T-SEC-1).

### FR-8: Payee form unification (Phase 0b)

The full-page payee create/edit form (`resources/views/payees/form.blade.php` +
`resources/js/payee/form.js`) duplicates `PayeeForm.vue`. It's reachable from four places, and
**all of them keep their current links**; the redirect is the one mechanism:

| Entry point | Link |
| --- | --- |
| Quick actions "New payee" (`resources/views/template/components/quick-actions.blade.php`) | `account-entity.create?type=payee` |
| Onboarding step "Add a payee" (`OnboardingApiController`) | `account-entity.create?type=payee` |
| Payee name on the transaction details view (`ShowStandard.vue` `entityLink()`) | `account-entity.edit` |
| Dashboard payee category recommendation widget (`PayeeCategoryRecommendation.vue`) | `account-entity.edit` |

Frontend:

1. The payee list page (`resources/js/payee/index.js`) reads `?create=1` and `?edit={id}` on
   load and calls `payeeFormNew.show()` / `payeeFormEdit.show(id)`. It then removes the parameter
   with `history.replaceState`, so a reload or Back doesn't reopen the modal.
2. A deep-linked modal behaves exactly like one opened from the list: saving updates the table
   in place, and cancelling just closes it.
3. An unknown or unauthorized `edit` ID shows the modal's existing API error handling (an error
   toast). It must not leave a half-filled modal behind. Verify this, and fix it if needed.
4. Delete `resources/js/payee/form.js` and its `loadModule('payee/form')` block in
   `resources/js/app.js`.

Backend: see §7. Tests: `testing.md` §9.

Docs (documentation agent, same PR):

- `.ai/docs/assets/payee/overview.md`: replace the "Creating a Payee (full form, dedicated page)"
  flow and the "Payee Form Page (full-page)" section with the deep-link/redirect behaviour.
- `.ai/docs/specifications/frontend-review/tasks.md` (line 128): drop
  `resources/views/payees/form.blade.php` from the list of Blade CRUD forms.

## 7. Backend Scope

**Only the payee form unification (FR-8, Phase 0b).** Everything else is frontend. If an agent
concludes another backend change is required (for example, an endpoint behaves differently for
an empty term than the widget needs), **stop and ask** rather than changing the API. Public
`/api/v1` response shapes must not change.

`AccountEntityController`, payee type only. Account handling must be byte-for-byte unaffected:

- `create` with `type=payee` → redirect to `account-entity.index` `{type: 'payee', create: 1}`.
- `edit` of a payee → redirect to `account-entity.index` `{type: 'payee', edit: <id>}`.
  **Authorization stays in front of the redirect**, so another user's payee still gets 403 and
  a guest still gets redirected to login (`tests/Feature/Concerns/AuthorizesResourceCrud.php`
  checks both).
- `store` / `update` with `config_type = payee` → 404. The web write path for payees is removed;
  `PayeeApiController::storePayee`/`updatePayee` (same `AccountEntityRequest`, same
  `PayeePersistenceService`) is the only write path. Authorization still runs first (403 for
  another user's payee takes precedence).
- Delete `createPayee()` and `editPayee()`, `resources/views/payees/form.blade.php`,
  `App\Http\View\Composers\CategoryListComposer` and its registration in
  `ViewServiceProvider` (it only serves `payees.form`). Remove the `PayeePersistenceService`
  constructor dependency from `AccountEntityController` if nothing else there uses it. The
  service itself stays because the API uses it.
- Breadcrumbs for `account-entity.create`/`edit` stay, because account pages use them.

## 8. Testing Scope (summary; details in `testing.md`)

- Add Pest 5 + Laravel plugin + browser plugin (Composer, dev) and `playwright` (npm, dev).
- New `tests/Pest.php`, new `tests/PEST/` browser suite, excluded from the default `artisan test` run.
- Convert these Dusk files, **entire files** (72 tests), to Pest browser tests, then delete the
  Dusk originals:
  `TransactionFormStandardStandaloneTest`, `TransactionFormStandardModalTest`,
  `TransactionFormInvestmentStandaloneTest`, `TransactionFormInvestmentModalTest`,
  `FindTransactionsFilterBehaviorTest`, `PayeeListTest`.
- Add minimal new E2E coverage for migrated pages that have no browser coverage today (merge
  pages, payee create/edit page, category learning, cashflow/budget chart account filter,
  budget modal).
- Add a CI workflow for the E2E suite. Existing Dusk workflows stay for the Dusk tests that
  remain.

## 9. Removal Checklist (G5)

- [ ] `package.json`: `select2`, `select2-bootstrap-5-theme` removed; lockfile updated
- [ ] `composer.json`: `roquie/laravel-dusk-select2` removed from `require-dev`, and the
      `repositories` VCS entry for `kantorge/laravel-dusk-select2` removed; lockfile updated
- [ ] `resources/js/shared/lib/select2/` deleted
- [ ] `resources/js/shared/lib/i18n/select2.js` deleted, and its re-export removed from
      `shared/lib/i18n/index.js`
- [ ] Select2 imports removed from `resources/sass/app.scss`; Select2 blocks removed from
      `resources/sass/_custom.scss`
- [ ] `tests/DuskTestCase.php`: `getSelect2Values`, `getSelect2ValueCount`,
      `waitForSelect2ValueCount`, `assertSelect2Values`, `assertSelect2HasNoSelection` removed
- [ ] The six Dusk test files listed in §8 deleted (after their Pest equivalents pass)
- [ ] Leftover `tests/Browser/console/*` logs for deleted tests removed if tracked
- [ ] `grep -rniI select2 app resources tests config routes composer.json package.json .github`
      returns nothing
- [ ] `npm run build` output contains no `select2` string
- [ ] Payee form unification (FR-8, done in Phase 0b): `resources/js/payee/form.js`,
      `resources/views/payees/form.blade.php`, `CategoryListComposer` + its registration,
      `createPayee()`/`editPayee()` removed; no `payees.form` or `payee/form` references left

## 10. Test Policy and Project Rule Updates (part of Phase 0)

Owner decision (2026-09-27):

- **New tests are written in Pest 5**, whatever their level. Browser tests use
  `pest-plugin-browser` and live in `tests/PEST/`. New unit and feature tests use Pest syntax in
  `tests/Unit/` and `tests/Feature/`, where Pest runs them alongside the existing PHPUnit classes.
- **Existing PHPUnit tests are not converted automatically.** The only exceptions are the Dusk
  files in §8, and any conversion the owner approves from the one-time scan (§11, Phase 5).
- **Dusk is legacy.** No new Dusk tests. The remaining Dusk tests move to Pest in a follow-up
  (§16).

These rules contradict the current wording in the files below, which must change in the Phase 0
PR:

- `CLAUDE.md`: replace "PHPUnit only — no Pest" (Architecture Highlights) with the policy above.
- `tests/CLAUDE.md`: Test Level Guide (add a Pest browser row for `tests/PEST/`, mark Dusk as
  legacy), Conventions (Pest for new tests; `it_…` naming becomes `it('…')`), the Dusk section,
  and "Do NOT introduce new testing frameworks" (Pest is now the approved framework).
- `.ai/agents/testing.agent.md`: test levels (Pest browser replaces Dusk for new tests), the
  "Do NOT introduce new testing frameworks" rule, and Done Criteria (the Pest browser suite must
  pass).

## 11. Phases

Each phase is one PR that leaves `develop` green. A component's Dusk tests must be converted in
the same PR that migrates the component, or Dusk CI breaks.

| Phase | Scope | Agents | Tests converted / added |
| --- | --- | --- | --- |
| 0 | Pest 5 + browser plugin + Playwright; `tests/Pest.php`; `tests/PEST/` suite; E2E CI workflow; Sail/local run command; rule-doc updates (§10); one smoke test (log in, open dashboard) | testing | smoke test |
| 0b | **Payee form unification** (FR-8, §7): payee create/edit routes redirect to the list modal; delete the full-page form, `payee/form.js`, `CategoryListComposer`; web store/update reject payees; test and doc updates (`testing.md` §9). Still on Select2, so it's independent of Tom Select. | backend, frontend, testing, documentation | new Pest feature tests for the redirects/404s; new Pest browser tests for the deep links; obsolete web-form tests removed |
| 1 | `tom-select` npm dependency; shared factory (FR-1); theme (FR-5); migrate **plain page scripts** P1–P4, P7–P11 (P5/P6 are gone after Phase 0b) | frontend, then testing | new E2E: category merge, payee merge, category-learning merge + filter, cashflow + budget chart preset account |
| 2 | Migrate `FindTransactionSelectCard`, `BudgetForm`, `CategoryLearningForm` | frontend, then testing | convert `FindTransactionsFilterBehaviorTest`; new E2E: budget modal category/account |
| 3 | Migrate transaction forms: `TransactionFormStandard`, `TransactionFormInvestment`, `TransactionItem`, `TransactionItemContainer`, and `PayeeForm` (embedded in the standard form's new-payee modal, so it must move with it); FR-6, FR-7 | frontend, then testing | convert the four transaction test files and `PayeeListTest`; add T-SEC-1 |
| 4 | Removal checklist (§9); remove the Select2 helpers from `DuskTestCase`; final grep; documentation agent writes the as-built feature doc `.ai/docs/features/remote-select/` | frontend, testing, documentation | full E2E suite + remaining Dusk suite green |
| 5 | **One-time scan** of existing PHPUnit Unit/Feature tests for files where Pest would bring a *significant* simplification or speed-up (see `testing.md` §7). The output is a report; nothing is converted without owner approval. Can run any time after Phase 0. | testing | none (report only) |

Both libraries ship during Phases 1–3. This is safe because page scripts are loaded per route.

## 12. Acceptance Criteria

- AC-1: Given any widget in `inventory.md`, when a user opens, searches, selects, clears and
  submits, then the request parameters, displayed labels, excluded options, side effects and
  submitted value match the "Must keep" column.
- AC-2: Given a transaction form opened for edit, clone or replace, when presets load, then the
  form isn't reported as dirty until the user changes something (the modal close/Esc tests
  verify this).
- AC-3: Given any migrated page, in light and in dark mode, when its selects are compared to
  pre-migration screenshots, then there's no unintended visual difference (sizes, colours, focus
  ring, chips, dropdown).
- AC-4: Given an investment named `<img src=x onerror=alert(1)>`, when it's listed or selected
  in the investment dropdown, then the literal text is shown and no element is created.
- AC-5: Given a select in a modal, when the dropdown opens, then it isn't clipped, typing works
  (focus isn't stolen by the modal), and Esc closes only the dropdown.
- AC-6: Given the investment form, when the account changes after the investment dropdown has
  already been opened, then reopening the investment dropdown shows results filtered by the new
  account's currency, and the same holds the other way round (no stale cache, D4).
- AC-7: `vendor/bin/sail artisan test --compact` runs Unit and Feature only and passes. The E2E
  suite passes locally and in CI with no `retry()`, `sleep()`, `pause()` or `wait(n)` calls.
- AC-8: Every item in the removal checklist (§9) is done.
- AC-9: `npx eslint resources/js`, Pint and PHPStan pass.

## 13. Risks

| Risk | Mitigation |
| --- | --- |
| Playwright's Chromium needs OS libraries. `npx playwright install --with-deps` needs root, and the Sail runtime image comes from `vendor/` and isn't published | Phase 0 finds a working local command (e.g. installing through `sail root-shell`, or running E2E outside Sail). If it only works by publishing Sail's Dockerfile (`sail:publish`), **ask first**, since that's a directory restructure. `ext-sockets` is already present in Sail. |
| Tom Select's per-query cache hides state-dependent filtering | D4 turns caching off; AC-6 covers it |
| Dropdown inside modals: focus trap or clipping | FR-1 item 10; AC-5 |
| `selectOnClose` has no native Tom Select equivalent | Implemented in the factory (FR-1 item 8), covered by transaction item E2E tests |
| Pest browser tests run the app **in process** against the `testing` DB. Dusk ran against a separately served app. | Better isolation, and `duskapiconf` isn't needed for converted tests. Tests that relied on shared state across tests (migrate + seed once per class) must seed explicitly (see `testing.md`). |
| A `public/hot` file (Vite dev server running) changes asset URLs during E2E runs | The E2E run instructions say to build assets and make sure `public/hot` is absent |
| Behaviour drift that the old Dusk tests didn't cover | `inventory.md` is the parity checklist; reviewers tick it per phase |

## 14. Resolved Decisions (2026-09-27)

| # | Question | Decision |
| --- | --- | --- |
| Q1 | Pest scope | Browser tests now; **all new tests** in Pest from now on; one-time scan of existing tests for high-value conversions (Phase 5); no automatic mass conversion. See §10. |
| Q2 | Browser test location | `tests/PEST/` |
| Q3 | Migrate the remaining Dusk tests later | Yes, as a follow-up (§16) |
| Q4 | Create-tag option listed first, not last | Probably acceptable. **The owner reviews it during testing** (§15). |
| Q5 | Keep `selectOnClose` | Yes, keep the current behaviour. **The owner reviews it during testing** (§15). |
| Q6 | Unify the full-page payee form with `PayeeForm.vue` | Yes: routes redirect to the list modal, the full page and its code are deleted, including cleanup (FR-8, Phase 0b) |

## 15. Owner Review Notes (hand to the owner during Phase 3 testing)

The Phase 3 PR description **must** repeat this list so the owner can check each item by hand:

- [ ] **R1: create-tag option position (FR-6, Q4).** In a transaction item's tag select, type a
      tag name that doesn't exist. Tom Select shows "<term> (new)" at the **top** of the list;
      Select2 showed it at the bottom. Decide whether to keep this, or have the factory move it
      last.
- [ ] **R2: `selectOnClose` (FR-1 item 8, Q5).** On the transaction item category select (V5),
      type part of a name and then Tab or click away. The
      highlighted option should be selected, as it is today. Decide whether it feels right or
      causes accidental selections.

(R3, about the unthemed payee page, is gone: the page was removed in Phase 0b.)

## 16. Follow-ups (separate specs)

- **Migrate the remaining Dusk tests to Pest browser tests**, then remove `laravel/dusk`,
  `alebatistella/duskapiconf`, `tests/DuskTestCase.php`, `tests/Browser/`, the Dusk CI
  workflows, `.env.dusk.ci`, and the `duskapiconf_tmp.txt` warnings in `CLAUDE.md` and
  `tests/CLAUDE.md`.
- Convert the existing tests the owner approves from the Phase 5 scan.
