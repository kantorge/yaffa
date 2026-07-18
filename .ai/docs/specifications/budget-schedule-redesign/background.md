# Budget/Schedule Concept Redesign — Background

This document records the rationale behind [specification.md](specification.md): the current model, the problems found in it, and the principle the specification implements. The specification is the sole source of truth for implementation details (functional requirements, data model, components, testing, rollout) — this document does not restate them.

## Current Model

YAFFA currently models three time-behaviors as two independent booleans on `Transaction`: `schedule` and `budget`, sharing one `TransactionSchedule` recurrence record. Investigation of the actual code (not just the docs) turned up real problems with this.

### Problems Identified

- **No structural enforcement, only UI convention.** `TransactionRequest` validates `budget` as a plain boolean regardless of `transaction_type`/`config_type` — nothing at the backend stops `budget=true` on a transfer or investment transaction; the "can't budget a transfer/investment" rule exists only as disabled Vue checkboxes.
- **`budget` has exactly one observable effect once `schedule` is also true**: inclusion in the category budget-vs-actual chart (`ReportApiController::budgetChart()`, which queries `budget=true` regardless of `schedule`). Account-balance/forecast calculation always treats `schedule=true` as authoritative and explicitly excludes dual-flagged rows from the dedicated budget bucket. Nothing in the UI explains this, so there was no visible reason to check both — this was the original complaint.
- **The critical realization** (surfaced while validating an earlier version of this plan against a real example — a telco bill scheduled transaction split across TV/broadband categories): a scheduled standard withdrawal/deposit with categorized items already *is* valid budget input — the categorized amounts live on the same `TransactionItem` rows the schedule uses. There is no independent "budget" decision to make for it; requiring a second flag (or, in an earlier draft of this plan, a fully separate duplicated `Budget` record) just creates a place for the same fact to drift out of sync when the amount changes.
- The only genuinely independent budget case is a target with **no backing transaction at all** — the docs' own primary example (groceries: uncertain payee, uncertain timing, uncertain exact amount). That case is real and deserves its own entity; the schedule-backed case does not.

## Resulting Principle

The rule going forward, matching the reasoning already used for transfer/investment exclusion (excluded because they structurally have no categorized items, not because of a flag):

- A scheduled standard withdrawal/deposit transaction always contributes to both account-balance forecasting (unchanged, already true today) and the category budget-vs-actual comparison — no per-transaction flag, no opt-out.
- Transfers never contribute to category budgeting (no items — structural, unchanged).
- Investment transactions never contribute to category budgeting (no items — structural, unchanged); they still contribute to account-balance forecasting via `schedule`, unchanged.
- A standalone `Budget` entity (category + target amount + its own period definition, no linked transaction) exists only for targets that aren't tied to any known transaction.

This removes the `budget` column/flag/UI from `Transaction` entirely — no replacement flag, not even an opt-out — and introduces `Budget` only for the standalone case. Scenario planning (base + worst-case/best-case budget variants) is explicitly out of scope for this pass, but a real `Budget` entity is what makes it a clean future addition (e.g. a nullable `budget_scenario_id` later) rather than something bolted onto transaction flags.

## Account Scoping for Budgets

`Budget.account_id` is nullable (FR-4), which raises a question that was initially considered a candidate open question: how does an account-agnostic budget relate to a per-account one for the *same category*, given YAFFA already supports both, and `account_monthly_summaries.account_entity_id` is already nullable for exactly this reason? Two real, coexisting use cases motivate this:

1. **Portfolio-wide category target.** "I plan to spend X on restaurants this month" — no specific account, the value is in seeing the category trend against actual spending and in the overall wealth/cashflow trajectory.
2. **Account-specific forecast detail.** "Half of my grocery budget usually goes through my credit card" — a more detailed assumption whose purpose is to check that account's own projected balance (e.g., against a credit limit), on top of (not necessarily reconciled with) the generic category-level target.

The naive design risk is double-counting: if a generic $400 groceries budget and a $200 credit-card-scoped groceries budget both exist, does the category total read $400, $600, or does the system need to infer that the $200 is "part of" the $400?

**Resolved rule**: it reads $600, and that is by design, not a bug to prevent. `Budget` rows are summed flatly per category, regardless of `account_id` — an account-scoped row is not treated as an allocation of, or exclusive with, an account-agnostic row for the same category. The system does not attempt to detect or reconcile overlap. The safeguard is transparency, not deduplication: any place a category's summed budget figure is shown must let the user see which `Budget` rows contributed to it (FR-7), so an unintended duplicate is something the user notices and fixes, not something YAFFA silently resolves.

This also fixes the account-attribution side: a `Budget` row with `account_id` set feeds that specific account's own balance-forecast bucket in `account_monthly_summaries` (`account_entity_id` = that account) *in addition to* counting toward the category total; a row with `account_id = null` feeds the existing account-agnostic bucket (`account_entity_id = null`), which rolls up into whole-portfolio forecasting but is not attributed to any single account's balance. No new schema was needed — the nullable `account_id` on `Budget` and the pre-existing nullable `account_entity_id` on `account_monthly_summaries` were already sufficient; the only thing that needed deciding was the summation rule itself.
