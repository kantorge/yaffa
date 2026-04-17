# Account Balance Widget

## Feature Name

Total Account Value Overview

## Feature Summary

This widget gives the user a grouped overview of total value across their accounts. It is designed to answer the immediate question of how much value is currently represented in the user's financial structure, while still allowing a quick drill-down into account groups and individual accounts.

## Target User

- Primary:
  Users checking their overall financial position during a daily or weekly review.

- Secondary:
  Users investigating whether one account group is carrying most of the value or stress.

## User Problem

- Users need a fast overview of financial value without opening every account separately.
- Multi-account and multi-currency setups are harder to reason about without a common base-currency summary.

## User Value / Benefit

### Functional Benefits

- Shows a total value headline at a glance.
- Groups accounts by account group for easier mental organization.
- Allows users to include or hide closed accounts.
- Links directly to the underlying account detail pages.

### Conceptual Benefits

- Gives the user a stable sense of overall financial position.
- Makes the account structure feel coherent rather than fragmented.

## When to Use This Widget

Use this widget when the user wants to quickly check net available value distribution across accounts.

## Business Questions Answered

- What is the rough total value of my active accounts?
- Which account groups carry most of my money?
- Are closed accounts affecting the picture I want to see?

## Technical Description

- The widget loads account balance data from the account balance API.
- It uses precomputed monthly summary values for fact data rather than recalculating all balances directly from raw transactions on each dashboard load.
- Account values are converted to the user's base currency when needed.
- If relevant background summary jobs are still running, the widget can temporarily show that data is not yet available and retry later.

## Inputs

- Account monthly summary data
- Account groups and currencies
- User base currency and locale settings
- Active or closed account state

## Outputs

- Total value display
- Grouped account totals
- Per-account values with optional foreign-currency display
- Loading, unavailable, or error states

## Core Logic / Rules

- Only accounts are summarized in this widget.
- Closed accounts can be hidden or included depending on the user's current toggle choice.
- Group totals and grand totals are calculated from the summarized account data.
- Non-base-currency accounts are converted for comparability while still preserving the original amount display where possible.

## Confidence Level

High

## Assumptions

- The widget presents a practical operational total rather than a full accounting-grade net-worth model.
