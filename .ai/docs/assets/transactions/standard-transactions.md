# Standard Transactions

## Feature Summary

Standard transactions are the everyday cashflow records of YAFFA. They cover money entering an account, leaving an account, or moving between accounts without involving an investment holding.

This is the part of the transaction system most users interact with most often during daily tracking.

## Target User

- Primary:
  Users recording regular income, spending, and cash movement between accounts.

- Secondary:
  Users reviewing categorized cashflow and building budgeting habits.

## User Problem

- Users need to capture normal financial activity clearly and consistently.
- They need to distinguish between money spent, money received, and money simply moved internally.
- They often need more detail than a single category can provide.

## User Value / Benefit

- Makes daily transaction entry fast and understandable.
- Supports precise cashflow tracking and category-based analysis.
- Keeps transfers separate from true income and spending, which improves reporting clarity.

### Functional Benefits

- Supports withdrawals, deposits, and transfers.
- Supports category allocation through transaction items for withdrawals and deposits.
- Supports optional scheduling and budgeting for future planning.

### Conceptual Benefits

- Helps users separate external money movement from internal account management.
- Encourages accurate financial habits by making classification explicit.

## Technical Description

Standard transactions use a standard configuration model containing:

- source account or payee reference
- destination account or payee reference
- amount from and amount to, which is usually the same but can differ for transfers involving currency conversion
- optional comment
- optional reconciliation flag for historical transactions

The meaning of those fields changes with the subtype.

## Subtype Breakdown

### Withdrawal

A withdrawal represents money leaving one of the user’s accounts and going to a payee or expense destination.

Typical meaning:

- purchase
- bill payment
- outgoing cashflow

Typical properties:

- account from: a real account where money is leaving
- account to: a payee entity where money was spent
- amount: the outgoing amount (stored in both amount from and amount to in the backend for consistency, but shown as a single amount in the UI)
- transaction items: optional but highly recommended, used to allocate spending across categories
- optional comment
- optional reconciliation flag for historical transactions

### Deposit

A deposit represents money coming into one of the user’s accounts from a payee or income source.

Typical meaning:

- salary
- refund
- reimbursement
- incoming cashflow

Typical properties:

- account from: a payee entity where money was received from
- account to: a real account where money is entering
- amount: the incoming amount (stored in both amount from and amount to in the backend for consistency, but shown as a single amount in the UI)
- transaction items: optional but highly recommended, used to allocate income across categories
- optional comment
- optional reconciliation flag for historical transactions

### Transfer

A transfer represents money moving between two of the user’s own accounts.

Typical meaning:

- moving money from checking to savings
- paying a credit card from another account
- internal rebalancing between accounts

Typical properties:

- account from: a real account where money is leaving
- account to: another real account where money is entering
- amount: the amount being moved
  - Important: in the background, both amount from and amount to are stored for consistency, but the UI should show this as a single amount if the currencies are the same. If the currencies differ, the user must enter both the source and destination amounts. Additionally, this conversion value does not have to match the currency exchange rate for the same day and same currency pair.

Important distinction:

- a transfer is not spending and not income from the user’s overall perspective
- it should be interpreted as internal movement rather than category-based cashflow (for this same reason, transfer transactions do not allow transaction items for category allocation)

## Inputs

- transaction subtype
- source and destination account(s) or payee
- schedule of budget flag (the concept of scheduling and budgeting is described in a separate file)
- amount field(s)
- date or schedule configuration
- optional comment
- optional reconciliation flag for historical transactions
- optional transaction item(s) and their properties (described in a separate file)

## Outputs

- a saved standard cashflow record
- categorized spending or income detail
- updated account-level reporting and forecasting inputs

## Core Logic / Rules

- Withdrawal and deposit are payee-linked cashflow transactions.
- Transfer links two real accounts and should remain neutral at portfolio level.
- Standard transactions can be historical, scheduled, budgeted, or both planned and scheduled.
- Reconciled status is only meaningful for historical standard transactions.
- Budget mode is available for standard cashflow planning, but not for transfer behavior.
- Transaction items are most important for withdrawal and deposit because those are the main categorized cashflow use cases.

## User Flow

1. User chooses withdrawal, deposit, or transfer.
2. YAFFA adapts the source and destination fields accordingly.
3. User enters the amounts and optional comment.
4. For deposit or withdrawal, the user can split the transaction into multiple categorized items.
5. The transaction is saved as historical or planning-oriented depending on the selected mode.

## Edge Cases / Constraints

- Transfer should not be mixed conceptually with normal spending or income.
- Budget mode is not intended for transfer scenarios.
- Scheduled and budgeted standard transactions cannot be reconciled until they become actual historical records.

## Dependencies

- Models:
  - Transaction
  - TransactionDetailStandard
  - TransactionItem
- Related concepts:
  - Account
  - Payee
  - Category
  - Tag

## Frontend Interaction

- The standard transaction form presents buttons for withdrawal, deposit, and transfer.
- The same form can expose scheduling and budget controls when appropriate.
- Split items are shown as part of the detailed entry workflow.

## Confidence Level

High

## Assumptions

- Standard transactions are the default daily-use financial record in YAFFA.
- Reporting details such as monthly summaries and forecasts are built on top of these entries rather than being their primary purpose in the UI.
