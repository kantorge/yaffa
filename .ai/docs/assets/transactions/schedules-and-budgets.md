# Schedules and Budgets

## Feature Summary

YAFFA treats time behavior as a mode of a transaction rather than a separate domain object. A transaction can represent something that already happened, something that is expected to recur, something that belongs in a forecast or budget, or a combination of those planning-oriented cases.

This is the main bridge between everyday entry and long-term financial planning.

At the user level, the distinction is simple but important:

- A schedule is about a specific expected event or series of events and its timing, including all the details that are expected to come with it. For example:
- I spend X amount on rent every month, and I expect that to continue for the foreseeable future.
- I spend Y amount on electricity every month. The amount might slightly change month to month based on my usage, but I expect the event to recur and I want it to be visible in my calendar and forecasts.
- A budget is about an expected level of spending or income and its role in planning, with some level of uncertainty about the exact real-world transaction that will eventually fulfill that expectation. For example:
- In a typical month, I spend Z amount on groceries, but the exact timing and payee may vary, just like the amount, and the payees.
- I expect to receive W amount from my side business every month, but the exact timing and payee may vary.

For this reason, most of the time, a schedule is also a budget, but a budget is not always a schedule.

## Target User

- Primary:
  Advanced users planning recurring income, expenses, and other expected financial events.

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
- Supports budget-focused projections for standard cashflow.
- Allows scheduled items to be entered as real historical instances when the time comes.

### Conceptual Benefits

- Helps users distinguish between financial facts and financial expectations.
- Reinforces the product philosophy that planning should support awareness, not hide it.

## Business Difference: Schedule vs Budget

Although both concepts are forward-looking, they solve different user problems.

### Schedule = event planning

A schedule answers the question:

- What real transaction do I expect to happen again, and when?

Business meaning:

- operational planning
- recurring financial events
- calendar-like visibility into upcoming activity

Typical examples:

- monthly rent
- salary on a known day
- recurring loan payment
- recurring investment purchase

This is why schedules feed features such as:

- schedule listings and upcoming-transaction review
- forward cashflow forecasting
- future investment and account-balance projections

### Budget = target planning

A budget answers the question:

- How much do I expect or allow myself to spend or receive in this area over time?

Business meaning:

- planning target
- category-level expectation
- benchmark for comparing plan versus reality

Typical examples:

- monthly groceries budget
- weekly eating out allowance
- expected household spending without a known merchant yet

This is why budgets feed features such as:

- budget-versus-actual comparison charts
- category-based reporting
- broader forecast support, even when some details are still uncertain

### Practical rule of thumb

- Use a schedule when the event itself is known.
- Use a budget when the spending or income expectation is known, but the exact real-world transaction is not fully defined yet.
- Use both when a recurring event should also count as part of the user’s planned spending framework.

## Main Modes

### Historical or Regular Transaction

A historical transaction is a concrete event that already happened.

Characteristics:

- has an actual date
- may be reconciled
- contributes to factual history and reporting

### Scheduled Transaction

A scheduled transaction is a recurring template for a future or repeating event.

Characteristics:

- uses schedule settings instead of being only a one-time dated entry
- has a next expected date (if active) or is considered finished if the next date is empty
- can generate virtual future instances for forecast views
- can optionally be automatically recorded when due
- must be complete in terms of data, since it is meant to represent a real expected event rather than a flexible planning placeholder
- is primarily used to answer what is coming next in concrete operational terms

### Budget Transaction

A budget transaction is a planning-oriented expectation used for projection.

Characteristics:

- supports forecast logic rather than statement reconciliation
- may include inflation to reflect changing expected cost or income (NOT FULLY IMPLEMENTED YET)
- is especially relevant for standard cashflow planning
- is allowed to contain partial entries, for example where account or payee is not yet fully known
- is primarily used to compare intent versus reality at the reporting level

### Scheduled and Budgeted Transaction

YAFFA also supports transactions that combine schedule and budget meaning.

Characteristics:

- the event is recurring
- the event also participates in projection-oriented budget logic
- useful when the user wants both recurrence and planning behavior in one place

## Schedule Settings

A planned transaction may include the following schedule properties:

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
- automatic recording for true schedules
- inflation for budget-oriented planning

Important interpretation rules:

- if next date is empty, the schedule is effectively considered finished
- end date and count are alternative ways of defining when recurrence stops

## Core Logic / Rules

- Schedule and budget are transaction modes, not top-level alternatives to the transaction concept.
- A schedule is event-centered, while a budget is target-centered.
- Historical transactions can be reconciled, but scheduled and budget transactions cannot.
- Scheduled instances can be materialized into real standalone historical transactions when entered.
- Replacing a schedule is treated as a lifecycle action: the base plan is closed and a new version is created.
- Budget mode is available for standard cashflow planning, not as a general investment planning layer.
- Transfer is not supported in budget mode.
- Schedules mainly drive upcoming-event visibility and forecasted balances.
- Budgets mainly drive plan-versus-actual reporting and category-level expectation setting.

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

1. User decides whether the transaction is historical or planned.
2. If planned, they define the recurrence or budget settings.
3. YAFFA uses that information to show future expectations in forecasts.
4. When a scheduled event becomes real, the user can enter it as an actual historical transaction instance.

## Outputs

- future schedule instances for forecast views and schedule review
- planning input for monthly projections
- category-level budget values for comparison against actual spending or income
- concrete historical entries created from recurring plans

## Edge Cases / Constraints

- A recorded transaction is considered to be a historical fact, even if the date is in the future. For example, a bank transfer already scheduled with the bank can be recorded as a future-dated actual transaction, but it is still not treated as a schedule inside YAFFA.
- Budget and schedule overlap conceptually, so the documentation must explain the distinction clearly.
- Investment transactions support scheduling, but budgeting is primarily a standard transaction planning concept.
- Some budgets may intentionally remain less specific than schedules, because their value comes from planning and comparison rather than exact operational execution.
- Examples where a scheduled withdrawal or deposit should usually **not** also be marked as a budget:
  - **A known reimbursement or refund**
    - Example: a tax refund or insurance reimbursement is expected on a known date.
    - Why it should usually stay schedule-only: it is a concrete expected event, not an ongoing planning target the user wants to compare against monthly spending behavior.
  - **A temporary or short-lived known event**
    - Example: a three-month promotional income stream or a short subscription with a fixed end date.
    - Why it should usually stay schedule-only: the user wants it reflected in future dates and balances, but it does not represent a durable budgeting category or habit.
  - **A legally or contractually fixed amount where comparison adds little value**
    - Example: child support, a fixed lease charge, or a mandatory service fee.
    - Why it should usually stay schedule-only: the amount is not something the user is trying to manage against a target; they simply need it to appear at the right time.

## Dependencies

- Models:
  - Transaction
  - TransactionSchedule
- Services and jobs:
  - TransactionService
  - scheduled-recording and monthly-summary processing

## Frontend Interaction

- Standard forms expose both schedule and budget controls where appropriate.
- Investment forms expose schedule controls for recurring investment actions.
- Dedicated schedule UI allows the user to choose frequency, interval, dates, and automation options.

## Confidence Level

High

## Assumptions

- The planning modes are intended to enhance user awareness and forecasting rather than turn YAFFA into a fully automated financial system.
- The documentation should use human-centered terms such as historical, scheduled, and budgeted even when the implementation uses flags and schedule models.
