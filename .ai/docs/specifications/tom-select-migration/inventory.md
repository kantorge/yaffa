# Select2 Instance Inventory

This is the parity checklist for the Tom Select migration (see [`specification.md`](specification.md)).
Every row must behave the same after migration. Line numbers are as of commit `568cf1ce`.

Conventions used in the tables:

- **Endpoint / params**: the query sent on each search. `term` is the typed text. "term (omit if
  empty)" means the key is **absent** when nothing is typed (jQuery dropped `undefined`), which
  changes the server's response for `/api/v1/accounts` (most-used accounts instead of a search).
  `null` values are sent as `key=` (see spec FR-1 item 3).
- **Label**: the field shown for each option.
- **Excludes**: options removed client-side from results.
- Every instance has `allowClear` unless noted otherwise, and no query cache (spec D4).

## Plain page scripts

| ID | File / element | Mode | Endpoint / params | Label | Excludes | Must keep |
| --- | --- | --- | --- | --- | --- | --- |
| P1 | `resources/js/categories/merge.js` `#category_source` | single | `/api/v1/categories` `{q: term (omit if empty), withInactive: true}` | `full_name` | value of `#category_target` | On select: `GET /api/v1/categories/{id}`, store "is parent" flag (`!data.parent`) on the element (read later by the merge confirmation). On clear: flag reset to `null`. Preset from `window.categorySource` (only if `.id` is set) **runs the select side effect**. Placeholder "Select category to be merged". |
| P2 | same file, `#category_target` | single | same as P1 | `full_name` | value of `#category_source` | Mirror of P1 (no preset). The submit handler blocks when either is empty or both are equal, using the existing `alert()` messages. |
| P3 | `resources/js/payee/merge.js` `#payee_source` | single | `/api/v1/payees` `{q: term (omit if empty), account_type: 'payee', withInactive: true}` | `name` | value of `#payee_target` | Preset from `window.payeeSource` (no side effect). Placeholder "Select payee to be merged". |
| P4 | same file, `#payee_target` | single | same as P3 | `name` | value of `#payee_source` | The submit handler validates empty/equal as today. Placeholder "Select payee to be merged into". |
| P5 | ~~`resources/js/payee/form.js` `#preferred`~~ | — | — | — | — | **Removed in Phase 0b** (spec FR-8). The payee create/edit page is replaced by `PayeeForm.vue` (V8–V10), so there is nothing to migrate. |
| P6 | ~~same file, `#not_preferred`~~ | — | — | — | — | **Removed in Phase 0b**, as P5. |
| P7 | `resources/js/category-learning/index.js` `#merge_source_learning` | single, in modal | `route('api.v1.category-learning.index')` `{search: term (omit if empty), status: 'all'}` | `` `${item_description} (${category.full_name ‖ category.name ‖ __('Not set')})` `` | value of P8 | `dropdownParent`: `#mergeCategoryLearningModal`. When the modal opens both selects are cleared, and the source is optionally preset (same label format, using `category_name`). Merge action reads both values. |
| P8 | same file, `#merge_target_learning` | single, in modal | same as P7 | same as P7 | value of P7 | As P7. |
| P9 | same file, `#table_filter_category` | single | `/api/v1/categories` `{q: term ‖ '*', withInactive: true}` | `full_name` | none | On change: DataTable column 5 exact regex search `^id$`. On clear: column search reset. Placeholder "Any". |
| P10 | `resources/js/reports/cashflow.js` account filter | single | `/api/v1/accounts` `{q: term (omit if empty), withInactive: true}` | `name` | none | On select and clear: `rebuildUrl()` (pushState). Preset from `window.presetAccount`: `GET /api/v1/accounts/{id}`, set, then `reloadData()`. |
| P11 | `resources/js/reports/budgetchart.js` account filter | single | same as P10 | `name` | none | On select and clear: `rebuildUrl()`. Preset from global `presetAccount`: set, then `presetFilters.account = true` and initial load when the tree is also ready. |

## Vue components

