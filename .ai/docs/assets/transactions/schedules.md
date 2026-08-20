# Schedules

## Feature Summary

A schedule is a recurring template attached to a `Transaction`: it describes a real financial event the user expects to happen again, and when. `schedule` is the only planning-mode flag on `Transaction` — a schedule with categorized items is, by that fact alone, both a forecast input (via account-balance and investment-value projections) and a category budget-vs-actual input (via the budget-comparison chart). There is no separate flag to opt a schedule into budget participation, and no way to opt one out.

For target-based planning that has no backing transaction — "I expect to spend around this much on groceries, but not through any specific known transaction" — see the standalone [Budget entity](../budget/budget.md) instead. A schedule and a Budget are siblings, not variants of one thing: see that document's "Schedule vs Budget" section for the distinction and when to use which.

## Target User

- Primary:
  Advanced users planning recurring income, expenses, investment activity, and other expected financial events.

- Secondary:
  Users reviewing future cashflow and trying to build stable financial habits.

## User Problem

- Users do not only care about past transactions; they also need to plan what is coming next.
- They need future expectations to appear in forecasts without confusing those expectations with already recorded history.

## User Value / Benefit

- Connects day-to-day tracking with forecasting.
- Reduces repetitive manual entry for recurring events.
- Helps users see expected future cashflow before it becomes reality.

### Functional Benefits

- Supports recurring schedules with automatic recurrence rules.
- A scheduled standard withdrawal/deposit's categorized items count toward category budget comparison automatically, with no extra step.
- Supports an optional inflation rate so a schedule's forecast contribution compounds over time, without affecting the amount actually recorded when an instance is entered.
- Allows scheduled items to be entered as real historical instances when the time comes.

### Conceptual Benefits

- Helps users distinguish between financial facts and financial expectations.
- Reinforces the product philosophy that planning should support awareness, not hide it.

## Scheduled Transaction

A scheduled transaction is a recurring template for a future or repeating event, attached to a real `Transaction` row (`schedule = true`).

Characteristics:

- uses schedule settings instead of being only a one-time dated entry
- has a next expected date (if active) or is considered finished if the next date is empty
- can generate virtual future instances for forecast views
- can optionally be automatically recorded when due
- must be complete in terms of data, since it represents a real expected event: a standard withdrawal/deposit schedule requires real `account_from`/`account_to` values, the same as a historical transaction
- is primarily used to answer what is coming next in concrete operational terms
- if it is a standard withdrawal/deposit with categorized items, those items are automatically included in category budget-vs-actual comparison — this was previously gated behind a separate `budget` flag, which no longer exists

## Schedule Settings

A scheduled transaction may include the following schedule properties:

- start date
- next date
- end date
- count
- frequency
  - daily
  - weekly
  - monthly
  - yearly
- interval
- day-of-week pattern (`by_day`), for ordinal-weekday recurrence such as "first Wednesday of every month" or, combined with month (`by_month`), "last Friday of November every year" — only meaningful for monthly/yearly frequencies
- automatic recording
- inflation (optional), the same flat-annual-rate calculation described in [Budget](../budget/budget.md)'s "Inflation" section — see "Where Inflation Applies" below for exactly which outputs it affects

Important interpretation rules:

- if next date is empty, the schedule is effectively considered finished
- end date and count are alternative ways of defining when recurrence stops
- next date must actually be a real occurrence of the configured recurrence rule (validated server-side); it is trusted verbatim when a scheduled instance is recorded
- when replacing a schedule with a new recurrence pattern, a next date that no longer matches the new rule is cleared rather than carried over

## Catching Up a Missed Schedule

If a schedule's next date has fallen behind (e.g. the app was unused for a while), the user can choose to catch it up to the current date instead of only skipping a single missed occurrence. This advances `next_date` occurrence-by-occurrence until it is on or after today, capped at a defensive iteration limit to guard against a pathological rule looping excessively within one request.

## Where Inflation Applies

A schedule's `inflation` rate is optional, exactly like a Budget's — leaving it unset means the schedule's amount never compounds. When set, it only affects **projected/forecast output**, never the recorded transaction itself:

