# Investment settlement (as built)

A user who holds investments in several currencies keeps one account per currency at the same platform: one in their
default currency (the **parent**) and one per foreign currency (the **children**). An investment must share its account's
currency, so a foreign share is held in the child. Its cash then has to be moved to the parent (after a sale, dividend or
interest payment) or from it (before a purchase). Without this feature that is a second, hand-entered transfer, and
rounding it by hand leaves a residue in the child account.

## Settlement account link
- `accounts.settlement_account_id` (nullable FK to `account_entities`, `ON DELETE SET NULL`), `Account::settlementAccount()`.
- Set on the account form (`resources/views/accounts/form.blade.php`, "Settles into"). Options come from
  `AccountEntityController::settlementAccountOptions()`: the user's accounts in their default currency, minus the account
  itself.
- `AccountEntityRequest::validateSettlementAccount()`: the user must have a default currency; the account being edited
  must **not** be in it; the settlement account must be the user's own account (not a payee) **in** the default currency.
  So the link is always exactly one level deep.
- `GET /api/v1/reference-data` returns `settlement_account_id` on every account (the transaction forms read it from there).

## Posting with a settlement
- `POST /api/v1/transactions/investment` accepts an optional `settlement: {amount}`. The amount is in the settlement
  account's currency (the user's default currency): what the broker or bank actually booked.
- `TransactionRequest::settlementAllowedRule()` allows it only for `action` create/clone/enter/finalize, `schedule` false,
  a transaction type with a cash flow (`amountMultiplier() !== null`), and an account that has a settlement account.
  `settlement` is `prohibited` on standard transactions.
- `TransactionCreationService::create()` calls `InvestmentSettlementService::createTransfer()` inside the same database
  transaction as the investment transaction, so both are saved or neither is. `TransactionCreated` is dispatched for
  both afterwards. The response gains `settlement_transaction` (null when none was created).
- The transfer's foreign side is the investment's cash flow at 4 dp, half-up: the same value `MoneyCast` stores as
  `cashflow_value`. The child account is therefore left with no residue. Positive cash flow: child → parent
  (`amount_from` = cash flow, `amount_to` = entered amount). Negative: parent → child (`amount_from` = entered amount,
  `amount_to` = |cash flow|). A zero cash flow is a validation error.
- The transfer stores `transactions.settles_transaction_id` (FK to `transactions`, `ON DELETE SET NULL`).
  `Transaction::settlementTransfer()` / `settles()`. Deleting either transaction keeps the other; the link is cleared.

## Frontend
- `TransactionFormInvestment.vue`: when the selected account has a settlement account and the transaction is new,
  unscheduled and has a non-zero total, a field in the default currency appears under the total cashflow value, with
  the implied exchange rate. Empty means no transfer. `settlementAccount`/`baseCurrency` are set in
  `onAccountSelected()` because the reference data is not reactive.
- Editing an investment transaction that has a settlement transfer shows a notice with a link to edit the transfer.
  Edits are **not** propagated to the transfer.

## Tests
`tests/Feature/InvestmentSettlementTest.php`: account form validation and options, reference data, sell/dividend/buy
directions and amounts, every rejection path (with nothing saved), standard transactions, deletion keeping the transfer.
Not covered: Vue behaviour (manual), propagation of edits (not built).

## Not built
- Updating the transfer when its investment transaction is edited.
- A "sweep the balance to the parent" action on the child account page.
