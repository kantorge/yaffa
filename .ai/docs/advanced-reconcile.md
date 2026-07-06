# Advanced Reconcile

Advanced Reconcile adds statement-style reconciliation for account cash, investment value, and total account value.

## Account Page

The account detail page includes a collapsed Advanced Reconcile panel above transaction history. It uses the page date filter as the reconciliation period.

- Cash compares opening cash balance, period withdrawals, period deposits, and closing cash balance.
- Investment compares opening market value and closing market value.
- Total compares cash plus investment value.
- Each section can save a checkpoint value and note for the period end date.
- Investment holdings list positions with non-zero opening quantity, closing quantity, buys, or sells in the period.
- Missing investment prices are treated as zero for valuation and flagged for user entry.
- Opening and closing price cells are clickable. Users can save a price directly, or enter the statement value of the holding and let Yaffa derive the unit price from the quantity held at that date.

## Investment Price Entry

The investment holdings price modal has two modes:

- `Price at date` stores the entered unit price for the opening or closing statement date.
- `Value at date` divides the entered holding value by the opening or closing quantity and stores the derived unit price for that statement date.

If a stored investment price already exists on the exact statement date, saving from the modal updates that price. If the displayed price came from an earlier stored price or a transaction price, saving creates a new stored price on the statement date.

## Checkpoints

Checkpoint records are stored in `account_balance_checkpoints`.

- `checkpoint_type` is one of `cash`, `investment`, or `total`.
- `checkpoint_date` represents the statement/checkpoint date.
- Multiple manual checkpoints are allowed; the latest active checkpoint for the same account, type, and date is used on the account page.
- Source document uniqueness is reserved for document-imported checkpoints with `source` and `source_document_id`.

## Dashboard

The Advanced Reconcile report shows active accounts down the left and the latest 12 calendar months across the top, newest first.

- The report can be filtered by checkpoint type.
- The display can show status or saved checkpoint balance.
- Statuses are `Matched`, `Reconcile required`, and `No checkpoint`.
- Reconcile required cells include the variance and link to the account page with date parameters.

## Current Assumptions

- Cash balance uses the same ledger semantics as Yaffa account balances: standard account movements plus investment transaction cashflows.
- Investment value uses quantity held multiplied by the latest known combined price on or before the target date.
- Total is cash plus investment value.
- Dashboard month assignment uses the latest active checkpoint whose checkpoint date falls inside that calendar month.
- Dashboard variance compares the checkpoint value to the calculated balance on the checkpoint date.
