# Investment account scope (as built)

Spec: `.ai/docs/specifications/investment-account-scope/`.

## Account filter on the investment page
- `?account=<AccountEntity id>` on `investments.show` and `GET /api/v1/investments/{investment}/display-data`.
- `InvestmentDisplayRequest` validates it (nullable integer, owned by the user, `config_type = account`). Any invalid value is a **404** on web and API (does not reveal existence), not 403.
- `InvestmentService::getDisplayData(Investment, ?AccountEntity)` is the single builder for both entry points. It scopes transactions, scheduled instances, current quantity and quantity history. Prices are never filtered.
- Response also carries `accounts: [{id, name, quantity}]`, always computed over all of the investment's transactions. `quantity` is a **decimal string** (BigDecimal); the frontend uses `decimal.js` for the distribution card.
- Frontend: `InvestmentDisplayContainer.vue` (selector, in-page refetch, `history.replaceState`), `AccountDistributionCard.vue` (shown when more than one account holds a positive quantity). `CurrentAssetsCard` was merged into `InvestmentDetailsCard`.

## Investments card on the account page
- `GET /api/v1/accounts/{accountEntity}/investments` (`api.v1.accounts.investments`, `abilities:read`) returns `[{investment_id, name, quantity, price, value}]` as decimal strings, positive quantities only, sorted by value descending; missing price gives null price/value.
- Ownership as `getAccountBalance` (403); non-account entity gives 400.
- `account/show.js` fetches for **every** account (there is no account-level investment-support flag, so the spec's "skip for non-investment accounts" was dropped); the card stays hidden when empty. Refreshed after transaction create/delete. The total is summed with `decimal.js`.

## Tests
`tests/Feature/InvestmentAccountFilterTest.php`, `tests/Feature/API/AccountInvestmentHoldingsTest.php`, plus the entry in `ApiAbilityEnforcementTest.php`. Not covered: scheduled-transaction filtering, Vue behaviour, mobile layout (manual).

## Spec deviations
- Invalid `account` gives 404 (spec: 403/404).
- No fetch skip for accounts without investments (see above).
