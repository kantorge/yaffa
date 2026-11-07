# Local Form Selects Specification

## 1. Purpose

Make the dropdowns of the transaction forms (accounts, payees, investments, item categories, tags) instant, smarter
and able to find everything, by loading a slim dataset **once per page** and doing all ranking, filtering and
free-text search in the browser.

This folder is the implementation handoff. Read in this order:

| File | Audience | Contents |
| --- | --- | --- |
| `specification.md` (this file) | everyone | goals, decisions, architecture, API contract, ranking rules, phases, acceptance |
| [`status-and-handoff.md`](status-and-handoff.md) | the agent picking up a phase | what is done, what is left per phase (step by step), gotchas, how to run and test, open questions |

Related as-built docs: `.ai/docs/features/remote-select/architecture.md` ("Local selects"),
`.ai/docs/features/mobile-app-support/architecture.md` ("Reference data and summary").

## 2. Background and pain points

Before this work, `TransactionFormStandard.vue`, `TransactionFormInvestment.vue` and `TransactionItem.vue` used
`createRemoteSelect()` (`resources/js/shared/lib/tom-select/index.js`): every dropdown open and every keystroke hit
`/api/v1/accounts|payees|investments|categories|tags`, and every selection fired a further GET (account currency,
payee default category, investment currency).

Pain points this spec resolves:

1. The forms feel slow: nearly every interaction waits for a server reply.
2. Small facts that should already be on the client (account currency, payee default category) were fetched on selection.
3. Search ignored payee and account **alias**.
4. Only the 10 most used items were offered. Wanted: 5 most used + 5 recently used, grouped (Tom Select optgroups).
5. An inactive payee (rarely used) could not be found, and the new-payee popup that can reactivate it is not
   available when the form is in a modal (`ModalStandard.vue`; no modal over a modal). The user had to open a new tab,
   reactivate, return, save, and deactivate again.
6. "Apply usual items" (`TransactionItemContainer.vue`) included exceptional categories and distributed amounts to
   the cent, giving fractions even in currencies without them (e.g. HUF).
7. Item category dropdowns should keep their per-payee behaviour: the payee's usual categories first, with any
   category still reachable by search.

Original behaviour that must be preserved: always offer the most plausible choices first, always allow search, apply
the limits that exist (no same account on both sides of a transfer, active assets by default, same currency for an
investment and its account), and adapt sibling dropdowns to what is already selected (e.g. payees most used with the
chosen account).

## 3. Decisions (confirmed with the product owner)

| # | Decision |
| --- | --- |
| D1 | Preload the **full** list of the user's accounts, payees, investments, categories and tags (active **and** inactive, flagged), with a **trimmed** payload: no timestamps, no unused columns or relations. |
| D2 | Free-text search is **fully local**. No server request on dropdown open, typing or selection. The "Add new payee" button/popup stays in the non-modal form. |
| D3 | The mobile companion app already reads reference data from `GET /api/v1/reference-data`. The web forms **reuse that endpoint** (extended additively), and the new usage endpoint is part of the same contract, so web and mobile share one source and one set of ranking rules. No parallel "select-data" API. |
| D4 | Existing list endpoints (`/accounts`, `/payees`, `/categories`, `/tags`, `/investments`, ...) stay exactly as they are: they are a public API contract and 12 other files use them through `createRemoteSelect`. The transaction forms simply stop using them. |
| D5 | Inactive entities are **reactivated inline**: picking one in the dropdown PATCHes it active (`api.v1.account-entities.patch-active`), works in the modal too, and an opt-out checkbox restores it (inactive again) after a successful save. Cancel or a failed save leaves it active, with a toast. |
| D6 | "Apply usual items" rounds to the account **currency's decimal precision**, drops categories under a 10% usage share (no fixed cap, so at most 10 items), and gives the remainder to the payee default category (if among the items) or else the largest item. |
| D7 | The server-side cache for reference data is a **measured follow-up**, not part of the first change. Start with the existing ETag/304 and measure real response time and payload size first. |
| D8 | Usage statistics stay **fresh on the client** during a session by counting each saved transaction locally (`applySavedTransaction`), instead of refetching or server-caching them. |
| D9 | The dropdown height is raised (`min(60vh, 22rem)`) so two groups of five fit on desktop, but only for the grouped layout: `createLocalSelect()` adds the `ts-dropdown-grouped` class to its dropdown and the style is scoped to it. Other Tom Selects are unchanged. |

## 4. Architecture