- **Forecast/projection views are inflation-adjusted:** the monthly account-balance forecast (and, since the cashflow report reads from that same precomputed data, the cashflow report too) and the budget-vs-actual chart's schedule-derived contribution both compound the amount at each calendar-year boundary.
- **Entering a scheduled instance is not inflation-adjusted:** when a user opens the "enter instance" form for a due occurrence, the prefilled amount is the schedule's own base `amount_from`/`amount_to` as stored, unmodified by any inflation multiplier. The user records the real amount they actually observed; inflation is a planning assumption for forecasting ahead, not a value the app imposes on a historical record.

## Core Logic / Rules

- `schedule` is a mode of a transaction, not a top-level alternative to the transaction concept, and not one of two independent flags.
- Historical transactions can be reconciled, but scheduled transactions cannot.
- Scheduled instances can be materialized into real standalone historical transactions when entered.
- Replacing a schedule is treated as a lifecycle action: the base plan is closed and a new version is created.
- Schedules are available for both standard and investment transactions.
- Transfer transactions can be scheduled but never contribute to category budgeting, since transfers never have transaction items.
- Investment transaction schedules contribute to account-balance/investment-value forecasting but never to category budgeting, since investment transactions have no category-based items.
- Schedules mainly drive upcoming-event visibility and forecasted balances; a standard withdrawal/deposit schedule also drives plan-versus-actual category reporting, without any separate setup step.

## User Lifecycle Actions

The transaction workflow includes actions such as:

- create
- edit
- clone
- enter a scheduled instance
- replace a schedule with a new one
- finalize an AI-created draft

These actions show that YAFFA treats transactions as evolving financial records rather than static rows of data.

## User Flow

1. User decides whether the transaction is historical or scheduled.
2. If scheduled, they define the recurrence settings (frequency, interval, dates).
3. YAFFA uses that information to show future expectations in forecasts, and — for a standard withdrawal/deposit with categorized items — in category budget comparison.
4. When a scheduled event becomes real, the user can enter it as an actual historical transaction instance.

## Outputs

- future schedule instances for forecast views and schedule review
- planning input for monthly account-balance and investment-value projections, inflation-adjusted when the schedule has a rate set
- category-level budget contribution for standard withdrawal/deposit schedules with items, likewise inflation-adjusted
- concrete historical entries created from recurring plans, always at the schedule's plain base amount, never inflation-adjusted

## Edge Cases / Constraints

- A recorded transaction is considered to be a historical fact, even if the date is in the future. For example, a bank transfer already scheduled with the bank can be recorded as a future-dated actual transaction, but it is still not treated as a schedule inside YAFFA.
- Investment transactions support scheduling, but a category-level target for a category with no specific investment relationship belongs on a standalone [Budget](../budget/budget.md), not a schedule.
- A known future event that the user does not want reflected as an ongoing category-level planning target (e.g. a one-off reimbursement, a short-lived promotional income stream, a legally fixed amount) is a good fit for a schedule on its own — there is no separate flag to disable its budget contribution, but a standalone Budget is not required either, since a plain schedule already covers "what real transaction do I expect, and when."
- A schedule's inflation rate never changes what a user sees or records when entering a due instance — see "Where Inflation Applies" above. Don't expect the "enter instance" amount to reflect compounding; that's by design, not a bug.

## Dependencies

- Models:
  - Transaction
  - TransactionSchedule
- Services:
  - RecurrenceRuleService (shared with Budget — see [Budget](../budget/budget.md))
  - InflationCalculator (shared with Budget; consumed by forecast/projection paths only, never by the "enter instance" flow)
  - TransactionService
  - scheduled-recording and monthly-summary processing

## Frontend Interaction

- Standard forms expose schedule controls; there is no separate budget checkbox on the transaction form.
- Investment forms expose schedule controls for recurring investment actions.
- Dedicated schedule UI allows the user to choose frequency, interval, dates, automation options, and an optional inflation rate.
- Schedules and standalone Budgets are managed together on one report page — see [Budget and Schedules](../../features/reports/budget-and-schedules.md).

## Confidence Level

High

## Assumptions

- The documentation should use human-centered terms such as historical and scheduled even when the implementation uses flags and schedule models.
