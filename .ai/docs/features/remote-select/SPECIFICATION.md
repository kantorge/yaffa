# Remote Select (searchable dropdowns)

As-built documentation, extracted from code after the Tom Select migration
(`.ai/docs/specifications/tom-select-migration/`, Phases 1–4). The code is the source of truth.

### Feature Name

Remote Select: searchable, server-backed dropdowns for accounts, payees, categories, tags and
investments

### Feature Summary

Every dropdown that picks one of the user's own records (account, payee, category, tag, investment,
category-learning rule) searches the server as the user types and lists exactly what the server
returns, in server order. A single shared factory (`createRemoteSelect()`, built on Tom Select)
gives all of these dropdowns the same look, keyboard behaviour, clearing, dark-mode theming and
modal handling. Transaction entry, which is the core manual-tracking task, therefore works the same
way on every page.

### Target User

- Primary: an intermediate or advanced user who records transactions by hand every day. They pick
  accounts, payees and categories many times per session and depend on fast, typed, keyboard-driven
  selection.
- Secondary: a user doing periodic clean-up (merging payees or categories, filtering reports,
  editing budgets and category-learning rules) who needs to find one record among hundreds.

### User Problem

- Users have hundreds of payees and categories, so a plain `<select>` holding all of them is slow
  to load and hard to scan.
- The valid choices often depend on other fields in the form: payees vs accounts by transaction
  type, investments by the account's currency, and a merge source can't also be the merge target.
  A list loaded once goes stale.
- Keyboard-heavy data entry needs typing, Tab and Esc to behave predictably, including inside
  modals.

### User Value / Benefit

#### Functional Benefits

- The user finds a record by typing a few characters instead of scrolling a full list. Opening an
  empty dropdown shows the server's default list (e.g. the most-used accounts).
- Options always reflect the current form state, because every open and every debounced keystroke
  (150 ms) queries again and there is no cache. After changing the account, the investment list
  shows only investments in the new account's currency.
- Closing a dropdown by Tab or click-away never selects anything: only Enter or a click picks an
  option. This prevents accidental selections (owner decision R2; Select2's `selectOnClose` was
  dropped).
- New tags can be created inline from the tag select. The create option ("… (new)") is not offered
  when a tag with that name (case-insensitive) is already listed.
- Dropdowns inside modals open in place, aren't clipped, and keep keyboard focus. Esc closes only
  the dropdown, not the modal.

#### Conceptual Benefits

- One consistent picker for the core domain entities (account, payee, category, tag, investment)
  makes those entities feel like one connected model, not a set of separate screens.
- Excluding invalid choices up front (the merge target can't equal the source, an item can't be
  both a preferred and an excluded payee category) keeps users from picking a structurally wrong
  combination. This is UX only: merge is enforced server-side, but preferred vs excluded currently
  is not (see `architecture.md`, Known risks).

### Technical Description

- `resources/js/shared/lib/tom-select/index.js` exports three functions:
  - `createRemoteSelect(element, options)` builds a Tom Select instance on an existing `<select>`.
    Tom Select's own client-side search, scoring and query cache are switched off. The dropdown
    lists the last server response only. A superseded request is aborted and its results are
    dropped immediately. The underlying `<select>` stays in sync, so form posts and
    `select.value` keep working.
  - `setSelected(ts, items, { silent })` presets options (edit, clone, URL presets). With
    `silent: true` it doesn't fire callbacks, so a preset never marks a form as dirty.
  - `clearSelect(ts, { silent })` clears the value and the loaded options.
- `queryString.js` serializes request params: `undefined` is left out and `null` is sent empty.
  Several API endpoints change behaviour depending on whether `q` is present at all, so this
  matters. It is covered by `queryString.test.js` (run with `node --test`).
- Per-widget behaviour comes from options: `url`/`params` (evaluated on every request, so they can
  read live form state), `mapResult`, `filterResults` (cross-select exclusion), `multiple`,
  `create`, `allowClear`, `renderOption`/`renderItem`, and
  `onSelect`/`onChange`/`onClear`.
- Theming: `resources/sass/app.scss` imports Tom Select's Bootstrap 5 SCSS, compiled against the
  CoreUI variables. The `.ts-wrapper`/`.ts-dropdown` rules in `resources/sass/_custom.scss` point
  its colours at CoreUI CSS variables, so light and dark mode need no separate override block.