| ID | File / element | Mode | Endpoint / params | Label | Excludes | Must keep |
| --- | --- | --- | --- | --- | --- | --- |
| V1 | `resources/js/transactions/components/form/TransactionFormStandard.vue` `#account_from` | single, `dropdownParent` = `dropdownParentSelector` prop | Account side: `/api/v1/accounts`; payee side: `/api/v1/payees` (by transaction type, `getAccountType('from')`). Params `{q: term (omit if empty), transaction_type, account_type: 'from', account_entity_id: accountId prop}` | `name` | `form.config.account_to_id` | Placeholder "Select account" / "Select payee" by side type. On select: set `form.config.account_from_id`. If account: `GET /api/v1/accounts/{id}` → `from.account_currency`. If payee: `GET /api/v1/payees/{id}` → `payeeCategory` from `config.category`. On clear: `resetAccount('from')`, plus `resetPayee()` if payee side. **Re-created on transaction-type change** (spec FR-3). Preset via `getDefaultAccountDetails()` returns a promise used for the `markFormClean()` baseline and **runs the select side effects**. `setPayee(payee)` (called after the new-payee modal saves) presets the payee side the same way. Sits in an `.input-group` next to the "add payee" button. |
| V2 | same component, `#account_to` | single | as V1 with `account_type: 'to'` and `getAccountType('to')` | `name` | `form.config.account_from_id` | Mirror of V1 (`to.account_currency`). |
| V3 | `resources/js/transactions/components/form/TransactionFormInvestment.vue` `#account` | single, `dropdownParent` prop | `/api/v1/accounts/investment` `{q: term (omit if empty), transaction_type, currency_id: investment_currency?.id}` (result already `{id, text}`) | `text` | none | On select: set `form.config.account_id`, `GET /api/v1/accounts/{id}` → `account_currency`. On clear (single path): `account_id = null`, `account_currency = null`. Preset from `form.config.account_id` (URL param or transaction) returns a promise for the clean baseline. **Results depend on the currently selected investment's currency (spec AC-6).** |
| V4 | same component, `#investment` | single, `dropdownParent` prop | `/api/v1/investments` `{query: term (omit if empty), active: 1, currency_id: account_currency?.id, limit: 10, sort_by: 'name', sort_order: 'asc'}` | Option: **escaped** `name` + muted **escaped** `(symbol)`. Selected item: escaped `name` | none | On select: set `investment_currency = {id: currency_id}` **immediately** (avoids a race in account filtering), then `GET route('api.v1.investments.show')` → full `investment_currency`. On clear: `investment_currency = null`, `investment_id = null`, `existingPriceForDate = null`, `storePriceEnabled = false`. Preset as V3. **Security fix FR-7.** |
| V5 | `resources/js/transactions/components/form/TransactionItem.vue` `select.category` (one per item row, scoped by `#transaction_item_{id}`) | single, `dropdownParent` prop | `/api/v1/categories` `{q: term (omit if empty), payee: payee prop (null sends `payee=`)}` | `full_name` | none | `selectOnClose: true`. On select/clear: emit `update:category_id`. If a recommendation exists, set `isRecommendationAccepted = (value == recommended_category_id)`. On clear: `learnRecommendation = false` + `onLearnRecommendationChange()`. Preload the saved category (`category_id` + `category_full_name`), else an auto-accepted recommendation (exact match, or AI with `confidence_score ≥ threshold`), then emit. The "accept recommendation" method replaces the value with the recommendation. Items are added and removed dynamically, so destroy on unmount (spec FR-2). |
| V6 | same component, `select.tag` | multi + create, `dropdownParent` prop | `/api/v1/tags` `{q: term (omit if empty)}` (result already `{id, text}`) | `text`. Create option: escaped term + ` (new)` | none | Emit `update:tags` with a mixed array (numeric IDs for existing tags, raw text for new ones; the format is unchanged, spec FR-6). Preload from the `tags` prop. Full width. Hidden below `md` (`d-none d-md-block` container). |
| V7 | `resources/js/transactions/components/form/TransactionItemContainer.vue` `itemListShow()` | reader | — | — | — | Show an item's detail container if its comment is non-empty **or any detail select has a selection**. Use a library-agnostic read (spec FR-4). |
| V8 | `resources/js/payee/components/PayeeForm.vue` default category select | single, `dropdownParent` = component modal | `/api/v1/categories` `{q: term ‖ '*', withInactive: true}` | `full_name` | none | On select/clear: `form.config.category_id` = Number or `null`. `loadPayeeData()` presets it (`setSelectValue`) or clears it. `resetForm()` clears it. Used on the payee list page (including the `?create=1` / `?edit={id}` deep links that replace the old full-page form, spec FR-8) **and** as the new-payee modal inside V1/V2. |
| V9 | same component, preferred categories | multi, `dropdownParent` = component modal | same as V8 | `full_name` | values of V10 (looked up within the same `.modal`) | On change: `form.config.preferred` = array of Numbers. Preset/reset as V8. |
| V10 | same component, excluded categories | multi | same as V8 | `full_name` | values of V9 | On change: `form.config.not_preferred` = array of Numbers. |
| V11 | `resources/js/reports/components/BudgetForm.vue` category select | single, `dropdownParent` = component modal | `/api/v1/categories` `{q: term ‖ '*', withInactive: true}` | `full_name` | none | On select/clear: `form.category_id` = Number or `null`. Preset in `loadBudgetData()`, cleared in `resetForm()`. |
| V12 | same component, account select | single, `dropdownParent` = component modal | `/api/v1/accounts` `{q: term ‖ '' (always present, so always a search), limit: 0}` | `name` | none | Placeholder "No account (base currency, account-agnostic)". On select/clear: `form.account_id` = Number or `null`, then `updateAccountCurrency()`. Preset/reset as V11. |
| V13 | `resources/js/reports/components/find-transactions/FindTransactionSelectCard.vue` (mounted 5×: category, payee, account, tag, investment) | multi, no `dropdownParent` | `searchApiPath` prop `{q: term (omit if empty), withInactive: true}` | `data[search_label_field] ?? full_name ?? text ?? name` | none | On change: `selectedValues` = values, emit `update(values)`. Presets: for each `presetItemIds` entry, `GET detailsApiPath.replace('#id#', id)`, add with `detailsLabelField`. When all are done, emit `preset-ready(property)`. With no presets, emit immediately. The card's clear button (disabled while empty or presets are pending) clears the value and emits `update([])`. |
| V14 | `resources/js/category-learning/components/CategoryLearningForm.vue` category select | single, `dropdownParent` = component modal | `/api/v1/categories` `{q: term ‖ '*', withInactive: true}` | `full_name` | none | On select/clear: `form.category_id` = Number or `null`. Cleared on reset. |

## Cross-cutting behaviour (applies to all rows)

- The search box sits inside the dropdown and shows the placeholder "Type to search...".
- "No results found" and "Searching…" messages are translated (en/fr/hu/pl).
- Options that the `excludes` rule removes are filtered on **every** response, using the other
  select's current value at request time.
- Clearing (button, removing the last chip, programmatic) runs the instance's clear path once.
- Placeholders are translated with `__()` at init time. Note that `categories/merge.js` passes
  functions today; plain strings are fine.
- `_token` query parameters existed only for `$.ajax`. Drop them, since axios sends the CSRF
  header.
