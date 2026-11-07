# Part 2 — "Investments" card on the account page

Read `specification.md` (shared contract) first. Self-contained session brief.

## Goal
On the account show page, add a card listing investments with quantity > 0 in that account, showing quantity and current value. Each row links to `investments.show` with `account=<this account>` so the investment page opens prefiltered (the filter itself is Part 1; until it lands the link simply shows the unfiltered page — no dependency for merging).

## Backend
1. New `AccountApiController::getInvestmentHoldings(AccountEntity $accountEntity)` (`app/Http/Controllers/API/AccountApiController.php`), route `GET /api/v1/accounts/{accountEntity}/investments`, name `api.v1.accounts.investments`, `whereNumber` (see `routes/api.php` near the `accounts.balance.show` route).
2. Same ownership check as `getAccountBalance`; reject non-account entities.
3. Logic: `$accountEntity->config->getAssociatedInvestmentsAndQuantity()` → keep `quantity > 0` → load `Investment` rows with `currency` → prices via `InvestmentService::getLatestPricesBatchExact` (`priceBatchKey`) → value = quantity × price with `BigDecimal` (pattern: `AccountMonthlySummary::calculateInvestmentValueFact`). Computed live; no `busy` polling.
4. Response: `[{investment_id, name, quantity, price, value}]`, decimal strings; sorted by value desc. Price missing → `price`/`value` null (frontend shows `.not-set`).
5. Add the method to the class-level `#[Middleware('abilities:read', only: [...])]` list and to the data provider in `tests/Feature/API/ApiAbilityEnforcementTest.php` (one deny + one allow).

## Frontend
1. `resources/views/accounts/show.blade.php`: new collapsible card "Investments" in the left panel after the "Actions" card and before the "Transaction filters" heading; copy sibling card collapse markup. Hidden entirely when empty; loading spinner and error states.
2. `resources/js/account/show.js`: fetch on load (axios); render list-group rows: name (link via `route('investments.show', {investment, account: window.account.id})`), quantity with `toFormattedNumber`, value with `toFormattedCurrency(value, window.YAFFA.userSettings.locale, window.account.config.currency)` (helpers from `@/shared/lib/i18n`). Refresh alongside `getAccountBalance` after `transaction-created`/delete.
3. Only for investment-type accounts is data possible; skip the fetch/card for others if the account has no investment support (check account config; verify).
4. New strings → all `/lang/*.json`.

## Tests
- Pest feature: holdings endpoint (quantity>0 filtering incl. fully-sold and negative cases, value math, missing price, other-user denied, non-account entity rejected), account show page renders the card container.
- Ability enforcement test entry (above).

## Done when
Value per row matches "Latest owned value" for that account on the investment page (Part 1) / manual calculation; sold-out investments absent; card hidden for accounts without holdings; lint/Pint/PHPStan/targeted tests green.