```
Browser (per page)                                     Server
─────────────────                                      ──────
shared/lib/select-data/index.js                        GET /api/v1/reference-data        (entities, ETag)
  loadSelectData()  ── memoized promise ──────────────▶ GET /api/v1/reference-data/usage   (volatile, 12 months)
  upsertEntity(kind,row)                                  └─ ReferenceUsageService
shared/lib/select-data/ranking.js  (pure, node-tested)
  rank / search / suggestCategories / applySavedTransaction
shared/lib/tom-select/index.js
  createLocalSelect(), lockSelect(), unlockSelect()
Vue forms ── read data on demand (non-reactive) ── selects call suggestions()/search() on every open/keystroke
```

- **Entities** (rarely change): accounts, payees, investments, categories, tags, currencies. One call, existing
  ETag/304 and `updated_since` delta.
- **Usage** (changes with every transaction): rolling 12-month aggregates. Always fetched fresh, never server cached.
- The loaded data is held in a plain module-level promise (`loadSelectData()`); this app keeps no global state
  store. The component keeps the resolved object as a **non-reactive** property (`this.selectData`) because it is
  large and only read on demand (select callbacks), never rendered.
- The form skeleton renders immediately. The affected selects are locked with a "Loading…" placeholder until the data
  resolves. The form's `data-loaded` flag (used by tests and `isDirty()`) turns true only after data and presets settle.

## 5. API contract

Both endpoints are `auth:sanctum`, `verified`, `abilities:read` (`ReferenceDataApiController`, class-level
middleware). Changes are **additive only** (mobile contract). `GET /meta` lists `features.reference_usage = true`.

### 5.1 `GET /api/v1/reference-data[?updated_since=ISO8601]`

Existing endpoint. Additions:

| Block | New fields |
| --- | --- |
| `accounts[]` | `alias` |
| `payees[]` | `alias`, `category_id` (default category; stored on the payee config table without timestamps, so in `updated_since` delta mode it can lag until the payee row itself changes) |
| `investments[]` (new block) | `id, name, symbol, currency_id, active` |
| `ids.investments` (delta mode only) | all current investment ids |

Unchanged: `accounts[]` `id,name,active,currency_id,account_group_id`; `payees[]` `id,name,active`; `categories[]`
`id,name,active,parent_id` (no `full_name`; clients build "Parent > Child" from `parent_id`); `tags[]`
`id,name,active`; `currencies[]` `id,iso_code,name,base,generic_decimal_precision,detailed_decimal_precision`;
`server_time`; `ETag`/`If-None-Match` → 304. Inactive rows are included (flagged `active: false`).

### 5.2 `GET /api/v1/reference-data/usage` (new)

Built by `app/Services/ReferenceUsageService.php`. Window: **12 months** (`ReferenceUsageService::WINDOW_MONTHS`),
non-scheduled transactions only.

```jsonc
{
  "window_months": 12,
  "standard":   [{ "type": "withdrawal", "account_from_id": 1, "account_to_id": 10, "uses": 5, "last_used": "2026-03-01" }],
  "investment": [{ "type": "buy", "account_id": 1, "investment_id": 7, "uses": 2, "last_used": "2026-01-01" }],
  "payee_categories":    [{ "payee_id": 10, "category_id": 2, "uses": 9 }],   // top 5 per payee, counted over transaction items
  "deferred_categories": [{ "payee_id": 10, "category_id": 3 }]               // payee opted out of these (preference pivot, preferred=false)
}
```

A per-entity ranking is derived by summing rows (see 6.1). `payee_categories` counts a payee on either side of a
standard transaction.

## 6. Ranking and search rules

These rules are normative: the web implementation is `resources/js/shared/lib/select-data/ranking.js`, and a native
client should implement the same (also documented in the mobile architecture doc).

### 6.1 Accounts, payees and investments (5 + 5)

1. **Candidates**: active entities only, minus exclusions (the other side's selected id; for investments the
   currency must match, see Phase 4).
2. **Usage** is summed from the usage rows, **limited to the transaction type**, and:
   - for a payee, to rows with the chosen account on the other side, when that account has any shared history,
     otherwise global usage for the type;
   - for an account side, usage of that side (from/to) for the type, with no payee anchor. This mirrors the old
     `GET /accounts` default list (top 10 by usage for the type and side); only the 5+5 split is new.
