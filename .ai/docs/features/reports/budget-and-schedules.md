# Budget and Schedules

## Feature Name

Scheduled and Budgeted Items List

## Feature Summary

This report is a simpler, maintenance-oriented side of financial planning in YAFFA. It gives users a structured list of recurring and budgeted transactions so they can review what is active, what is coming next, and which planned items need correction.

Unlike the more analytical category-based review pages, this screen is primarily operational. It helps users keep their recurring setup healthy so later budget comparisons and forecasting-style reports remain trustworthy.

## Target User

- Primary:
  Users who already rely on recurring transactions or budgets and need a quick oversight view for maintenance.

- Secondary:
  Users auditing outdated schedules, skipped items, or plans that no longer reflect current reality.

## User Problem

- Recurring plans become hard to manage when they are spread across many transactions.
- Users need to see which scheduled items are active, overdue, upcoming, or budget-related.
- Users need fast access to corrective actions without hunting through the whole transaction history.
- Users need one place to verify that the future-facing setup behind forecasts is still realistic.

## User Value / Benefit

### Functional Benefits

- Lists scheduled and budgeted transactions in one review-focused place.
- Highlights items whose next occurrence is overdue or imminent.
- Lets users filter by schedule status, budget flag, active state, transaction type, and free-text search.
- Provides direct actions to edit, clone, replace, delete, enter, or skip scheduled instances.

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

- The page is table-driven and loads scheduled items from the existing transaction API.
- Each row represents a recurring or budgeted transaction and exposes status information such as schedule rule, start date, next date, active flag, and type.
- Human-readable schedule text helps users understand the recurrence pattern without reading the raw rule.
- Contextual actions reuse the application's standard transaction workflows rather than inventing report-specific editing logic.

## Inputs

- Existing scheduled transactions
- Existing budgeted transactions
- Schedule, budget, active, and transaction-type filters
- External search text

## Outputs

- Filtered maintenance list of recurring items
- Row-level warning states for next occurrence timing
- Contextual actions for corrective maintenance

## Core Logic / Rules

- The screen focuses on items with scheduling or budget relevance rather than the full transaction history.
- Overdue next dates are visually emphasized, and near-term next dates are also highlighted.
- Some actions, such as entering or skipping an instance, only make sense when a schedule is active.
- Edit, clone, replace, and delete actions are launched from this report but handled by the existing transaction flows.
- The quality of forecasting and category-based budget review depends heavily on this underlying schedule and budget maintenance being kept accurate.

## User Flow

1. User opens the Schedules and Budgets report.
2. User filters the list to the relevant subset of recurring items.
3. YAFFA displays schedule details, next dates, and status indicators.
4. User identifies outdated, overdue, or incorrect recurring entries.
5. User performs maintenance actions such as edit, skip, or replace.

## Edge Cases / Constraints

- Human-readable schedule text exists, but translation and wording may not yet be fully polished.
- The usefulness of the list depends on recurring items being modeled consistently.
- This page is intentionally simpler than the category-based budget history review and should not be treated as the main analytical budgeting feature.

## Dependencies

- Models:
  Scheduled and budgeted transactions together with their schedule-related data

- Services and helpers:
  Shared transaction formatting helpers, table-filter helpers, contextual action helpers, and onboarding support

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

- YAFFA continues to model budgets through recurring transaction infrastructure rather than through a fully separate budgeting subsystem.
