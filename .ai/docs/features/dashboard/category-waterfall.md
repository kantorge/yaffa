# Category Waterfall Widget

## Feature Name

Monthly Category Impact Overview

## Feature Summary

This widget shows which top-level categories had the biggest positive or negative effect during a selected month. It gives users a fast explanation of what drove the current monthly result without opening a full report.

## Target User

- Primary:
  Users checking why this month looks better or worse than expected.

- Secondary:
  Users who want a quick category-level explanation before opening the deeper reporting area.

## User Problem

- Users often feel that a month was unusually good or bad, but cannot immediately tell which categories caused it.
- A simple balance figure alone does not explain the financial story behind the month.

## User Value / Benefit

### Functional Benefits

- Summarizes category contribution for a specific month.
- Lets users move backward and forward across months.
- Supports all transactions, only standard transactions, or only investment transactions.
- Ends with an overall result bar to make the total monthly effect easy to understand.

### Conceptual Benefits

- Turns raw transaction history into a quick narrative of what drove the result.
- Gives an immediate explanation that can guide deeper reporting follow-up.

## When to Use This Widget

Use this widget when the user wants to answer What moved the month the most?

## Business Questions Answered

- Which categories had the biggest financial effect this month?
- Was the monthly result driven by normal spending or by investments?
- Is the current month unusual compared with the previous one?

## Technical Description

- The widget reuses the waterfall reporting endpoint from the broader Reports feature.
- It loads category contribution data for the selected year and month and renders it as a waterfall-style chart.
- Values are sorted and color-coded to distinguish positive and negative impact.
- The same endpoint can represent standard, investment, or combined transaction data.

## Inputs

- Selected month and year
- Transaction type scope: all, standard, or investment
- Category-aggregated waterfall data

## Outputs

- Monthly waterfall chart
- Category contribution ordering
- Final result bar
- No-data state when the selected month has nothing relevant to show

## Core Logic / Rules

- Categories are shown as impact contributors rather than as a full ledger.
- Positive values are visually separated from negative ones.
- Navigation across months changes the reporting window without leaving the dashboard.
- This widget is intentionally simplified compared with the richer report views and is best used as a directional summary.

## Confidence Level

High

## Assumptions

- The widget focuses on top-level categories for readability and quick interpretation rather than on exhaustive category drill-down.
