# Investment Account Scope — Overview & Shared Contract

Three loosely related features, each implementable in a **separate agent session / PR**. Each part file is self-contained; this file holds the shared context and the contracts between them. Read this first, then your part.

| Part | File | Depends on | Touches |
|---|---|---|---|
| 1. Account filter on investment page | `01-investment-account-filter.md` | — | investment show controller/API/Vue |
| 2. Investments card on account page | `02-account-investments-card.md` | link works unfiltered without Part 1 | account show Blade/JS, AccountApiController |
| 3. Mobile layout of investment page | `03-investment-mobile-layout.md` | none (merge-order note below) | investment Vue cards, shared TransactionTable |

Suggested order: 1 → 2 → 3 (3 may go first or last). Parts 1 and 3 both edit `InvestmentDisplayContainer.vue`: whichever lands second rebases; keep changes in separate hunks (1 = script/data/selector, 3 = template grid classes).

## Background
The investment detail page (`resources/js/investments/show.js` → `components/display/InvestmentDisplayContainer.vue`) merges all accounts. The account page (`resources/js/account/show.js`, `resources/views/accounts/show.blade.php`) shows only a balance summary. Users want account-scoped investment views, navigation between the two, and a usable mobile layout.

## Shared contract
- **Query parameter:** `?account=<AccountEntity id>` on `investments.show` (web) and on `GET /api/v1/investments/{investment}/display-data`. Optional; absent = all accounts. Must belong to the authenticated user and be an account-type entity, else 403/404 (use existing ownership pattern from `AccountApiController::getAccountBalance`).
- **Link built by Part 2:** `route('investments.show', {investment: id, account: accountId})` (Ziggy). Web route name is `investments.show` (plural).
- **Decisions already made:**
  - Account dropdown lists accounts that have transactions for the investment (incl. accounts whose quantity is now 0).
  - Account-card values are in the account's own currency (investments share the account currency), no base-currency conversion.
  - Switching account is in-page (no reload): refetch `display-data?account=` + `history.replaceState`.
  - Prices are per investment and are never account-filtered.

## Facts about the code (verified during exploration, re-verify before relying)
- `InvestmentService::getCurrentQuantity(Investment, ?AccountEntity)` already supports an account.
- `InvestmentService::enrichInvestmentWithQuantityHistory` is NOT account-aware (Part 1 adds it).
- `Account::getAssociatedInvestmentsAndQuantity(?Carbon)` (`app/Models/Account.php`) returns `{investment_id, quantity}` per investment for that account; it does not drop zero/negative quantities.
- `InvestmentService::getLatestPricesBatchExact` + `priceBatchKey()` give batch prices; `AccountMonthlySummary::calculateInvestmentValueFact` shows the quantity×price BigDecimal pattern.
- `account_id` lives on `config` (`TransactionDetailInvestment`) of each transaction.
- ResultsCard math (`investments/lib/investmentReturn.js`) is pure frontend over the transaction/price props.
- `InvestmentController::show` and `InvestmentApiController::getDisplayData` duplicate their data-building logic.

## Project rules that apply to all parts
- Sail prefix for all commands; rebuild assets (`sail npm run dev`) before UI testing; Pint + PHPStan before finishing PHP; ESLint for JS.
- Vue Options API, no `<script setup>`; Bootstrap utilities first; handle loading/error/empty states; no new dependencies.
- New/changed strings → update **every** `/lang/*.json` (en = source; raw translations of the others, remind the user a native speaker must review). See `.ai/docs/features/i18n/translation-process.md`.
- New API controller action → add to the `abilities:read|write` `only:` list and to `tests/Feature/API/ApiAbilityEnforcementTest.php`.
- Any `routes/web.php` change needs a matching `routes/breadcrumbs.php` entry (no web route changes planned).
- New tests in Pest 5; run only the minimal affected tests, ask before the full suite.
- Money/quantities: `BigDecimal`/`Money`, serialized as decimal strings; format on the frontend with `shared/lib/i18n` helpers using user locale (never custom formatters).
- Docs: after implementing, write the as-built note under `.ai/docs/features/investment-account-scope/` (your part only) and flag any spec/code discrepancy.
