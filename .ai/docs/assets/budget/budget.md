# Budget

## Feature Name

Budget

## Feature Summary

A Budget is a standalone, category-level spending or income target with no linked transaction. It answers "how much do I expect or allow myself to spend or receive in this category over time?" when the exact real-world transaction that will eventually fulfill that expectation is not known yet — no fixed payee, no fixed date, sometimes not even a fixed account.

A Budget is distinct from a [scheduled transaction](../transactions/schedules.md): a schedule describes a real, specific event ("rent, every month, from checking"), while a Budget describes a target level for a category ("around this much on groceries, in general"). Both feed the same category budget-vs-actual comparison, but a Budget exists precisely for the case where there is no concrete transaction to schedule.

## Schedule vs Budget

A schedule and a Budget are siblings, not variants of one thing:

- A **schedule** is attached to a real `Transaction` and describes a specific expected event — a known payee, a known (or determinable) date, and — for a standard withdrawal/deposit — known categorized items. Use a schedule when you know what the actual transaction will look like.
- A **Budget** is a standalone entity with no linked transaction — just a category, an optional account, a target amount, and a period. Use a Budget when you have a spending/income target for a category but no single concrete transaction to represent it (no fixed payee, no fixed date, sometimes not even a fixed account).

Both feed the same category budget-vs-actual comparison and the same account-balance forecast (when account-scoped), but neither replaces the other: a schedule cannot express an unattached target, and a Budget cannot represent a specific real event.

## User Problem

- Users want to set a spending/income target for a category — "about this much on groceries" — without inventing a fake transaction to represent it.
- Some planning targets never have a single known payee, date, or even account, and forcing them into a transaction-shaped record hides that uncertainty instead of representing it honestly.
- Users need to know, when a category's budget total looks off, exactly which underlying entries produced that total — not just trust an opaque sum.

## Target User

- Primary:
  Advanced users planning category-level spending or income targets that don't correspond to one specific known transaction.

- Secondary:
  Users reviewing budget-vs-actual reports who need to understand what makes up a category's target.

## User Value / Benefit

### Functional Benefits

- Lets a category-level target exist without a fake or placeholder transaction.
- Supports a target that is account-agnostic (a general household target) or scoped to one specific account, and both can coexist for the same category.
- Automatically tracks whether a target's own period is still active, the same way a schedule's active state is maintained.

### Conceptual Benefits

- Keeps "what I expect to happen" (schedules) and "what I'm aiming for" (budgets) as separate, honestly-represented concepts instead of overloading one model to mean both.
- Makes a budget total's composition inspectable rather than hidden inside a single number.

## Concept Description

A Budget is always category-scoped: `category_id` is required. It optionally references an account (`account_id`, nullable) — when set, the Budget additionally feeds that specific account's own balance-forecast projection, on top of counting toward its category's overall total; when null, it is account-agnostic and only counts toward the category total. A Budget for the same category can exist both account-scoped and account-agnostic at once — the system deliberately does not attempt to detect or exclude overlap between them (see "Multiple Budgets for the Same Category" below).

Because a Budget has no linked transaction to derive a direction from, it stores its own `transaction_type` (`withdrawal` or `deposit`) so its amount can be signed correctly wherever it's projected — the same sign convention already used for scheduled items.

A Budget has its own period definition (frequency, interval, start date, optional end date, optional count, optional inflation rate) — the same recurrence vocabulary a schedule uses, computed through the same shared recurrence logic, but evaluated in aggregate per period rather than advanced instance-by-instance the way a schedule's `next_date` is. A Budget has no `next_date` and no automatic-recording option: there is no transaction for it to record.

### Currency

A Budget never stores its own currency. Its effective currency is always derived: the linked account's current currency when `account_id` is set, or the user's base currency when it is null. This is deliberate — storing a separate `currency_id` would let a Budget's currency silently drift out of sync with its account's; deriving it instead means there is nothing to reconcile, ever, even if the account's own currency later changes.

A known limitation of this: a user who wants an account-agnostic budget in a currency other than their base currency has no direct way to express that. The workaround is to create a real account in the desired currency and attach the budget to it.

### Active state

Like `TransactionSchedule.active`, a Budget's `active` flag is always computed, never user-set. A Budget is active if its recurrence rule has at least one occurrence on or after the date of evaluation; it's recomputed on create/update and refreshed by the same periodic recalculation job that maintains schedule active flags. An inactive Budget (its rule is exhausted — past its end date/count) is excluded from both the budget-vs-actual chart and the account-balance forecast bucket, mirroring how an inactive schedule is already excluded from the forecast.

### Inflation

