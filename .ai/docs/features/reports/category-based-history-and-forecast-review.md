# Category-Based History and Forecast Review

## Feature Name

Category-Based Budget History and Forward Review

## Feature Summary

This is an analytical and strategically important budgeting report in YAFFA. It lets users select categories and account scope, then compare actual historical behavior against planned budget values over time.

This is also one of the strongest examples of where detailed past recording and future planning finally become valuable. Real transactions provide the historical evidence, while schedules and budgets supply the forward-looking structure that makes the chart useful for decision-making.

The view is forward-looking without being a pure prediction engine. Its forecast character comes from recurring budget definitions expanded into future-relevant periods and from trend-oriented summaries such as moving averages that help users interpret likely direction.

## Target User

- Primary:
  Users who regularly review spending behavior by category and want to see whether their financial plan is holding up over time.

- Secondary:
  Users doing monthly or quarterly planning who need a more meaningful view than a raw transaction list.

## User Problem

- Users need more than a list of budget items; they need to know whether actual results follow the intended plan.
- Category-level spending patterns are hard to understand without aggregation across time.
- Users need a practical way to review both historical behavior and near-future expected budget pressure.
- Users need a report that turns disciplined categorization and schedule maintenance into clear planning insight.

## User Value / Benefit

### Functional Benefits

- Compares actual and budgeted values over time for selected categories.
- Lets users narrow the analysis by account scope and account entity.
- Supports month, quarter, and year views depending on the level of detail needed.
- Uses a moving-average trend to make longer-term direction easier to understand.
- Links the chart to underlying scheduled and budgeted items for deeper inspection.

### Conceptual Benefits

- Connects budgeting to real behavior rather than treating it as a static list of planned amounts.
- Helps users see whether a category is stabilizing, drifting, or repeatedly overshooting expectations.
- Supports better long-term planning by turning recurring definitions into interpretable financial signals.
- Makes the user's careful historical categorization effort pay off during planning and review.

## When to Use This Report

Use this report when the user wants to evaluate whether category-level reality still fits the plan.

It is especially useful when the user wants to:

- review monthly or quarterly spending against expectations,
- check whether recurring commitments are likely to put pressure on future months,
- understand which categories are steadily drifting upward,
- validate whether the forecast-like picture created by schedules and budgets still feels realistic.

## Business Questions Answered

- Which categories are most out of line with the plan?
- Is the current spending direction sustainable?
- Are recurring budget assumptions still realistic for upcoming months?
- Where should I intervene first if I want to improve monthly saving or free cash?
- Does the trend suggest a temporary spike or a lasting habit change?

## Technical Description

- The report starts from category selection and automatically includes child categories so parent-level review remains meaningful.
- Actual values are calculated from recorded standard transactions and grouped by month.
- Planned budget values are derived from recurring budget transactions expanded into schedule instances up to the user's planning horizon.
- Both actual and planned values are normalized to the user's base currency when needed.
- The frontend can re-aggregate the loaded monthly data into quarterly or yearly views without rethinking the whole feature.
- The use of monthly aggregated data and schedule expansion is a deliberate performance decision; recalculating every view directly from all transactions and all future schedule instances on the fly would be more cumbersome and slower for the user.

## Inputs

- Selected categories and child categories
- Account scope mode and optional account entity
- Recorded standard transactions
- Budgeted recurring transactions and their schedules
- Currency rates and base-currency settings
- User planning horizon or end date

## Outputs

- Actual-versus-budget time series by period
- Moving-average trend line
- Month, quarter, or year aggregation views
- Supporting list of scheduled and budgeted transactions for the selected scope

## Core Logic / Rules

- Actual history uses standard recorded transactions that are not schedule entries and not budget-only entries.
- Budget values are generated from recurring budget definitions rather than entered manually for every month.
- Withdrawal values are treated as negative and deposit values as positive so the comparison preserves direction.
- Account scope can include all accounts, a selected account, or generic no-account cases for broader plan review.
- Missing periods are filled so the chart can present a continuous time axis.
- The moving average is meant for interpretation and trend review, not exact accounting calculation.
- The usefulness of the forward-looking view depends directly on schedules and budgets being kept accurate in the simpler maintenance report.

## User Flow

1. User opens the category-based budget review.
2. User selects the categories they want to analyze.
3. User optionally narrows the review to a specific account scope.
4. YAFFA loads actual and budgeted values and renders the history chart.
5. User switches between monthly, quarterly, and yearly views depending on the planning question.
6. If needed, the user checks the supporting schedule table for the recurring items driving the budget side.

## Edge Cases / Constraints

- This is a forward-looking review tool, but not a true predictive forecasting engine.
- The quality of insight depends on clean category usage and maintained recurring definitions.
- Broad category selections may make the story less specific, while narrow selections may produce sparse charts.
- Multi-currency comparison is practical and user-oriented, but not intended as formal accounting precision.
- This chart is not meant to track meeting budget targets within a specific month, but rather to review overall direction and consistency with the plan.

## Dependencies

- Models:
  Transactions, transaction items, standard transaction details, and recurring budget schedule data

- Services and helpers:
  CategoryService, schedule-instance generation, currency conversion helpers, and chart helpers

- Frontend components:
  Category tree, account selection controls, time-interval toggles, zoom controls, and chart rendering

## Frontend Interaction

- Users primarily interact through category selection, account filtering, and time-granularity toggles.
- The chart is exploratory and supports zooming for closer review.
- The view blends historical evidence with plan-driven forward context, making it the more strategic budgeting report in the Reports area.

## Documentation Boundary

This document focuses on category-based analysis and forward review. The simpler maintenance list of scheduled and budgeted items is documented separately.

## Confidence Level

High

## Assumptions

- The forward-looking aspect is driven by recurring budget schedules and trend interpretation, not by an independent forecasting model.
- The related schedule table is treated as supporting context rather than the primary value of this report.
