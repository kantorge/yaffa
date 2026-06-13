# Schedule Calendar Widget

## Feature Name

Upcoming Scheduled Transactions Calendar

## Feature Summary

This widget places scheduled transaction instances on a calendar so users can quickly see what is coming up next. It is a future-awareness tool designed to reduce surprises and keep recurring financial commitments visible, while also allowing users to act on a scheduled instance directly from the calendar.

## Target User

- Primary:
  Users relying on recurring transactions who need quick awareness of near-future obligations.

- Secondary:
  Users checking whether their scheduled plans still make sense before they trigger.

## User Problem

- Scheduled commitments are easy to forget when they are hidden inside forms or lists.
- Users need a visual way to see near-future financial activity without running a full report.
- Users need a quick way to either enter or skip a scheduled instance when it is top of mind.

## User Value / Benefit

### Functional Benefits

- Displays upcoming scheduled instances in calendar form.
- Lets users open the enter-instance workflow directly from the calendar.
- Lets users skip the current scheduled instance without leaving the calendar.
- Makes upcoming planned activity visible in a time-based layout.
- Keeps the user on the same month when the widget refreshes after an action.

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
- Each date cell can contain one or more transaction-type icons that open an interactive popover.
- The popover lets the user skip the current instance or enter it through the existing transaction modal flow.
- The visible month is preserved when the widget refreshes after user actions.

## Inputs

- Scheduled transaction items with next-date values
- Transaction type and account context data
- User locale and language settings

## Outputs

- Calendar with scheduled-instance markers
- Interactive popover with transaction details and action buttons
- Skip action result reflected back into the calendar data
- Direct entry into the schedule workflow through the modal-based form

## Core Logic / Rules

- Only items with a valid next scheduled date are shown.
- The calendar focuses on schedule instances rather than the full transaction history.
- The widget is a visibility tool, not a full schedule-management interface.
- The popover can stay open long enough for interaction and closes when the calendar moves or the widget is dismissed.
- After skipping or saving an instance, the widget refreshes its schedule data and keeps the visible month stable when possible.

## Confidence Level

High

## Assumptions

- The widget is mainly oriented toward awareness and follow-up rather than detailed schedule editing, even though it now supports two lightweight actions on each upcoming instance.
