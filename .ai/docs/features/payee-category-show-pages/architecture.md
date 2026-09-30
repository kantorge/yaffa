# Payee and Category Show Pages — Architecture (as built)

Extracted from the code on branch `feat/payee-category-show-pages`. Pre-implementation design: [`../../specifications/payee-category-show-pages/specification.md`](../../specifications/payee-category-show-pages/specification.md). Where they differ, this document and the code win; differences are listed at the end.

## What it is

A detail page for a **payee** and for a **category**, built from the same reporting widgets that power *Find transactions*. Each page has a lifetime overview (server-side), a few side cards, and a tabbed report over the user's default transaction-history date range. Both are owner-only Blade pages that mount one Vue island.

- **Payee** answers "what is my relationship with this counterparty?" — payee totals are transaction-level amounts.
- **Category** answers "how does this part of my life behave over time?" — all figures are item-level, including child categories.

## Routes and controllers

| Page | Route | Controller | Auth |
|---|---|---|---|
| Payee | `account-entity.show` (existing; account branch unchanged) | `AccountEntityController::show()` payee branch → `payees.show` | `AccountEntityPolicy::view` runs before the branch |
| Category | `categories.show` (new; `show` dropped from `except`) | `CategoryController::show()` → `categories.show` | `can:view,category` middleware |
| Payee overview JSON | `GET /api/v1/payees/{accountEntity}/overview` (`api.v1.payees.overview`) | `PayeeStatsApiController::overview` | `abilities:read`; 404 for non-payee or foreign entity |

`categories.merge.form` / `.submit` are registered **before** the `categories` resource so `merge` is not parsed as `{category}`.

Data reaches Vue via `JavaScriptFacade::put()` (`window.payee`, `overview`, `categorySuggestion`, `baseCurrency` for payees; `window.category`, `overview`, `budgets`, `learningEntries`, `baseCurrency` for categories). `resources/js/app.js` picks `payee/show` instead of `account/show` when `#payeeShow` exists.

## Lifetime overview — `App\Services\AssetOverviewService`

- `payeeOverview(user, payee)`: non-schedule standard transactions; withdrawals where the payee is `account_to`, deposits where it is `account_from`. Sums `amount_to` / `amount_from`.
- `categoryOverview(user, category)`: non-schedule withdrawal/deposit **items** of the category and its children. Sums `transaction_items.amount`.
- Both run one aggregate query grouped by currency, month and transaction type, then convert to the base currency **in PHP with the user's monthly average rates** (`CurrencyTrait`), not from stored `*_base` columns. Result: `count`, `first_date`, `last_date`, `withdrawal_total`, `deposit_total` (decimal strings, scale 4). Without a base currency, amounts are summed unconverted.
- `categoryBudgets(category)`: the category's own budgets (children not included), each with `next_occurrence` via `RecurrenceRuleService::getOccurrencesAfter()` anchored on the later of today and the budget start; `null` on an invalid rule.
- Must run in the owner's request context (`CurrencyTrait` reads `auth()`); `ratesMapFor()` returns no rates otherwise.

## Payee page contents

Overview card · actions (edit via `PayeeForm` modal, new transaction, Find transactions, merge, delete only when `overview.count === 0`) · date range card · pending category suggestion (existing `PayeeCategoryRecommendation` dashboard widget; shown only when the payee has no default category and the suggestion was not dismissed) · upcoming schedules (`scheduled-items?type=schedule&accountSelection=selected&accountEntity=`) · similar payees (`/api/v1/payees/similar`, ≥ 60 % similarity, current payee excluded) · report tabs (all six). Deleting a transaction from the list re-fetches the overview from the new endpoint. Saving the edit modal reloads the page.

## Category page contents

Overview card (parent, children, description, default aggregation, counts, totals) · actions (edit, merge, Find transactions, budget chart preset) · date range card · budgets · scheduled transactions (`scheduled-items?type=schedule&categories[]=`) · related card (payees defaulting / preferring / not preferring, AI learning entries with link to `/category-learning`) · report tabs **summary, transaction-list, timeline-charts, monthly-breakdown** with `matchingItemsOnly = true` and `categoryIds = [id, ...childIds]` (as strings).

## Shared frontend pieces (`resources/js/shared/`)

