# Budget drift suggestions

Extracted from the merged code. Where this differs from `.ai/docs/specifications/budget-drift-suggestions/specification.md` (design intent), the code and this folder win.

## Feature Name

Budget check ("Budget drift suggestions")

## Feature Summary

On request, YAFFA compares what a category has actually cost recently with what the budget and the scheduled transactions say it will cost from next period on. If the two have drifted apart in a lasting way, it proposes a new budget amount. Nothing is stored or changed automatically: the user judges every proposal.

## Target User

- Primary: an intermediate to advanced self-hoster who maintains budgets by hand and reviews them periodically (monthly or quarterly), on the budget report page.
- Secondary: a technical user or an AI agent calling the read-only API or the artisan command.

## User Problem

- Budgets go stale; noticing that a category has run 20% above its budget for months means scanning charts by eye.
- One-off spikes, thin history and a budget that was just changed must not trigger advice.

## User Value / Benefit

- **Functional:** a ranked list (largest monthly effect first) of categories worth a second look, with the evidence (recent actual vs. expected per period), a prefilled budget form to apply it, and a "recommended budget" line on the chart.
- **Conceptual:** keeps budgets an expression of the user's intent that is checked against reality, without the app deciding for them (consistent with conscious manual tracking).

## Automated vs. manual

- Automated: detection, ranking, amount conversion, the recommended series.
- Manual: starting a review, choosing sensitivity/bias/data-through month, and creating, replacing or ignoring each proposal. The feature never writes anything.

## User Flow

1. Open the budget report. The "Budget check" card sits above the chart.
2. Choose the data-through month (default: last complete month), sensitivity and estimate bias.
3. "Check selected categories" (the categories checked in the tree) or "Review all categories".
4. Per recommendation: open the prefilled budget form (replace an existing row, or create a new period), **Hide** it for this page view, or **Stop monitoring this category**. A collapsed list shows why every other unit got no proposal.
5. Selecting one recommendation draws the dashed "Recommended budget" series on the chart.
6. A category's "Calculate budget suggestions" switch (category form) opts it out permanently; a parent's switch off excludes its children.

## Edge Cases / Constraints

- The current month is never analysed; later `data_through` values are clamped.
- Units need enough complete periods (6 months, 4 quarters, 3 years by default) and a forecast horizon within the user's `end_date`; otherwise `insufficient_data`.
- Cadences longer than a year give `insufficient_data`.
- Currencies without any rate give `missing_rates`; the run also reports `currencies_without_rates`.
- A unit with a Budget row and a schedule: the whole gap goes to the budget row; schedules are only listed (with a link to their replace page).
- Stateless: with nothing stored, an unapplied suggestion reappears on every run. "Stop monitoring" is the only lasting silence.

## Dependencies

- Models: `Budget`, `Category`, `Transaction`/`TransactionSchedule`, `Currency`.
- Services: `BudgetChartService`, `BudgetService`, `RecurrenceRuleService`, `InflationCalculator`.
- Related docs: `budget-schedule-redesign/` (budget and schedule model), `reports/budget-and-schedules.md`.

## Confidence Level

High (derived from code and tests). Documentation referred to: the specification, which matched the code except for the points in "Known limitations" in `architecture.md`.