3. **Most used**: top 5 by `uses` (ties: later `last_used`, then name).
4. **Recent**: the 5 latest `last_used` among candidates **not already in Most used** (an item never appears in two groups).
5. **Fill**: if fewer than 10 are listed, top up alphabetically from the remaining candidates (label "Other").
6. **Search** (non-empty term): local match on `name` and `alias`, case- and accent-insensitive. Order: name starts
   with, name contains, alias contains; active before inactive. Alias-only matches show the alias muted next to the
   name. Inactive entities are included and badged "inactive". Capped at 50 rows to keep the DOM small.

### 6.2 Categories per payee (Phase 3)

1. If a payee is selected: its **default category** (`payees[].category_id`) first, then its top categories by
   `payee_categories` uses, excluding categories in `deferred_categories` and inactive categories. Max 5.
   The same exclusions apply to search (see item 4).
2. A payee without history (or none selected): the most used categories across all payees stand in (summed from
   `payee_categories`; these are top-5-per-payee rows, so it is an approximation).
3. Any **active** category can always be found by typing (local search over `full_name`). Inactive categories are
   neither suggested nor searchable (same as the old behaviour).
4. **Decided (owner):** categories deferred for the selected payee (`deferred_categories`) are hidden from search too,
   not only from suggestions, when adding or editing a standard transaction. Same as the old `GET /categories?payee=`.

### 6.3 Keeping usage fresh

`applySavedTransaction(usage, transaction)` increments the matching `standard`/`investment` row (creating it if
needed), updates `last_used`, and increments `payee_categories` for the payee (withdrawal: `account_to_id`;
deposit: `account_from_id`; transfers touch none). Scheduled transactions are ignored. Call it for **new**
transactions only (not edits, they would double count).

## 7. Inactive entities (decision D5, details)

- Inactive accounts/payees/investments are in the local list, badged "inactive", sorted after active matches.
- User pick of an inactive account or payee → `PATCH api.v1.account-entities.patch-active {active: true}` (policy
  `update` on the entity), then `upsertEntity(kind, {id, active: true})` and the entity joins `reactivated`.
  On failure: error toast, the select is cleared.
- Checkbox "Deactivate again after saving: :names" (default on) shows while `reactivated` is non-empty. After a
  **successful save** (create or edit) every entry in `reactivated` is PATCHed `{active: false}` (and the local data
  updated) when the box is on. Restoring covers entries the user later replaced by another choice.
- Cancel (button or confirmed discard): nothing is deactivated; an info toast says the reactivated items stay active.
- Presets (edit/clone/AI draft) that reference an inactive entity do **not** reactivate it: only user picks do.
- Investment forms use `PATCH api.v1.investments.patch-active` (Phase 4), not the account-entity route.

## 8. Phases

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Backend: additive `reference-data` fields + `investments`, `usage` endpoint and service, `/meta` feature flag; frontend `select-data` module (ranking, loader) with unit tests | **Done** |
| 2 | `createLocalSelect`, dropdown CSS, standard form account/payee selects, inactive reactivation, loading/error state, translations, browser tests | **Done** |
| 3 | Categories and tags in transaction item rows (`TransactionItem.vue`) | **Done** |
| 4 | Investment form (`TransactionFormInvestment.vue`): account and investment selects | **Done** |
| 5 | "Apply usual items" redesign (`TransactionItemContainer.vue` + payee stats amounts) | Done |
| 6 | Measure payload/response time; decide on the server cache (D7); mobile-contract confirmation | Validated, no server cache needed |

Step-by-step instructions per phase are in [`status-and-handoff.md`](status-and-handoff.md).

## 9. Acceptance criteria (whole effort)

- Opening a dropdown, typing and selecting in the transaction forms makes **no network request** (verify in the browser
  network panel on standard form, investment form, and in `ModalStandard`).
- Selecting an account/payee/investment sets currency and default category synchronously from the preloaded data.
- Search finds entities by alias and by name ignoring case and accents, including inactive ones.
- Suggestions show "Most used" then "Recent" (no duplicates) following 6.1/6.2.
- An inactive payee can be picked in the modal, is reactivated, and is deactivated again after saving unless the user opts out.
- The form never appears broken while loading: selects locked with "Loading…", an error with Retry on failure.
- "Apply usual items" follows Phase 5 rules, with no fractions in 0-decimal currencies.
- Existing reference-data API keys are unchanged (mobile contract); all existing Pest browser tests for the forms pass.
- New strings are in all `lang/*.json` files (`en` is the source) with the native-speaker review reminder given to the user.