- `ui/reports/TransactionReportTabs.vue` — the tab block extracted from Find transactions. Props `transactions`, `busy`, `matchingItemsOnly`, `categoryIds`, `tagIds`, `tabs`, `useBreakdownCache`, `drillDownFilter`. Hosts the drill-down banner (shown only when `drillDownFilter` is passed, i.e. on Find transactions).
- `ui/reports/` — `TransactionSummary`, `TransactionTimeline` + `MonthlyTimelineChart`, `CategoryDetails` + the two pie charts, `MonthlyBreakdown`, `TransactionWaterfall`. No `ReportingCanvas-FindTransactions-*` file remains.
- `ui/datatable/TransactionTable.vue` — generic transaction DataTable (props `columns`, `actions`, `actionParams`, `transactions`, `busy`, `isActive`; emits `transaction-deleted`, `transaction-skipped`, `set-date-range`). Used by the report tabs and by `investments/components/display/TransactionHistoryCard.vue`.
- `lib/reports/index.js` — aggregation helpers (`itemMatchesActiveFilters`, `aggregateTransactionsByCategory`, `aggregateTransactionsForWaterfall`, …).
- Both pages load `GET /api/v1/transactions` for the chosen range (`account_details_date_range` setting, `none` = load nothing until a range is picked) with `payees[]` or `categories[]`, and discard stale responses via a request counter.

## Drill-down and entry points

- Monthly breakdown on the new pages has **no in-page drill-down**: it navigates to `reports.transactions` with the page's payee or category, the cell's categories, and `date_from` / `date_to`. On Find transactions the in-page drill-down is unchanged.
- Monthly breakdown category links go to `categories.show`.
- Name links: payee list, categories list, search results (payee name), plus names inside the new pages. Names in the shared transaction column definitions are **not** links.

## Find transactions behaviour change

Summary and Timeline now honour `matchingItemsOnly` / `categoryIds` / `tagIds`; before, only the category-aware tabs did. With "matching items only" on, their sums use only matching items of split transactions. Visible to users; recorded in `UPGRADE.md`.

## Setting rename

"Default date range for account details" → "Default date range for transaction history" (same `account_details_date_range` setting, now also used by the payee and category pages). Labels and help text changed in `UserSettings.vue`, `accounts/form.blade.php` and the four `lang/*.json`.

## Departures from the specification

| Spec | As built |
|---|---|
| §4/§6.3: no new endpoints; base sums from stored `*_base` columns | New `GET /api/v1/payees/{id}/overview` (used to refresh after deleting a transaction); base conversion from monthly average rates in PHP |
| §5.2: category tabs "same as payee" incl. category split and waterfall | Category page hides the category-charts and waterfall tabs (commits `182c8a84`, `f0496eac`) |
| §7.3: `perspectiveEntity` prop; fixed default actions | Not implemented; `TransactionTable` takes `actions` (array or per-row function) and `actionParams` instead, plus `transaction-skipped` / `set-date-range` events |
| §7.5: link places limited to list, in-page names, breakdown | Also the payee name in the search results page |
| §7.2: banner in `FindTransactions.vue` or the tabs component | Lives in `TransactionReportTabs`, only rendered when `drillDownFilter` is passed |
| §5.1 P3: accept / dismiss via a new block | Reuses the dashboard `PayeeCategoryRecommendation` widget (changed to be reusable) |
| §6.1: "budgets, learning entries eager-loaded in the controller" | Budgets through `AssetOverviewService::categoryBudgets()` |

## Permissions

Owner-only. A foreign payee or category id gives 403 (web) / 404 (overview endpoint). An account id on the payee overview endpoint gives 404; an account id on `account-entity.show` renders the account page. The overview endpoint is `abilities:read` and pinned by `ApiAbilityEnforcementTest`.

## Tests

- Feature: `tests/Feature/PayeeCategoryShowPagesTest.php`, `tests/Feature/PayeeStatsApiControllerTest.php` (overview endpoint), `tests/Feature/API/ApiAbilityEnforcementTest.php`.
- Browser (Pest): `tests/PEST/Pages/Payees/PayeeShowPageTest.php`, `tests/PEST/Pages/Categories/CategoryShowPageTest.php`, plus regression tests `tests/PEST/Pages/Reports/FindTransactions/FindTransactionsReportTabsTest.php`, `tests/PEST/Pages/Investments/InvestmentTransactionHistoryTest.php`.
- Not covered in the browser: pie charts and waterfall totals (canvas); the drill-down navigation from the breakdown on the new pages; delete-from-list on the payee page; split-transaction item totals (covered server-side only).
