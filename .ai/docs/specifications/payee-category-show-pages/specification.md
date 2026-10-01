# Payee and Category Show Pages — Specification

## 1. Purpose

Add a detail ("show") page for **payees** and **categories**, the two core assets that still
lack one (accounts have an overview page, investments a detailed one). Build both pages from the
reporting widgets that already power *Find transactions*, and in the same effort extract a
single reusable **transaction table** component so that the transaction lists stop being
re-implemented per page.

Product intent: in line with YAFFA's focus on conscious tracking, each page should answer a
reflective question, not just list records:

- **Payee:** *what is my relationship with this counterparty?* (how much, how often, for what,
  from which accounts, what's coming up)
- **Category:** *how does this part of my life behave over time?* (trend, plan vs. reality, who
  it goes to, how it's structured)

## 2. Background

### 2.1 Current state

- `AccountEntityController::show()` renders an account page for `config_type = 'account'` and
  redirects back for payees. `categories` is registered with `->except(['show', 'destroy'])`.
- The payee and category lists already link to *Find transactions* with a preset filter
  (`reports.transactions` with `payees[]` / `categories[]`); that is the closest thing to a
  detail view today.
- `GET /api/v1/transactions` already filters by `payees[]` and `categories[]`. The category
  filter expands to child categories (`CategoryService::getChildCategories()`).
- `GET /api/v1/transactions/scheduled-items?type=schedule` already filters by
  `accountSelection=selected&accountEntity={id}` (matches `account_from_id` **or**
  `account_to_id`, so it works for payee ids) and by `categories[]`.
- `/reports/budgetchart` already accepts preset `categories[]` from the URL.
- `GET /api/v1/payees/{id}/category-stats` (`PayeeStatsApiController`) and the category
  suggestion accept/dismiss endpoints exist.

### 2.2 Reporting widgets (`resources/js/reports/components/widgets/`)

All widgets are driven by the same contract and are therefore not tied to their page:

| Prop | Meaning |
| --- | --- |
| `transactions` | result of `GET /api/v1/transactions`, mapped through `processTransaction()` (`@/shared/lib/helpers`) |
| `busy` | loading state |
| `matchingItemsOnly`, `categoryIds`, `tagIds` | *(category-aware widgets only)* restrict sums to the matching items of split transactions |

Coupling to *Find transactions* is limited to:

- file names (`ReportingCanvas-FindTransactions-*`) and location under `reports/`;
- aggregation helpers imported from `reports/components/find-transactions/helpers.js`
  (`itemMatchesActiveFilters`, `aggregateTransactionsByCategory`,
  `aggregateTransactionsForWaterfall`);
- the tab block that hosts them, which is inline in `FindTransactions.vue` (~L250–335);
- the TransactionList widget's drill-down banner and MonthlyBreakdown's `drill-down` event;
- MonthlyBreakdown links to `categories.edit`.

Gap: **`MonthlyTimeline` and `Summary` do not accept `matchingItemsOnly`/`categoryIds`/`tagIds`.**
On a category page they would count the whole amount of a split transaction instead of the
matching items only.

### 2.3 Transaction tables

Five separate DataTables setups exist today. They share column definitions
(`transactionColumnDefinition`), action buttons, quick view, and delete helpers from
`@/shared/lib/datatable`, but each re-implements the lifecycle (initialise, redraw on data change,
destroy, recalculate layout when a tab becomes visible, delete handling).

| Where | Data source | Specifics |
| --- | --- | --- |
| `reports/.../ReportingCanvas-FindTransactions-TransactionList.vue` | Vue prop | drill-down banner, own `Swal` delete flow |
| `investments/components/display/TransactionHistoryCard.vue` | Vue prop | investment columns |
| `account/show.js` (history + schedule) | jQuery, ajax on demand | signed amount from the account's point of view (`amountCustom`, reads `window.account.config.currency`), reconciled filter |
| `account/history.js` (history + schedule) | jQuery, ajax | running balance (`running_total`), reconcile toggle |
| `reports/schedules.js` | jQuery, ajax, budget rows included | skip / enter / replace actions |

## 3. Goals / Non-Goals

**Goals**

- G1. Payee show page reachable at `account-entity.show` for payees.
- G2. Category show page at `categories.show`.
- G3. Reporting widgets moved to a shared location with page-neutral names, and reused by
  *Find transactions*, the payee page and the category page.
- G4. Correct item-level totals on the category page (G3 widgets all honour `matchingItemsOnly`).
- G5. One shared `TransactionTable` Vue component, used by *Find transactions*, both new pages,
  and the investment transaction history.
- G6. Entry points to the new pages from existing screens (list rows, MonthlyBreakdown links).

**Non-Goals** (candidates for later, see §10)

- Recurring-payment detection ("this looks monthly — create a schedule?").
- Anomaly or price-increase alerts.
- Turning `budgetchart.js` into an embeddable component; the category page links to it instead.
- Migrating `account/show.js`, `account/history.js`, `reports/schedules.js` to `TransactionTable`.
- Server-side pagination for transaction tables.
- Any change to the payee/category data model or to suggestion logic.

## 4. Assumptions

- No new API endpoints are needed for the widgets, the transaction table or scheduled items;
  the existing filters cover them (§2.1). The only new server-side data is the lifetime overview
  (§6.1).
- Base-currency totals use the stored `*_base` columns (`amount_from_base`, `amount_to_base`,
  `amount_in_base`), as the existing reports do.
- Authorization reuses `AccountEntityPolicy::view` and `CategoryPolicy::view` (owner-only).
- New pages are Blade pages that mount one Vue island each, like the investment show page.

## 5. Page Contents

### 5.1 Payee page

The payee is `account_to` on withdrawals and `account_from` on deposits. **Payee totals are
transaction-level amounts** (`transaction_details_standard`), never item sums.

| # | Section | Source |
| --- | --- | --- |
| P1 | **Overview card**: name, active, default category, import alias, preferred / excluded categories; first & last transaction date, days since last; transaction count; lifetime total paid and received (base currency) | server-rendered (§6.1) |
| P2 | **Actions**: edit (existing `PayeeForm` modal), merge (`payees.merge.form` with source preset), new transaction with this payee, open in *Find transactions*, delete (only when unused, existing rule) | existing routes / API |
| P3 | **Pending category suggestion** with accept / dismiss, if eligible | existing suggestion endpoints |
| P4 | **Report tabs** (§7.2) over the selected period: Summary, Timeline, Category details, Monthly breakdown, Waterfall, Transaction list | `GET /api/v1/transactions?payees[]={id}` |
| P5 | **Upcoming scheduled transactions** with this payee | `scheduled-items?type=schedule&accountSelection=selected&accountEntity={id}` |
| P6 | **Similar payees** (possible duplicates) with a merge link | `GET /api/v1/payees/similar` |

The Category details tab shows the payee's category split. That lets the user check whether
the default category matches actual use, next to the suggestion in P3.

### 5.2 Category page

Category totals are **item-level** (`transaction_items.amount_in_base`). Every widget on this
page receives `matchingItemsOnly: true` and `categoryIds: [id, ...childIds]`.

| # | Section | Source |
| --- | --- | --- |
| C1 | **Overview card**: full name, parent (link), children (links), description, active, default aggregation; item count, first & last use; lifetime totals, including subcategories | server-rendered (§6.1) |
| C2 | **Actions**: edit, merge (`categories.merge.form` with source preset), open in *Find transactions*, open *budget chart* preset to this category (`reports.budgetchart?categories[]={id}`) | existing routes |
| C3 | **Report tabs** (§7.2) over the selected period. For a parent category, Category details / Monthly breakdown show the split between subcategories | `GET /api/v1/transactions?categories[]={id}` |
| C4 | **Budgets** for this category: amount, recurrence (virtual attributes), active state, next occurrence | server-rendered (§6.1), via `RecurrenceRuleService` |
| C5 | **Scheduled transactions** using this category | `scheduled-items?type=schedule&categories[]={id}` |
| C6 | **Payee links**: payees defaulting to / preferring / excluding this category (links to their show pages) | `payeesDefaulting`, `payeesPreferring`, `payeesNotPreferring` |
| C7 | **AI learning entries** (description → this category, usage count) with a link to `/category-learning` | `CategoryLearning` where `category_id` |

"Top payees for this category" doesn't need a separate section: it's the payee grouping of
the Transaction list and Summary data. Add a dedicated widget only if that turns out to be
insufficient (§10).

### 5.3 Period selection

Both pages load transactions for the date range in the user's **"Default date range for
transaction history"** setting (`account_details_date_range`; the label was previously "for account
details"), the same setting the account page uses. Reuse `shared/ui/date/DateRangeFilterCard.vue`
with that preset as its initial value, so the user can adjust or clear the range like elsewhere.
With the value "Don't load data by default" nothing is requested until a range is picked. There is
no dedicated "load all" control: clearing the range does that.
The overview card (P1/C1) always shows lifetime figures from the server and doesn't depend on the
selected range.

Because the widgets aggregate in the browser, loading a very long range is slow for large
histories (§9 R2); that is the user's choice through the setting or the range card.

## 6. Backend Scope

### 6.1 Controllers, services, views

- **Routes**: `categories` resource: drop `show` from `except`. Payees keep
  `account-entity.show` (no route change).
- **`AccountEntityController::show()`**: add the payee branch → `payees.show` view. Keep the
  account branch untouched.
- **`CategoryController::show()`**: new, `authorize('view')`, → `categories.show` view.
- **Lifetime overview aggregates** (P1, C1): one aggregate query each, in a service, not in the
  controller and **not** via the `Payee::transactions()` / `get*TransactionDateAttribute()`
  accessors. Each of those loads every transaction into memory and the load repeats per
  accessor.
  - Payee: `COUNT`, `MIN(date)`, `MAX(date)`, and `SUM` of base amounts split by withdrawal /
    deposit, over non-schedule standard transactions where the payee is `account_from_id` or
    `account_to_id`.
  - Category: `COUNT` of items, `MIN/MAX(transactions.date)`, `SUM(amount_in_base)` split by
    transaction type, over the category and its children.
  - Placement: add a method to `PayeePersistenceService` / `CategoryService` or a small
    dedicated stats service. Choose during implementation; no new abstraction beyond that.
- **Budgets (C4)** and **learning entries (C7)**: eager-loaded in the controller and passed to the
  view. The next occurrence goes through `RecurrenceRuleService` (CLAUDE.md rule).
- **Views**: `resources/views/payees/show.blade.php`, `resources/views/categories/show.blade.php`,
  following the layout of `investments/show.blade.php` (title postfix, header, one Vue mount point,
  initial data via `window.*` / JSON like the investment page).

### 6.2 Policies / auth

- Owner-only via existing `view` policies. A foreign payee id or an account id passed where a
  payee is expected must not leak data: the payee branch is chosen by `config_type`, and the policy
  runs before either branch.

### 6.3 API

- No new endpoints. If a JSON variant of the overview aggregates turns out to be needed (e.g. for
  refreshing after an in-page edit), add it to `PayeeStatsApiController` with `abilities:read` and
  a matching row in `ApiAbilityEnforcementTest`'s data provider (CLAUDE.md rule).

## 7. Frontend Scope

### 7.1 Directory moves and renames (approved)

| From | To |
| --- | --- |
| `reports/components/widgets/ReportingCanvas-FindTransactions-Summary.vue` | `shared/ui/reports/TransactionSummary.vue` |
| `…-Timeline.vue` + `MonthlyTimeline.vue` | `shared/ui/reports/TransactionTimeline.vue` + `shared/ui/reports/MonthlyTimelineChart.vue` |
| `…-CategoryDetails.vue` + `WithdrawalsByParentCategory.vue` + `DepositsByParentCategory.vue` | `shared/ui/reports/CategoryDetails.vue` + the two pie charts alongside |
| `…-MonthlyBreakdown.vue` | `shared/ui/reports/MonthlyBreakdown.vue` |
| `…-Waterfall.vue` | `shared/ui/reports/TransactionWaterfall.vue` |
| `…-TransactionList.vue` | `shared/ui/datatable/TransactionTable.vue` (§7.3) |
| `PieChartLoader.css` | `shared/ui/reports/` |
| aggregation helpers in `find-transactions/helpers.js` (`itemMatchesActiveFilters`, `aggregateTransactionsByCategory`, `aggregateTransactionsForWaterfall` and their private dependencies) | `shared/lib/reports/index.js` |

The *Find transactions* cache-key and URL helpers (`buildFilterCacheKey`,
`buildBreakdownCacheKey`, …) stay in `find-transactions/helpers.js`. Final file names can be
adjusted during implementation. The rule is page-neutral names under `shared/`.

`WithdrawalsByParentCategory.vue` and `DepositsByParentCategory.vue` are near-identical (291 lines
each). Merging them into one component with a `transactionType` prop is optional; do it only if it
falls out of the move naturally.

### 7.2 `TransactionReportTabs` component

- Extract the tab block from `FindTransactions.vue` into
  `shared/ui/reports/TransactionReportTabs.vue`.
- Props: `transactions`, `busy`, `matchingItemsOnly`, `categoryIds`, `tagIds`, and optionally
  `tabs` (which tabs to show, default all).
- Owns the active-tab state and passes `isActive` to the table.
- Re-emits `drill-down` and `transaction-deleted`. *Find transactions* keeps its in-page
  drill-down behaviour unchanged.
- **No in-page drill-down on the new pages** (owner decision). They handle `drill-down` by
  navigating to *Find transactions* (`reports.transactions`) with the page's own asset prefilled
  (`payees[]={id}` on the payee page, `categories[]={id}` on the category page), plus the
  `date_from` / `date_to` and `categories[]` carried by the drill-down payload. All of these are
  URL parameters *Find transactions* already reads.
- The drill-down banner moves out of the list widget into `FindTransactions.vue`, or into this
  component, whichever keeps *Find transactions* behaviour identical. It isn't shown on the new
  pages.

### 7.3 `TransactionTable` component

Promote the current TransactionList widget (it's closest to generic) to
`shared/ui/datatable/TransactionTable.vue`.

- Props:
  - `transactions`, `busy`, `isActive` (layout recalc);
  - `columns`: list of keys mapped to `transactionColumnDefinition` entries, e.g.
    `['date', 'type', 'fromTo', 'category', 'amount', 'extra']` (default = today's
    *Find transactions* set);
  - `actions`: list of `dataTablesActionButton` keys, default
    `['quickView', 'show', 'edit', 'clone', 'delete']`;
  - `perspectiveEntity` *(optional)*: `{ id, currency }`. When set, a signed-amount column is
    available (outflow negative / inflow positive from that entity's point of view), replacing
    the `window.account` read in `amountCustom`.
- Emits `transaction-deleted`. Delete uses the shared `confirmDelete` / delete helper instead of
  the widget's own `Swal` block.
- No column plugin system and no server-side mode. A list of keys covers every consumer in scope.

Consumers in scope: *Find transactions*, payee page, category page,
`investments/components/display/TransactionHistoryCard.vue`.

Explicitly out of scope: `account/show.js` (jQuery; best next candidate thanks to
`perspectiveEntity`), `account/history.js` (running balance), `reports/schedules.js` (budget rows,
schedule-only actions).

### 7.4 Widget fixes

- `TransactionSummary` and `TransactionTimeline` / `MonthlyTimelineChart`: accept and honour
  `matchingItemsOnly`, `categoryIds`, `tagIds` via `itemMatchesActiveFilters`. When the flag is
  set, sums use matching item amounts only. *Find transactions* passes its existing values, so its
  "matching items only" toggle now also applies to Summary and Timeline. Treat this as an
  intentional fix and mention it in the changelog.
- `MonthlyBreakdown`: category links go to `categories.show` instead of `categories.edit`.

### 7.5 Pages and entry points

- New entry scripts for the two pages (e.g. `resources/js/payee/show.js`,
  `resources/js/categories/show.js`) mounting one root component each, registered like the
  existing page entries.
- Formatting only through `@/shared/lib/i18n` helpers with user locale and base / account
  currency. No custom formatters.
- Links to the new pages, limited to these places:
  - payee list: name → `account-entity.show`; keep "Show transactions";
  - category list: name → `categories.show`;
  - payee / category names inside the new pages (C1 parent/children, C6 payee links) and the
    category links in `MonthlyBreakdown` (§7.4).
- Payee or category names in the shared transaction column definitions
  (`transactionColumnDefinition.payee`, `.category`) are **not** turned into links (owner
  decision).

## 8. Implementation Phases

1. **Move & rename**: widgets and helpers to `shared/` (§7.1); update *Find transactions*
   imports. No behaviour change. Rebuild assets, run existing *Find transactions* browser tests.
2. **Extract components**: `TransactionReportTabs` and `TransactionTable` (§7.2, §7.3); switch
   *Find transactions* and `TransactionHistoryCard` to them. *Find transactions* must behave
   exactly as before.
3. **Widget fixes** (§7.4).
4. **Backend**: routes, controller branches, aggregate queries, views (§6).
5. **Payee page** (§5.1), then **category page** (§5.2).
6. **Entry points** (§7.5), docs (§11).

Phases 1–3 are one PR together with 4–6 (owner decision: table changes ship with the main
changes). Keep them as separate commits.

## 9. Risks / Open Questions

Resolved decisions (owner, 2026-09-29):

- **Q1. Default period:** the user's "Default date range for transaction history" setting (§5.3).
- **Q2. Drill-down on the new pages:** none in-page; it links to *Find transactions* with the
  payee or category prefilled (§7.2).
- **Q3. Links in transaction tables:** not added automatically (§7.5).

Risks:

- **R1. Behaviour change in *Find transactions*.** §7.4 changes Summary/Timeline numbers when
  "matching items only" is on. This is correct, but visible to users.
- **R2. Large datasets.** *Measured in step 0* (headless Chromium, synthetic data, no tags): API
  ~1.4 ms and ~3.6 KB per transaction; Find transactions took ~5 s to load at 1,000 rows and
  ~16-18 s at 3,000 (10 MB payload, ~10 s of it browser rendering); at 10,000 rows the API call
  failed/timed out and the page never rendered. All widgets render eagerly on load (later tab
  switches are cheap), so lazy tab rendering would not help. **Decision:** load only the user's default date range (§5.3), so history size is bounded by a
choice the user controls. The overview aggregates stay server-side and lifetime. Server-side chart aggregation or a row cap remain options if "all" proves
  too slow for real users; the 10,000-row API failure cause (probably a PHP memory/time limit) was
  not identified.
- **R3. Move churn.** Phase 1 touches many import paths. Keep it a pure move so review is easy.
- **R4. Docs discrepancy to fix.** `.ai/docs/assets/payee/overview.md` "Assumptions" says there
  is no show page; update when shipped.

## 10. Future Directions

- Recurring-payment detection on the payee page, with a "create schedule" action.
- Price-drift / anomaly hints ("last 3 payments 12% above the 12-month average").
- Embeddable actual-vs-budget chart (refactor `budgetchart.js`) for the category page.
- A dedicated "top payees" widget for categories, if the list/summary grouping is not enough.
- Migrate `account/show.js` to `TransactionTable` using `perspectiveEntity`.
- Fix `Payee::transactions()` and its accessors (list page performance), independent of this
  work.

## 11. Test Strategy

New tests are **Pest 5** (CLAUDE.md). Browser tests only for the critical flows, in
`tests/PEST/Pages/`.

**Backend (feature)**

- Payee show: owner gets 200 with overview figures; other user gets 403; account entities still
  render the account page (regression); a payee with no transactions renders with empty-state
  values.
- Category show: owner 200, other user 403; parent category totals include children; split
  transaction counts only the matching item amount; budgets listed with next occurrence.
- Aggregate queries: withdrawal vs. deposit split for payees; scheduled (`schedule = 1`)
  transactions excluded; multi-currency data summed via base amounts.

**Frontend (browser, Pest)**

- *Find transactions*: existing tests pass unchanged after phases 1–2 (tab switching, list,
  delete, drill-down).
- Payee page: loads, report tabs render, transaction list shows only this payee's transactions,
  delete from the list updates the table.
- Category page: loads with item-level totals for a split transaction.
- Monthly breakdown on either page: clicking a cell opens *Find transactions* with the asset,
  month and categories prefilled.
- Investment show: transaction history still renders (regression for `TransactionTable`).

**Edge cases / negative paths**

- Inactive payee / category: page still available.
- Payee with only deposits; category used only in schedules or budgets.
- Category whose parent was merged or deleted.
- Base amount missing (`*_base` null) — verify how existing reports treat it and match that.

## 12. Acceptance Criteria

- Given a payee I own, when I open `account-entity.show` for it, then I see its overview,
  report tabs covering my default transaction-history date range, and its upcoming schedules.
- Given the monthly breakdown on a payee or category page, when I click a month/category cell,
  then *Find transactions* opens with that payee or category, the month's date range and the
  clicked categories prefilled.
- Given a payee I don't own, when I open its show URL, then I get 403.
- Given a category with a split transaction, when I open its page, then all figures include
  only that category's item amounts (and its children's).
- Given a parent category, when I open its page, then the category breakdown shows its
  subcategories.
- Given *Find transactions*, after the refactor, then it behaves as before, except that
  Summary/Timeline now honour "matching items only".
- Given the investment show page, after the refactor, then its transaction history works as
  before.
- No file remains under `resources/js/reports/components/widgets/` with a
  `ReportingCanvas-FindTransactions-` name; all consumers import from `shared/`.
- `.ai/docs/assets/payee/overview.md` and `.ai/docs/assets/category/overview.md` mention the
  show pages; a feature doc under `.ai/docs/features/` is extracted after implementation.
