# Dashboard

## Feature Name

Dashboard and Daily Financial Overview

## Feature Summary

The YAFFA Dashboard is the quick-glance home screen of the application. It gives users an immediate overview of financial position, recent category impact, upcoming scheduled activity, setup readiness, and small data-quality improvements without requiring them to enter the deeper Reports area.

This is where the user's ongoing effort of recording accounts, transactions, categories, schedules, and investments starts to feel immediately useful on a day-to-day basis. The Dashboard does not replace Reports; it provides the fast operational view that helps users decide where to look next.

## Target User

- Primary:
  Users performing daily or weekly check-ins who want a fast understanding of their current situation.

- Secondary:
  New users still setting up their finance structure
  Advanced users who want a quick starting point before deeper analysis.

## User Problem

- Users need a concise overview before deciding whether deeper investigation is necessary.
- New users need guidance on what is required for YAFFA to become useful.
- Ongoing users need to notice balance changes, upcoming scheduled events, and data-quality improvements quickly.

## User Value / Benefit

### Functional Benefits

- Shows a grouped snapshot of total account value.
- Highlights which categories had the strongest monthly effect.
- Surfaces upcoming scheduled transaction instances on a calendar.
- Suggests a likely default category for a payee based on actual usage patterns.
- Guides new users through essential first setup steps.

### Conceptual Benefits

- Reinforces the feeling that recorded data has practical payoff.
- Helps users keep a continuous relationship with their finances instead of only reviewing them occasionally.
- Creates a bridge between quick awareness and deeper reporting.

## When to Use the Dashboard

Use the Dashboard when the user wants a quick answer to How am I doing right now?

It is especially useful when the user wants to:

- get a daily or weekly financial pulse,
- notice whether something needs attention,
- see whether upcoming schedules may require action,
- decide whether to open a deeper report for more analysis,
- confirm that the application is set up enough to provide meaningful insight.

## Business Questions Answered

- What is the rough total value of my active accounts right now?
- Which categories had the biggest effect this month?
- What scheduled items are coming up soon?
- Is there a payee rule I should formalize to improve future data quality?
- Am I fully set up to benefit from YAFFA's reporting features?

## Dashboard Contents

The current dashboard contains five main content blocks:

- Welcome and onboarding checklist
- Total account value grouped by account group
- Monthly category waterfall summary
- Calendar of scheduled transaction instances
- Payee category recommendation widget

## Technical Description

- The dashboard is the authenticated home route of the application.
- It is implemented as a Vue-based page composed of multiple independent widgets.
- Some widgets use precomputed monthly summary data or existing report endpoints rather than recalculating everything from raw transactions on every page load.
- This design keeps the home screen responsive while still reflecting meaningful financial information.

## Inputs

- Accounts, account groups, and currencies
- Transactions and categories
- Scheduled transactions and next scheduled dates
- Payee-category usage patterns
- Onboarding completion state
- User locale and base currency settings

## Outputs

- Visual summary widgets
- Grouped account totals
- Category impact chart for the selected month
- Upcoming schedule calendar entries
- Smart recommendation prompts
- Setup checklist progress

## Core Logic / Rules

- The dashboard is optimized for quick overview rather than exhaustive detail.
- It combines operational widgets and lightweight analytics in one place.
- Some widgets may temporarily show loading or unavailable states while summary calculations are still running.
- The dashboard often serves as the first step before the user opens a more detailed report or editing workflow.

## Relationship to Reports

- Dashboard: quick signals, orientation, and next actions
- Reports: deeper interpretation, comparison, and planning

The dashboard should therefore be documented separately, while clearly referencing the richer analytical features documented under Reports.

## Dependencies

- Models:
  Accounts, account groups, transactions, schedules, payees, categories, user onboarding state

- Services:
  Account summary calculations, payee category statistics, shared chart and table helpers, onboarding support

- External systems:
  Vue widgets, calendar component, charting tools, localization helpers

## Confidence Level

High

## Assumptions

- The dashboard is intended as a living home screen rather than a static welcome page.
- Some widgets may evolve independently over time while still remaining conceptually part of the dashboard.
