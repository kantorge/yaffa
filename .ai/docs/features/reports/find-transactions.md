# Find Transactions

## Feature Name

Transaction History Exploration

## Feature Summary

Find Transactions is the most investigation-oriented report in YAFFA. It gives users an interactive workspace where they can filter existing transaction history and view the result through several analytical tabs instead of a single static table.

It is part of Reports because its purpose is interpretation, comparison, and review. It does not redefine how transactions are created or stored. It is also one of the clearest places where careful bookkeeping pays off, because every date, category, payee, and tag becomes searchable and explainable.

## Target User

- Primary:
  Users performing monthly review, trying to understand what happened across accounts, categories, or payees.

- Secondary:
  Advanced users debugging unexpected balances, validating imported data, or tracing specific financial events.

## User Problem

- Large transaction histories become difficult to review record by record.
- Users need to isolate patterns such as all spending in one category or all activity for a payee.
- Users need both a high-level summary and a fast way to inspect the raw matching records.

## User Value / Benefit

### Functional Benefits

- Narrows a large transaction history down to a relevant working set within seconds.
- Lets users switch between summary, list, timeline, category, and monthly views without leaving the same context.
- Supports drill-down from aggregated monthly data into the exact transactions behind that result.

### Conceptual Benefits

- Helps users move from raw data collection to genuine understanding.
- Strengthens the link between categorization habits and later analytical clarity.
- Turns detailed historical recording into practical investigative value.

## When to Use This Report

Use this report when the user needs to answer What exactly happened?

It is especially useful when the user wants to:

- investigate an unexpectedly high-spending month,
- find the transactions behind a category spike,
- trace activity related to a specific payee, tag, or account,
- confirm whether a suspected pattern is real or only a feeling.

## Business Questions Answered

- What transactions explain this balance change?
- Which payees or categories drove the result?
- When did this spending pattern start?
- Is the issue isolated or repeated across months?

## Technical Description

- The page is implemented as a Vue-based report workspace rather than a server-rendered static report.
- It queries the existing transaction search endpoint and then reuses shared transaction transformation helpers.
- Filter state is reflected in the URL, which helps preserve analysis context.
- The screen uses session-based caching for the same filter set to reduce unnecessary reloads.
- Tabs let the same filtered dataset drive multiple visualizations and list views.
- At the moment, full transactions are returned even if some filters such as categories or tags are applied at the level of transaction items.
- There are no hard limitations on the date range or other filters, but very broad queries may produce large result sets that are harder to interpret visually.

## Inputs

- Date from and date to filters
- Selected accounts
- Selected categories
- Selected payees
- Selected tags
- Optional tab or return context from the URL

## Outputs

- Summary statistics for the filtered transaction set
- Full list of matching transactions
- Timeline visualization
- Category-focused visual summaries
- Monthly breakdown with drill-down into the list view
- Transaction detail modal for record inspection

## Core Logic / Rules

- The same filtered result set powers all tabs so the user can compare views without changing the underlying query.
- Sidebar filters can be collapsed to give more space to the analytical output.
- Drill-down from monthly breakdown applies an in-memory narrowing of the current result set rather than forcing a whole new analytical context.
- URL parameters persist selected filters and the active tab.
- Only safe relative return paths are allowed when the page is opened with a return target.
- Session cache is tied to the active filter combination so stale data is less likely to appear for a different query.

## User Flow

1. User opens the Find Transactions report.
2. User selects a date range and optional filters such as category, payee, account, or tag.
3. User updates the report and YAFFA loads matching transactions.
4. User moves between tabs such as Summary, List of Transactions, Timeline Charts, Category Charts, and Monthly Breakdown.
5. If a monthly pattern needs investigation, the user drills down to the list view and inspects the underlying transactions.

## Edge Cases / Constraints

- The usefulness of the view depends on well-maintained dates, categories, and related transaction metadata.
- Very broad queries may produce large result sets that are harder to interpret visually.
- Cached session data improves responsiveness but only within the current browser session.
- This report helps explore history; it does not replace the separate transaction documentation or editing workflows.

## Dependencies

- Models:
  Transactions and their related accounts, categories, payees, and tags

- Services and shared helpers:
  Existing transaction search endpoint, transaction formatting helpers, reporting widgets, and toast or modal helpers

- Frontend components:
  Date range selector, select cards, tabbed reporting canvases, and transaction show modal

## Frontend Interaction

- Left sidebar hosts the filters and can be collapsed.
- The main panel is tab-driven and optimized for iterative analysis.
- The screen behaves more like a finance review dashboard than a plain search form.

## Documentation Boundary

This page should describe how YAFFA helps users explore and interpret transaction history. Detailed transaction lifecycle, validation, and data model behavior should remain in the existing transaction documentation.

## Confidence Level

High

## Assumptions

- The exact summary metrics depend on shared widgets that are not fully re-documented here.
- This document focuses on the user-facing analytical behavior that is clearly visible from the report implementation.
