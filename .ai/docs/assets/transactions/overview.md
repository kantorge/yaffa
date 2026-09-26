# Transaction

## Feature Summary

Transaction is the core financial record in YAFFA. It represents a meaningful money event or planned money event in the user’s financial life, covering everyday income and spending, internal transfers, and investment activity within one unified concept.

In product terms, this is one umbrella concept with multiple modes and subtypes. The user starts with a single mental model — “I need to record a completed or planned transaction” — and then refines it based on what kind of financial event it is.

## Target User

- Primary:
  Basic personal-finance users who actively record spending, income
  Advanced personal-finance users who actively record transfers, and investments.

- Secondary:
  Users doing recurring planning, forecasting, or correction of previously entered records.

## User Problem

- Users need one reliable way to represent money moving through their accounts and investments.
- They need to capture both historical facts and future intent.
- They need enough flexibility to describe a simple transfer, a split grocery purchase, or a stock dividend without learning multiple unrelated concepts.

## User Value / Benefit

- Gives the user one central structure for nearly all financial activity.
- Reduces manual rework by supporting both simple entries and detailed multi-line breakdowns.
- Connects short-term tracking with long-term planning and investment monitoring.

### Functional Benefits

- Supports standard and investment activity in one domain.
- Supports one-time, recurring, and planning-oriented entries.
- Supports item-level categorization for more accurate reports.
- Supports review and finalization of AI-created draft transactions.

### Conceptual Benefits

- Helps users think about their finances as a timeline of real and planned events.
- Encourages conscious financial tracking instead of opaque automation.
- Makes it easier to separate “what happened” from “what is expected to happen.”

## Technical Description

The Transaction record is the top-level container. It is shaped by three major dimensions:

1. Transaction type
   - standard
   - investment

2. Transaction subtype
   - for example withdrawal, deposit, transfer, buy, sell, dividend

3. Time mode
   - historical one-time record
   - scheduled recurring template — a standard withdrawal/deposit schedule's categorized items also count automatically toward category budget comparison, with no separate flag

Standard transactions may contain multiple transaction items for split categorization. Investment transactions use a dedicated detail model centered on quantity, price, commission, tax, and income fields.

## Inputs

- Transaction type: standard or investment
- Chosen transaction subtype
- Date or schedule configuration
- Account, payee, and/or investment references
- Amounts and optional comment
- Optional reconciliation state
- Optional transaction items with category, comment, and tags
- Optional AI draft association during finalization

## Outputs

- Saved historical transaction record
- Planned scheduled transaction template, which also feeds category budget-vs-actual comparison when it's a standard withdrawal/deposit with items
- Itemized category allocation for reporting
- Data used in balance summaries, forecasts, and investment history

## Domain Concepts Used

- Account
- Payee
- Investment
- Category
- Tag
- Transaction Item
- Transaction Schedule
- AI Document

These concepts should be referenced from their own documentation rather than re-explained here in full. A category-level spending/income target with no linked transaction is a separate, sibling concept — [Budget](../budget/budget.md) — not a mode of Transaction.

## Core Logic / Rules

- Transaction is an umbrella domain concept, not merely a CRUD object.
- Every transaction belongs to either the standard or investment family.
- The subtype determines which fields are meaningful and required.
- `schedule` is the only planning-mode flag on a transaction; there is no separate flag for budget participation.
- Standard deposit and withdrawal records can be split into multiple categorized items.
- Transfer is still a standard transaction, but behaves differently from payee-linked inflow or outflow.
- Reconciled status is intended for historical records and is not allowed for scheduled records.
- Planned transactions influence projections and summaries even when they are not yet historical facts.

## User Flow

1. The user decides to record or plan a financial event.
2. They choose whether the transaction is standard or investment-oriented.
3. They select the subtype and provide the relevant fields.
4. If needed, they add split items or scheduling information.
5. YAFFA saves the transaction as a historical record or a scheduled planning template, depending on the chosen mode.
6. The transaction then contributes to balances, forecasting, and analysis.

## Documentation Map

This concept is documented through the following supporting files in this folder:

- transaction-types.md — classification and subtype meaning
- standard-transactions.md — everyday cashflow transactions
- investment-transactions.md — investment-specific transactions
- transaction-items.md — split lines and categorization behavior
- schedules.md — historical vs scheduled transaction modes

The standalone category-level target concept, [Budget](../budget/budget.md), lives outside this folder as a sibling asset alongside category and account, since it has no linked transaction.

## Edge Cases / Constraints

- Not every investment subtype uses the same combination of amount, price, and quantity.
- Category budget comparison only applies to standard withdrawal/deposit transactions with items — transfers have no items, and investment transactions have no category-based items.
- Scheduled transactions can generate future instances without immediately becoming historical facts.
- Some transactions originate as AI drafts and become final only after user review.

## Dependencies

- Models:
  - Transaction
  - TransactionItem
  - TransactionSchedule
  - TransactionDetailStandard
  - TransactionDetailInvestment
- Services:
  - TransactionService
  - TransactionItemMergeService
  - monthly summary and schedule-processing jobs
- External systems:
  - optional AI document processing

## Frontend Interaction

- Users interact with transaction forms that adapt to the chosen subtype.
- Standard forms expose split items and optional scheduling controls.
- Investment forms expose quantity, price, tax, and commission fields.
- Schedule controls appear when the transaction is planned rather than purely historical.

## Domain Concepts

- Transaction:
  the central record of a financial event or planned financial event.
- Historical transaction:
  a concrete event that already occurred and is stored with an actual date.
- Scheduled transaction:
  a recurring template that can produce future instances over time, and — for a standard withdrawal/deposit with items — automatically counts toward category budget comparison.
- Transaction item:
  a sub-line within a standard transaction used for category-level allocation.

## Confidence Level

High

## Assumptions

- Accounts, payees, and investments already have their own documentation.
- This specification is intentionally concept-first and avoids low-level implementation detail unless it changes user-visible behavior.
- The code is the source of truth where terminology and intent differ from older documentation.

## Frontend Interaction

- Users create and edit transactions through transaction forms that adapt to the selected type.
- Standard transactions may expose split items for granular categorization.
- Scheduled transactions appear as planning-oriented forms rather than simple one-time records.
