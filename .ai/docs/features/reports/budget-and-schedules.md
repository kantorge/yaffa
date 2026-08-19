# Budget and Schedules

## Feature Name

Scheduled and Budgeted Items List

## Feature Summary

This report is a simpler, maintenance-oriented side of financial planning in YAFFA. It gives users a single, merged list of recurring scheduled transactions and standalone [Budget](../../assets/budget/budget.md) rows so they can review what is active, what is coming next, and which planned items need correction. There is no separate Budget page — this report is the only place standalone Budgets are created, edited, and deleted.

Unlike the more analytical category-based review pages, this screen is primarily operational. It helps users keep their recurring setup healthy so later budget comparisons and forecasting-style reports remain trustworthy.

## Target User

- Primary:
  Users who already rely on recurring transactions or budgets and need a quick oversight view for maintenance.

- Secondary:
  Users auditing outdated schedules, skipped items, or plans that no longer reflect current reality.

## User Problem

- Recurring plans become hard to manage when they are spread across many transactions.
- Users need to see which scheduled items and standalone Budgets are active, overdue, or upcoming, in one place.
- Users need fast access to corrective actions without hunting through the whole transaction history.
- Users need one place to verify that the future-facing setup behind forecasts and budget comparisons is still realistic.

## User Value / Benefit

### Functional Benefits

- Lists scheduled transactions and standalone Budgets in one review-focused place, merged into a single table.
- Highlights items whose next occurrence is overdue or imminent.
- Lets users filter by row type (Schedule/Budget), active state, transaction type, and free-text search.
- Provides direct actions to edit, clone, replace, delete, enter, or skip scheduled instances, and to edit or delete Budget rows.

### Conceptual Benefits

- Gives users confidence that their planned financial structure is still accurate.
- Reduces the risk that future-oriented reporting is built on stale or forgotten recurring items.
- Gives concrete value to future planning effort by making it inspectable and maintainable.

## When to Use This Report

Use this report when the user wants to maintain the future model rather than deeply analyze the trend.

It is especially useful when the user wants to:

- review all recurring or budgeted items in one place,
- fix schedules that may be causing unrealistic future expectations,
- check what is overdue, active, or about to trigger next,
- confirm that the planning data behind forecasts and budget comparisons is still correct.

## Business Questions Answered

- Which recurring plans are still active and which need attention?
- Are outdated schedules making my future-facing reports unreliable?
- What planned items are about to occur next?
- Which budget or schedule entries should I update, skip, or replace?

## Technical Description

- The page is table-driven and merges two row sources behind one listing: `Transaction` rows with an active schedule, and standalone `Budget` rows.
- Each row exposes status information such as schedule/period rule, start date, next date (Budget rows have none), active flag, and type.
- Columns that don't apply to a Budget row — payee, next date, the enter/skip-instance actions — render blank/muted for that row rather than being conditionally hidden, the same convention already used elsewhere in the table for an empty category cell.
- Human-readable schedule text helps users understand the recurrence pattern without reading the raw rule, for both schedule and Budget rows.
- Contextual actions branch by row type: a schedule row keeps its existing edit/clone/replace/enter/skip/delete workflow; a Budget row offers edit/delete only, through the dedicated Budget API.

## Inputs

- Existing scheduled transactions
- Existing standalone Budget rows
- Row-type (Schedule/Budget), active, and transaction-type filters
- External search text

## Outputs

- Filtered maintenance list of recurring items
- Row-level warning states for next occurrence timing
- Contextual actions for corrective maintenance

## Core Logic / Rules

- The screen focuses on items with scheduling or budget relevance rather than the full transaction history.
- Overdue next dates are visually emphasized, and near-term next dates are also highlighted.
- Some actions, such as entering or skipping an instance, only make sense for a schedule row and are active only when the schedule is active; a Budget row never has them.
- Schedule row actions (edit, clone, replace, enter, skip, delete) are launched from this report but handled by the existing transaction flows; Budget row actions (edit, delete) go through the dedicated Budget API.
- The quality of forecasting and category-based budget review depends heavily on this underlying schedule and Budget maintenance being kept accurate.

## User Flow

1. User opens the Schedules and Budgets report.
2. User filters the list to the relevant subset — by row type (Schedule/Budget), active state, or transaction type.
3. YAFFA displays schedule/Budget details, next dates (Budget rows blank), and status indicators.
4. User identifies outdated, overdue, or incorrect recurring entries, or creates a new standalone Budget via the "New Budget" action.
5. User performs maintenance actions such as edit, skip, replace (schedule rows), or edit/delete (Budget rows).

## Edge Cases / Constraints

- Human-readable schedule text exists, but translation and wording may not yet be fully polished.
- The usefulness of the list depends on recurring items being modeled consistently.
- A Budget row and a Transaction row are separate underlying records that can share the same numeric id — row lookups in the table must be row-type-aware, not id-only.
- This page is intentionally simpler than the category-based budget history review and should not be treated as the main analytical budgeting feature.

## Dependencies

- Models:
  `Transaction` (scheduled rows) and `Budget` (standalone rows), merged into one listing

- Services and helpers:
  `BudgetApiController`/`BudgetService` for Budget CRUD, shared transaction formatting helpers, table-filter helpers, contextual action helpers, and onboarding support

- Frontend components:
  Data table, filter sidebar, external search, and onboarding card

## Frontend Interaction

- The page centers on a filterable table rather than charts.
- Users interact through toggles, search input, and a contextual actions menu.
- The screen is optimized for quick oversight and maintenance, not deep analysis.

## Documentation Boundary

This document covers the simpler list-based oversight view only. The deeper category-based history and forward review is documented separately in the companion reporting document.

## Confidence Level

High

## Assumptions

- A standalone Budget is a first-class entity with its own table, separate from `Transaction` — see [Budget](../../assets/budget/budget.md) — but is deliberately surfaced in the same maintenance list as schedules rather than a dedicated page, since both are forward-looking planning inputs a user maintains together.
