# Part 1 — Account filter on the investment detail page

Read `specification.md` (shared contract) first. Self-contained session brief.

## Goal
On the investment show page, let the user switch between "All accounts" (today's behaviour) and a single account. The filtered view scopes transactions, current quantity, quantity history, and the Results calculations. Prices stay unfiltered.

## Backend
1. Extract one private builder shared by `InvestmentController::show` (`app/Http/Controllers/InvestmentController.php`) and `InvestmentApiController::getDisplayData` (`app/Http/Controllers/API/InvestmentApiController.php`), taking `?AccountEntity`. When set:
   - filter `transactionsBasic()` and the scheduled instances by `transaction_details_investment.account_id`;
   - pass the account to `getCurrentQuantity`;
   - pass it to `enrichInvestmentWithQuantityHistory` (`app/Services/InvestmentService.php`, add optional `?AccountEntity $account = null`, apply the same account filter to its query).
2. Add `accounts` (id, name) to both responses: distinct accounts with transactions for the investment (always computed over *all* the investment's transactions, regardless of the active filter). Eager-load as needed.
3. Validate `account` with a small FormRequest (nullable integer, exists, owned by user, account-type entity). Foreign/invalid → 403/404.
4. `display-data` route is already `abilities:read`; no new route.

## Frontend (`resources/js/investments/components/display/`)
1. `InvestmentDisplayContainer.vue`: new props `accounts`, `initialAccount`; data `selectedAccountId`, loading/error flags. Plain `<select class="form-select">` ("All accounts" + accounts), hidden when ≤1 account. Blade `resources/views/investments/show.blade.php` passes the props.
2. On change: fetch `/api/v1/investments/{id}/display-data?account=…`, replace investment/transactions/quantities (reuse the post-delete refresh path `onDeleteTransaction`, and make delete refresh keep the current account); `history.replaceState` updates `?account=`.
3. Fix `QuantityHistoryCard.vue` / `PriceHistoryCard.vue`: charts are built once in `mounted()` with no data watcher → add a watcher that sets `chart.data` (also fixes the existing stale chart after delete).
4. `ResultsCard.vue` computes date bounds once in `data()`: reset dates (`resetDates`) when the transaction set changes.
5. `CurrentAssetsCard`, `TransactionHistoryCard` need no change. Optional: account column in the history table when viewing all accounts (needs `config.account` eager-loaded) — only if cheap.
6. New strings ("All accounts", "Account", error text) → all `/lang/*.json`.

## Tests
- Pest feature (extend `tests/Feature/InvestmentTest.php` or new file): show + display-data with `?account=` filter transactions, `current_quantity`, quantity history; foreign account denied; `accounts` list content.
- Unit: `enrichInvestmentWithQuantityHistory` with/without account (currently untested).
- Browser (optional, `tests/PEST/Pages/Investments/`, model on `InvestmentTransactionHistoryTest.php`): selecting an account changes history rows and results.
- Vitest not needed (`investmentReturn.js` unchanged).

## Done when
Investment held in 2 accounts: All vs each account correct for table, Owned quantity/value, Results, quantity chart; delete while filtered keeps filter; URL reflects selection and reload restores it; lint/Pint/PHPStan/targeted tests green.
