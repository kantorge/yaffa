# Transaction Items

## Feature Summary

Transaction items allow one standard transaction to be split into multiple meaningful lines. Instead of forcing the user to assign an entire withdrawal or deposit to a single category, YAFFA lets the user break it down into parts that better reflect reality.

This is a key part of YAFFA’s philosophy of conscious and precise financial tracking. It is also fundamental for budgeting, reporting and forecasting.

## Target User

- Primary:
  Users who want accurate categorization of mixed purchases or income components.

- Secondary:
  Users reviewing reports and trying to understand where money actually went.

## User Problem

- Real-world transactions often cover more than one category.
- A single payment may include groceries, household goods, and a pharmacy purchase at once.
- A single income event may also contain multiple conceptual parts.
- However, such detailed categorization is often too time-consuming and error-prone.

## User Value / Benefit

- Improves report accuracy without forcing the user to create multiple separate transactions.
- Reduces the tradeoff between quick entry and detailed categorization.
- Makes spending analysis more truthful and useful over time.

### Functional Benefits

- Supports split transactions inside one parent record.
- Allows category-level comments and tags.
- Supports AI-assisted categorization suggestions during draft review.
- Speeds up entry through payee defaults, remaining-amount shortcuts, and history-based suggestions.

### Conceptual Benefits

- Reflects how real-life purchases and receipts actually work.
- Helps users build a clearer mental model of why a single payment may belong to multiple categories.

## Technical Description

A transaction item belongs to one parent transaction and stores a categorized portion of the amount. Each item can carry:

- amount
- category
- optional comment
- optional tags

The items are important and recommended for standard withdrawal and deposit transactions, where category-based reporting is central.
Items are not available for transfer transactions, which are meant to represent internal movement rather than category-based cashflow.
Items are not available for investment transactions, which use a different model focused on quantity and price rather than category.

YAFFA also includes a set of quick-entry helpers around transaction items so that detailed categorization does not become slow or frustrating.

### Quick-entry helpers

#### Payee default category auto-allocation

If the selected payee has a default category, YAFFA can automatically assign the remaining unallocated amount to that category. This is especially useful for broad fallback categories such as other groceries or eating out, where the user only wants to enter the exceptional items manually and let the rest be captured automatically.

#### Assign remaining amount shortcut

The UI exposes a shortcut for assigning the still-unallocated remainder to an item. This reduces manual calculation and helps the user close the transaction quickly without leaving small gaps in allocation.

#### Load from history and split by past share

YAFFA can reuse payee history to build a suggested split automatically. Instead of requiring the user to remember every category line, the interface can load historically frequent categories and distribute the current total amount proportionally according to past usage patterns.

This is particularly useful when:

- speed matters more than perfect precision
- the receipt is unavailable
- the user wants a reasonable starting point and plans to adjust only if needed

#### Frequent category suggestions

Frequently used categories for the selected payee are suggested for quick reuse. These suggestions make it easier to stay consistent over time while still allowing manual overrides.

## Inputs

- item amount
- category selection
- optional comment
- optional tags
- optional AI-provided description or recommendation during review flow, if the transaction was created as an AI draft and the user is in the process of reviewing it

## Outputs

- detailed category allocation inside a single transaction
- better reporting and filtering
- more accurate tag-based and category-based analysis

## Core Logic / Rules

- Transaction items are attached to a parent transaction, not stored independently from the financial event.
- Each item must have a positive amount and a category.
- Items are primarily part of standard cashflow behavior rather than investment behavior.
- The sum of the items represents how the transaction amount is allocated conceptually.
- If a payee has a default category, unassigned remaining amount can be automatically captured under that fallback category.
- The user can explicitly assign the remaining unallocated amount to an item through a UI shortcut.
- Historical category usage for a payee can be reused to propose a split automatically, including proportional allocation based on past share.
- Frequently used categories are suggested as accelerators, not as mandatory rules.
- Duplicate item lines with the same category and empty comment may be merged automatically when the relevant user setting is enabled.
- AI draft processing may suggest category choices, but the finalized saved transaction depends on user confirmation.

## User Flow

1. User creates or edits a standard transaction.
2. Instead of treating the whole amount as one category, the user adds one or more transaction items.
3. YAFFA can speed up the process by suggesting frequent categories, loading a historical split, or auto-assigning the leftover amount to the payee’s default category.
4. The user adjusts only the exceptions or fine details if needed.
5. YAFFA uses the combined item list for analysis, budgeting, reporting, and forecasting.

## Edge Cases / Constraints

- Overly simplified single-line categorization may hide useful financial insight.
- AI recommendations can assist item preparation, but should not replace user review.

## Dependencies

- Models:
  - TransactionItem
  - Transaction
- Related concepts:
  - Category
  - Tag
  - AI Document

## Frontend Interaction

- Standard transaction forms expose a section for multiple item lines.
- The UI can suggest frequently used categories for the selected payee.
- The UI can load a history-based split and distribute the total amount based on past category share.
- The UI provides a shortcut to assign the remaining unallocated amount quickly.
- Saved transaction views show the category-level breakdown of the transaction.
- AI-assisted drafts can pre-populate suggested items to reduce manual entry effort.

## Domain Concepts

- Transaction item:
  a categorized sub-line of a parent standard transaction.
- Split transaction:
  a transaction whose financial meaning is expressed through multiple items rather than one flat category.
- Default category fallback:
  a payee-linked category used to automatically capture remaining unassigned amount.
- History-based split suggestion:
  a quick-entry proposal that reuses past category patterns and proportions for the same payee.

## Confidence Level

High

## Assumptions

- Transaction items are a product-level feature, not just a database detail, because they directly shape how users interpret and analyze their finances.