A Budget's `inflation` field, when set, is a flat annual percentage rate that compounds once per calendar-year boundary (not on the anniversary of the start date) — the same compounding calculation a [Schedule](../transactions/schedules.md) can independently use for its own `inflation` field. It only affects projected output (the budget-vs-actual chart, and the account-balance forecast bucket when the Budget is account-scoped) — there's no "enter instance" concept for a Budget to worry about, since it has no linked transaction.

## Inputs

- Category (required)
- Account (optional — leave unset for an account-agnostic target)
- Transaction type: withdrawal or deposit (required)
- Target amount
- Optional comment
- Frequency, interval, start date, optional end date, optional count
- Optional inflation rate

## Outputs

- A category-level contribution to the budget-vs-actual comparison chart, for every period the Budget's recurrence rule is active
- When account-scoped, a contribution to that account's own balance-forecast projection
- A visible line item in any breakdown/drill-down of a category's budget total, so the total's composition (which Budget rows, and their accounts) is never hidden

## Domain Concepts Used

- Category: the required scope of every Budget.
- Account: the optional scope of a Budget, when the target should also feed one account's own forecast.
- [Schedule](../transactions/schedules.md): the sibling concept for a real, specific expected transaction rather than a category-level target.
- RecurrenceRuleService: the recurrence/period logic shared between `TransactionSchedule` and `Budget`.

## Core Logic / Rules

- A Budget is always category-scoped; account scope is optional and additive, not exclusive.
- A Budget never stores its own currency — it is always derived from its account (or the base currency, if account-agnostic).
- A Budget's `active` flag is always computed from its recurrence rule, never accepted as user input.
- A category's budget-vs-actual total is the sum of every active Budget row for that category, regardless of account, plus every schedule-derived contribution — an account-scoped row and an account-agnostic row for the same category both count, and this is expected, not a defect.
- A scheduled transaction's own items are never double-counted between the forecast bucket and the standalone-budget bucket — a Budget and a schedule are structurally separate sources that get summed, not reconciled against each other.
- Only standard-transaction-shaped directions apply: `transaction_type` is restricted to `withdrawal`/`deposit`. Transfers and investment transactions are structurally excluded from category budgeting, and a Budget cannot represent either.
- A Budget is managed on the same report page as schedules — there is no separate Budget page (see [Budget and Schedules](../../features/reports/budget-and-schedules.md)).

## User Flow

1. User identifies a category-level spending or income target with no single known transaction behind it.
2. User creates a Budget for that category, optionally scoping it to one account, and sets the target amount and recurrence.
3. YAFFA includes the Budget's projected amounts in the category's budget-vs-actual chart for every active period, and — if account-scoped — in that account's own forecast.
4. If the user later wants to see what a category's total is made of, the chart's breakdown shows every contributing Budget row and its account.

## Edge Cases / Constraints

### Multiple Budgets for the Same Category

The system does not deduplicate or enforce non-overlapping Budget rows for the same category. An account-scoped Budget (e.g. groceries via a specific credit card) and an account-agnostic one (e.g. a generic groceries target) for the same category are both summed into the category total — the user is expected to manage this deliberately, using the visible breakdown as the safeguard against unintended double counting, rather than the system silently merging or rejecting overlapping entries.

### Currency mismatch is structurally impossible

Because currency is always derived rather than stored, a Budget can never end up in a state where its displayed currency has drifted from its account's — if the account's currency changes, every Budget attached to it picks up the new currency automatically.

## Related Product Behaviors

- Reports: the category budget-vs-actual chart sums every active Budget for a category alongside schedule-derived contributions.
- Account forecasting: an account-scoped Budget contributes to that account's own balance-forecast projection.
- Schedules report: Budgets are listed, created, edited, and deleted from the same page as schedules.

## User Interaction

- Users create, edit, and delete Budgets from the schedules report page, alongside real schedules — there is no dedicated Budget page.
- The budget-vs-actual chart's drill-down shows each contributing Budget row (category, account or "no account", amount) so a total is never opaque.

## Confidence Level

High

## Assumptions

- The intended audience for Budget is the same advanced-planning user who already uses schedules; Budget is a peer concept, not a simplified alternative for beginners.

## Known Limitations / Open Questions

1. An account-agnostic Budget cannot be expressed in a currency other than the user's base currency; the documented workaround is to attach it to a real account in the desired currency instead.
2. Scenario planning (base/best-case/worst-case Budget variants) is out of scope for the current design — see the redesign's future-directions notes if that memory exists.

## Completeness Assessment

Complete

The Budget concept has a coherent, self-contained purpose distinct from Schedule: representing a category-level planning target that has no specific transaction behind it, with its total's composition always visible to the user.
