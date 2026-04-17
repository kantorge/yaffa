# Cash Flow and Forecasting

## Feature Name

Cash Flow Trend Review

## Feature Summary

This report helps users understand how money moves over time. It presents monthly balance change, cumulative running total, and investment value in a time-based chart, with an optional forecast-aware mode when summary data beyond pure fact values is available.

This is one of the clearest places where both past recording and future planning gain meaning. Historical transactions show how money really moved, while schedules, budgets, and summary calculations help the user judge whether their future pattern is sustainable.

## Target User

- Primary:
  Users reviewing month-to-month financial progress and checking whether current cash movement supports their goals.

- Secondary:
  Users investigating a specific account's trend or validating whether forecasted balances look reasonable.

## User Problem

- Users can see individual transactions, but that does not always reveal the overall direction of their finances.
- Users need a quick way to see whether the month was net positive or negative and how that affects long-term balance.
- Users need to view cashflow in one currency even when the underlying accounts use multiple currencies.
- Users need a way to check whether planned finances are starting to diverge from available funds.

## User Value / Benefit

### Functional Benefits

- Makes monthly gains and losses immediately visible.
- Shows the cumulative running total, which is often easier to interpret than isolated monthly changes.
- Lets users scope the view to a specific account entity or inspect the broader overall trend.
- Supports forecast-aware analysis when the user wants to include non-fact summary data.

### Conceptual Benefits

- Encourages users to think in terms of trend and trajectory, not only individual spending events.
- Makes long-term financial direction easier to understand during periodic review.
- Helps users decide whether free cash should remain liquid, be saved, or be invested.

## When to Use This Report

Use this report when the user wants to understand whether their cash position is healthy enough for the coming period.

It is especially relevant when the user wants to:

- check whether planned finances do not match currently available funds,
- see if accumulated free cash is becoming large enough to save or invest,
- decide whether their monthly saving pace is realistic,
- understand whether one account is becoming stressed or underused.

## Business Questions Answered

- Am I consistently building surplus each month, or only occasionally?
- Do my planned commitments fit my current cash position?
- Is my accumulated free cash worth moving into savings or investments?
- Does the current trend suggest that my monthly saving target is realistic?
- Which account view should I inspect more closely?

## Technical Description

- The report reads from monthly account summary data rather than raw transactions one by one.
- It aggregates by month and transaction type, then converts values to the user's base currency when needed.
- The frontend renders a mixed chart with monthly balance change bars plus line series for running total and investment value.
- A toggle allows the user to keep chart series on one axis or split them across axes for readability.
- The forecast-oriented behavior depends on precomputed summary values because recalculating every month directly from all historical transactions and all relevant future schedule instances on demand would be unnecessarily expensive.

## Inputs

- Precomputed monthly account summary data
- Optional account entity filter
- Optional forecast inclusion flag
- Base currency and localization settings

## Outputs

- Monthly balance-change bars
- Running total line
- Investment value line
- Busy-state message when summary calculations are still in progress

## Core Logic / Rules

- If the underlying monthly summary jobs are still running, the endpoint returns a non-error busy message instead of incomplete chart data.
- When forecast is disabled, only fact data is included.
- When forecast is enabled, additional non-fact summary rows may be included if present in the summary table.
- Values are grouped by month, transaction type, and effective currency.
- Non-base-currency values are converted using the latest available monthly rate for meaningful comparison.
- Forecast usefulness depends on schedules and budgets being maintained properly elsewhere in the system.

## User Flow

1. User opens Cash Flow from the Reports menu.
2. User optionally selects a specific account.
3. User optionally enables the forecast toggle.
4. User reloads the chart data.
5. YAFFA displays monthly balance change, cumulative total, and investment-value trend lines.
6. User reviews whether cash movement appears healthy, volatile, or inconsistent with expectations.

## Edge Cases / Constraints

- Forecast quality depends on the availability and freshness of monthly summary calculations.
- Users may briefly see a waiting state while background calculations complete.
- This view is best for trend analysis, not for explaining every individual transaction.
- Multi-currency comparison depends on available rate data and is therefore interpretive rather than exact accounting precision.

## Dependencies

- Models and tables:
  Account monthly summaries, accounts, and related account entities

- Services and helpers:
  Currency conversion helpers and chart localization helpers

- Frontend components:
  Account select widget, forecast toggle, axis toggle, and amCharts-based visualization

## Frontend Interaction

- Users can reload data on demand.
- The selected account and forecast state are reflected in the page URL.
- The chart highlights the current month to orient the user in the timeline.

## Documentation Boundary

This page should cover analytical cashflow and forecast behavior. Detailed account definitions and transaction entry mechanics belong in their dedicated domain documentation.

## Confidence Level

High

## Assumptions

- The monthly summary generation process is documented elsewhere or inferred from background job naming and usage.
- The report treats forecast as a summary-data mode rather than a separate forecasting engine specification.