- UI strings ("Type to search...", "No results found", "Searching...", "(new)", "Clear
  selection", "Remove") go through the app's own `__()` translations. There are no third-party
  locale bundles.
- Vue components create their instances in `mounted` and call `destroy()` in `beforeUnmount`.
  Destroying an instance also cancels its pending request.

### Inputs

- The user's search term (typing, or an empty term when the dropdown opens)
- Live form state that request params read (transaction type, selected account currency, the
  other select's value)
- Preset values from the server or URL (edit/clone/replace forms, `window.payeeSource`,
  `presetAccount`, find-transactions URL filters)

### Outputs

- `GET` requests to the existing `/api/v1/*` search endpoints (accounts, accounts/investment,
  payees, categories, tags, investments, category-learning)
- The selected value(s) in the underlying `<select>`, submitted with the form or read by Vue
  (`v-model`/emitted events)
- Side effects through callbacks, for example: payee default-category lookup, currency updates on
  the transaction form, report URL rebuilds (`pushState`), DataTable column filtering on the
  category-learning list

### Domain Concepts Used

- Account, Payee (both account entities), Category (shown by `full_name`, i.e. parent > child),
  Tag, Investment, Category-learning rule. See `.ai/docs/assets/`.

### Core Logic / Rules

- The dropdown shows the server's result set in server order. The client never re-sorts or
  re-filters it, apart from the widget's own `filterResults` exclusion.
- There is no result cache. Every open and every keystroke (after the 150 ms debounce) makes a new
  request.
- Silent presets (`setSelected(..., { silent: true })`) never fire `onSelect`/`onChange`, so
  loading an existing transaction doesn't make its form dirty.
- `onClear` fires once whenever the value becomes empty (clear button, removing the last chip, or a
  non-silent `clearSelect()`).
- Tab or blur closes the dropdown without selecting. Only Enter or a click selects.
- Any API data used in custom templates must go through Tom Select's `escape`. For example, an
  investment named `<img src=x onerror=…>` is shown as literal text.

### User Flow (typical: transaction item category)

1. The user clicks the category select or tabs into it. The dropdown opens and immediately
   requests `/api/v1/categories` (with payee context) with an empty term.
2. The user types "gro". After 150 ms the list is replaced with the server's matches.
3. The user presses Enter. The highlighted category is selected.
4. The user presses the clear button. The value empties and the component is notified once.

### Edge Cases / Constraints

- The widgets and their "Must keep" behaviour are listed in
  `.ai/docs/specifications/tom-select-migration/inventory.md` (P1–P4, P7–P11, V1–V14; P5/P6 were
  removed along with the full-page payee form).
- The transaction form destroys and rebuilds its account/payee selects when the transaction type
  changes, because the endpoint changes. A request still pending from the old instance is aborted.
- Multi-selects force `multiple` on the underlying `<select>`, so Tom Select returns an array value.
- The create-tag option is listed first in the dropdown. The previous library listed it last. The
  owner accepted this (spec §15, R1).
- jQuery remains in the app for DataTables, jsTree and `$.ajax`. The selects don't use it.

### Dependencies

- Models / API: the existing `/api/v1/*` search endpoints. No backend changes were made for this
  feature.
- Services: none
- External libraries: `tom-select` (npm; base build plus the `clear_button`, `dropdown_input` and
  `remove_button` plugins), `axios` (requests, abort via `AbortController`)

### Frontend Interaction

- Plain page scripts: `categories/merge.js`, `payee/merge.js`, `category-learning/index.js`,
  `reports/cashflow.js`, `reports/budgetchart.js`
- Vue components: `TransactionFormStandard`, `TransactionFormInvestment`, `TransactionItem`,
  `PayeeForm`, `BudgetForm`, `CategoryLearningForm`, `FindTransactionSelectCard`

### Tests

- Pest browser tests (`tests/PEST/`, run with `pest --testsuite=Browser`) cover every migrated page:
  transaction forms (standard/investment, standalone/modal), payee list/modal/deep links/merge,
  category merge, category learning, budget modal, cashflow/budget chart account filter, and
  find-transactions filters.
- Shared helpers live in `tests/PEST/Support/BrowserTestCase.php`: `chooseTomSelectOption`,
  `searchTomSelect`, `assertTomSelectValues`, `clearTomSelect` and `tomSelectIdByTestId`.
- Unit: `resources/js/shared/lib/tom-select/queryString.test.js`.
- The full coverage map is in `tests.md`. The trust-boundary view is in `architecture.md`, `flows.md`,
  `permissions.md` and `variables.md`.

### Domain Concepts

- "Remote select": a dropdown whose options come from a server search on every open and keystroke,
  never from a preloaded list.
- "Silent preset": a value set by code (not the user) that doesn't count as a user change.

### Confidence Level

- High. Extracted from the factory, its call sites and the passing browser suite.

### Assumptions

- Referred to `.ai/docs/specifications/tom-select-migration/` (specification and inventory). It
  matches the code, apart from the inventory's historical title and "Select2" wording, which
  describes the pre-migration state on purpose.
- The empty-open request showing "most-used" records depends on each endpoint's behaviour when `q`
  is absent. This doc doesn't verify it per endpoint.
