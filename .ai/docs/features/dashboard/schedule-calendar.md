# Schedule Calendar Widget

## Feature Name

Upcoming Scheduled Transactions Calendar

## Feature Summary

This widget places scheduled transaction instances on a calendar so users can quickly see what is coming up next. It is a future-awareness tool designed to reduce surprises and keep recurring financial commitments visible.

## Target User

- Primary:
  Users relying on recurring transactions who need quick awareness of near-future obligations.

- Secondary:
  Users checking whether their scheduled plans still make sense before they trigger.

## User Problem

- Scheduled commitments are easy to forget when they are hidden inside forms or lists.
- Users need a visual way to see near-future financial activity without running a full report.
- Provides a quick link to enter scheduled instances when they are top of mind.

## User Value / Benefit

### Functional Benefits

- Displays upcoming scheduled instances in calendar form.
- Lets users click through directly to the enter-instance workflow.
- Makes upcoming planned activity visible in a time-based layout.

### Conceptual Benefits

- Keeps future commitments mentally present.
- Supports forecasting awareness by showing what is already expected to happen soon.

## When to Use This Widget

Use this widget when the user wants to know What is scheduled soon?

## Business Questions Answered

- Which scheduled transactions are approaching?
- Do I have several recurring obligations clustered in the same period?
- Should I review or enter an upcoming schedule instance now?

## Technical Description

- The widget loads scheduled transaction items from the existing transaction API.
- It maps next scheduled dates into a calendar component.
- Each date cell can contain one or more transaction-type icons that link to the related entry action.
- The visible date range is automatically adjusted to the relevant upcoming period.

## Inputs

- Scheduled transaction items with next-date values
- Transaction type and account context data
- User locale and language settings

## Outputs

- Calendar with scheduled-instance markers
- Tooltip-style labels for transaction details
- Direct navigation into the schedule entry workflow

## Core Logic / Rules

- Only items with a valid next scheduled date are shown.
- The calendar focuses on schedule instances rather than the full transaction history.
- The widget is a visibility tool, not a full schedule-management interface.

## Confidence Level

High

## Assumptions

- The widget is mainly oriented toward awareness and follow-up rather than detailed schedule editing.
