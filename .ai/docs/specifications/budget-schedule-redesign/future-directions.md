# Budget/Schedule Redesign — Future Directions (Scenarios & Forecasting)

This document is directional only — it is not a build-now specification. It records alignment decisions for a future "forecast scenarios" feature (base/worst-case/best-case planning) so that the budget/schedule redesign in [specification.md](specification.md) doesn't need rework when scenarios are eventually designed. Nothing in this document is being built as part of the current spec, except where explicitly noted in "Immediate action for the current spec" below.

## Recommended Scenario Architecture: Overlay/Delta, Not Parallel Data

There is exactly one real baseline: today's actual `Budget` and `Schedule` data, untagged by any scenario concept. A future `Scenario` is a named set of small adjustment rules applied on top of that real baseline only when computing a scenario's projection — never a parallel, duplicated copy of the baseline data. Each adjustment rule references a target (a specific `Budget` row, a specific schedule, or nothing, for a pure addition) and a kind: override amount, disable, add-hypothetical, or percentage adjustment.

This is the same principle that ruled out the original fully-decoupled `Budget` design earlier in this redesign (don't duplicate real category/amount facts — express deltas instead), applied one level up. It has two direct consequences:

- **"Baseline" is not a named scenario.** It's simply "no adjustments applied." There is no need for a `Scenario` row to represent it, and critically, `Budget`/schedule rows should **not** eventually gain a nullable `scenario_id` where `null` means baseline — that shape belongs to the rejected parallel-container alternative, not this one.
  - For the **user**, this means: you never set anything up to get a baseline. Your everyday Budgets and Schedules — the ones you'd have even if the scenario feature didn't exist — are the baseline, automatically, with no "create baseline scenario" step. When you later create a "Worst case" scenario, you don't copy your whole budget setup into it — you only record what's different: "assume my salary schedule stops," "assume groceries goes to $600 instead of $400." Everything you didn't mention (rent, utilities, your car budget, whatever) stays exactly as your real data says, automatically, for that scenario too.
  - This has a very practical consequence: scenarios stay cheap to create and maintain. A "worst case" might be two or three adjustment lines, not a full duplicate of every category you track. And if you go update your real groceries budget from $400 to $450 next month, every scenario that doesn't specifically override groceries picks that up automatically — you're not maintaining N parallel copies of your financial life, just your one real one plus a short list of "what ifs" per scenario.
  - For the **architecture**, this rules out a specific, tempting shortcut: adding a nullable scenario_id column to budgets (and eventually transaction_schedules), where null means "this is baseline." That looks simple, but it's the same anti-pattern this whole redesign already rejected once — it was the reason the first draft of decoupling Budget (duplicating category/amount data into a second place) got thrown out for the telco-bill case. If every scenario needs its own row per Budget it cares about, you're back to N copies of the same facts, and a $50 grocery increase now means updating it in the real budget and in however many scenarios also track groceries.
  - Instead, a future Scenario is defined entirely by rows in a new, separate, small table (something like scenario_adjustments: scenario_id, target_type, target_id nullable, kind, value) that references existing Budget/schedule rows without ever touching or duplicating them. "What does scenario X look like" becomes a query-time operation: fetch the real baseline data, then lay that scenario's adjustment rows on top (skip disabled targets, substitute overridden amounts, append pure additions). Nothing about budgets or transaction_schedules themselves changes to support this — which is exactly the guarantee this whole redesign has been trying to give you throughout.

- **"Promoting" a scenario to baseline is a real, explicit write** — it means applying (materializing) the scenario's adjustment rules onto the actual `Budget`/schedule data, not flipping a pointer or a flag. This matches how a user would actually describe the action ("I've lost my job, let's go with worst-case _from now on_" — adopting a new reality, not just relabeling a view).
  - For the **user**: when you say "I lost my job, let's go with worst-case from now on," you mean your actual financial plan changes — the salary schedule really gets disabled, the reduced grocery budget really becomes your real grocery budget. It's not "now I'm just looking at the worst-case tab by default" — it's "my plan is different starting today." That's a meaningful, deliberate action, not a view preference, so it should feel like one in the UI: probably a confirm-before-you-commit interaction, since it's changing real numbers that drive your real forecasts, not a toggle you can casually flip back.
  - One side effect worth naming: after promotion, your other scenarios don't automatically make sense anymore. If you also had a "best case: I get a raise" scenario defined as an adjustment against the old salary schedule, and that salary schedule just got disabled by the worst-case promotion, "best case" is now stale and probably needs a second look — promotion doesn't ripple through and fix up every other scenario for you.
  - For the **architecture**: because baseline isn't a row (per the first bullet), there's no pointer to flip when promoting — there's nothing like scenarios.is_baseline to reassign. Promotion has to be a genuine batch of writes: for each adjustment rule in the scenario, perform the equivalent real mutation — an "override amount" rule actually updates the real schedule's amount, a "disable" rule actually ends/deactivates the real schedule, an "add-hypothetical" rule actually inserts a new real Budget row. Critically, this should go through the same service-layer methods normal edits already use (e.g. whatever BudgetService/TransactionService methods already exist for editing a schedule or budget), not raw database writes — so all the normal side effects (recalculating monthly summaries, validation, authorization, events) still fire correctly, exactly as if the user had made each of those edits by hand.
  - The practical takeaway for whoever eventually builds this: don't design "promote" as a cheap operation, because it isn't one — it's functionally a batch of ordinary edits, just triggered by a single button, and it deserves the same weight (confirmation, audit trail of what changed) as any other consequential write in the app.

## Per-Question Directional Answers

### Investment price forecasting

Confirmed by research: investment price forecasting does not exist today in any form. `CalculateAccountMonthlySummary::getInvestmentValueForecastData()` projects future holding _quantity_ from scheduled buy/sell transactions, but always prices those future holdings at the single latest known price — flat, forever. There is no expected-return, growth-rate, or projection field anywhere on `Investment` or `InvestmentPrice`.

Introducing an assumed-growth-rate concept (e.g. an expected annual return on `Investment`) is a legitimate future planning capability, and it should reuse the same compounding utility introduced for budget inflation (see "Immediate action" below) rather than inventing a second growth-rate mechanism. Scenario variants of it (best-case/worst-case return assumptions) are naturally expressed as adjustment rules targeting that assumption — not a new kind of scenario mechanism. (While less likely for a budget, an assumed decrease needs to be supported too.)

### Currency rate forecasting

Same answer, same reasoning. Confirmed: `Currency::rate()` and `CurrencyTrait::getLatestRateFromMap()` both always resolve to the single latest known historical rate for any date, including future forecast dates — there is no exchange-rate projection anywhere. If currency drift assumptions are introduced later, they should reuse the same compounding utility and the same scenario-adjustment-rule mechanism as investment returns and budget inflation.

### Debt tracking (loans, mortgages, credit cards)

Raised while discussing inflation and scenarios: is debt planning/forecasting in scope here? The simplest case is already covered by this redesign with no extra work — a recurring loan payment is just a scheduled standard withdrawal with a category, and per FR-1 that already counts toward both cashflow forecasting and the budget-vs-actual chart.

What's genuinely missing, and would be new domain modeling rather than something this redesign provides, is *amortization awareness*: knowing that a payment splits into interest and principal, tracking the remaining balance as its own forecastable quantity, and projecting payoff timelines. There's no equivalent today of `account_monthly_summaries`' `investment_value` transaction type for a debt balance — a future `Debt` entity (principal, rate, term) with its own balance-projection logic, parallel to how `Investment` tracks quantity × price, would be needed.

That said, it isn't an unrelated concept — it would reuse two things already established here rather than needing its own mechanisms:

- **The compounding-rate utility (FR-8, current spec).** Loan interest accrual and inflation compounding are the same underlying math (a periodic rate applied to a base value over time), just pointed in different directions. This makes debt balance projection a natural third sibling alongside investment price growth and currency drift in the "assumed rate projects a value forward" family described above — worth reusing the same utility, not building a fourth version of it.
- **The scenario overlay/delta model.** Debt payoff scenarios ("what if I pay an extra $200/month," "what if I refinance at a lower rate") are a clean, realistic example of adjustment rules over a baseline — not new data. If anything, this is a good validation that the overlay model generalizes beyond budgets and schedules.

Out of scope for now; noted here only so a future debt-tracking feature reuses the existing compounding utility and scenario mechanism rather than reinventing them.

### Baseline scenario

Not a distinct entity. See "Recommended Scenario Architecture" above — baseline is the absence of adjustments, not a row to look up.

### Are scheduled transactions part of scenarios?

Yes. A schedule's scenario variant (e.g. "assume this income schedule stops," "assume this rent schedule increases 15%") is an adjustment rule referencing the real schedule — never a duplicated, hypothetical `Transaction`. Real schedules remain exactly what they are today; a scenario changes only how their amounts are interpreted when projecting _that scenario's_ forecast.

### What reports are affected by scenarios?

Only forward-looking projection reports need scenario awareness:

- The cashflow/forecast report (`ReportApiController::getCashflowData`)
- The category budget-vs-actual chart (`ReportApiController::budgetChart`)
- A future scenario comparison view (see below)

Everything that shows **real** data is untouched, because scenarios never create real schedule rows:

- The account view's schedule table (`resources/js/account/show.js`, `#scheduleTable`)
- The dashboard schedule calendar (`ScheduleCalendar.vue`)
- The schedules-and-budgets maintenance list (`resources/js/reports/schedules.js`)
- The enter/skip/edit/clone/replace actions on any of the above

This also directly resolves "what if a scheduled instance from a scenario is entered — is this allowed at all?": the question doesn't arise under this model. There is no such thing as a schedule that exists only inside a scenario; all schedules are real and shared across every scenario view. Only their _assumed forecast amounts_ are adjusted per scenario, never their existence or their "enter instance" eligibility.

**Concretely**: say the real rent schedule is $1,200/month, and a "worst case" scenario has an override adjustment assuming $1,400/month (a rent increase the user is bracing for). The account view's schedule table and the dashboard calendar always show and act on the _real_ $1,200 schedule — they have no concept of "which scenario is currently selected," because they're not projection reports. If the user clicks "enter this month's rent instance," it always records a real transaction using the real $1,200 schedule, never the scenario's $1,400 assumption, regardless of which scenario the user happens to have open in some other tab or report at the time. The $1,400 figure only ever appears inside that scenario's own projection (the forecast/budget-chart views, viewed with that scenario selected) — it has no materialization path of its own. If the rent increase actually happens and the user wants $1,400 to become the real, ongoing schedule amount, that is exactly the "promote to baseline" operation described above (an explicit write to the real schedule), not something that falls out of the ordinary "enter instance" action.

### Can a scenario be promoted to baseline?

Yes — see "Recommended Scenario Architecture" above. It is an explicit, confirmed write that applies the scenario's adjustment rules onto the real `Budget`/schedule data, not a metadata flip.

### New views needed to compare scenarios

Primarily a new, dedicated scenario-comparison view, computed on demand for the selected scenario(s) at request time — **not** precomputed per scenario into `account_monthly_summaries`, which would multiply storage and job load by the number of scenarios for a feature that is inherently occasional/exploratory. Existing reports (cashflow, budget chart) would gain an optional single-scenario selector defaulting to baseline, rather than becoming inherently multi-scenario.

### How to handle budget (and related assets) in scenarios: add new / disable baseline / modify baseline?

All three reduce to one generic adjustment-rule shape: a target (nullable, for pure additions), a kind, and a value.

- **Add new**: target is null; the rule introduces a hypothetical `Budget`/schedule-like line that exists only within that scenario's projection.
- **Disable baseline item**: target references a real `Budget`/schedule row; the rule excludes it from that scenario's projection.
- **Modify baseline**: two options, to be decided when scenarios are actually designed, not now:
  - A distinct "override" kind referencing the real row — better UX continuity (the line still reads as "Rent," just adjusted), at the cost of one more adjustment kind to implement.
  - Reduced to "disable original + add replacement" — fewer mechanisms, but loses the line's identity/continuity in any report that lists adjustments.

Leaning toward the distinct "override" kind for UX clarity, but this is a detail, not a decision that needs to be made now.

## Immediate Action for the Current Spec

Everything above is directional only. The one concrete, near-term action is specified as FR-8 in [specification.md](specification.md): implement the previously-unused `inflation` field's compounding calculation as a small, standalone, reusable utility, deliberately kept separate from the calendar-occurrence/recurrence-listing logic (FR-5) — so the same utility can be reused later for investment-return and currency-drift assumptions above, without rework.
