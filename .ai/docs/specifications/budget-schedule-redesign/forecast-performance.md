# Forecast & Schedule-Instance Calculation Performance

## Why This Is a Separate Document

This affects the account-balance and investment-value forecast calculation independently of anything else in this folder — the underlying performance defect exists regardless of the budget/schedule redesign or future scenarios ([future-directions.md](future-directions.md)) being built at all. It's tracked here as its own document because the analysis stands on its own, but the fix is in scope for this redesign: it's specified in [specification.md](specification.md) as **FR-9**, a prerequisite that ships before FR-8 (inflation) touches the same code paths — see FR-9 for why layering inflation onto the unfixed calculation would make the problem worse, not better. Scenario planning and debt tracking ([future-directions.md](future-directions.md)) are out of scope for this fix; FR-9 only fixes the baseline forecast/schedule-instance calculation.

## Reported Symptoms

Production experience (self-hosted VPS: 4 vCPU Xeon E5, 4 GB RAM; local dev: Ryzen 9 HX, 32 GB RAM, WSL2 — dev is not meaningfully faster in practice) at approximately 25,000 transactions and 100 active schedules/budgets:

- Account balance recalculation: ~20-30 seconds.
- Forecast recalculation: ~1-2 minutes.

This is what led to the existing precomputed/cached `account_monthly_summaries` table (`app/Models/AccountMonthlySummary.php`) in the first place — it's fragile, and forced/manual recalculation is exposed directly in the UI in a way that isn't user-friendly.

## Root Cause

Two specific, fixable inefficiencies, confirmed both by reading the implementation and by profiling a real recalculation run against the local dev database (user 1: 1,605 transactions, 21 active schedules, 11 investments — roughly 6-15x smaller than the reported production scale) via Telescope's `telescope_entries` table, correlating job execution windows with the queries each one issued:

### 1. `Transaction::scheduleInstances()` called a full Eloquent clone per occurrence

The method built each virtual forecast occurrence via `$this->replicate()` — a full Eloquent model clone (attribute-array copy, cast re-application, model event dispatch) — called once per occurrence, for every active schedule, up to `virtualLimit` (default 500) or out to the user's forecast end date when a schedule has no `end_date`/`count`. With ~100 active schedules and a multi-year horizon (YAFFA supports long-range projections), this was tens of thousands of `replicate()` calls per forecast run, for a value that's only ever summed and discarded — no Eloquent casts, relations, or model events were actually needed.

Profiling a single job with a schedule whose `end_date` was set roughly 600 months out confirmed this at scale: the job took **16-19 seconds**, and query logs showed `investments`/`transaction_details_investment` lookups repeating ~600 times — once per generated forecast month — for what should have been one fixed, small set of investments for the whole run. Total query time across ~4,500 queries in that window was only ~3.4-3.8 **seconds** of the job's 16-19 **second** wall time — roughly 75-80% of the time was pure PHP-side overhead (model instantiation, attribute casting, collection operations), not database time.

(One theory floated during investigation — that `Transaction::replicate()` silently drops already-loaded Eloquent relations, forcing each virtual instance to independently re-query its own `config` relation — turned out not to apply to the Laravel version actually installed in this project, `laravel/framework` v12.65.0: `Model::replicate()` does preserve loaded relations via `setRelations()`. The PHP-side overhead measured above comes from `replicate()`'s own per-call cost — copying the attribute array via `setRawAttributes()` (a raw copy, not routed back through the mutator/cast-setting pipeline) plus dispatching the `replicating` model event — not from relation re-querying or cast re-application. This doesn't change the fix below, which replaces `replicate()` entirely regardless of which specific cost dominates.)

### 2. `getInvestmentValueForecastData()` queried the database inside a per-month loop

```php
$amount = $quantities->map(function ($quantity, $investmentId) use ($carbonEndOfMonth) {
    $investment = Investment::find($investmentId);
    $latestPrice = $this->investmentService->getLatestPrice($investment, 'combined', $carbonEndOfMonth);
    return $quantity * $latestPrice;
})->sum();
```

`Investment::find()` was a real database round trip, executed **inside** the per-month loop, for every investment with a changed quantity, over the full forecast horizon (potentially 100+ months) — a classic N+1 pattern. The set of investments involved is small and fixed for the whole run, so this is fetched once, up front, into an in-memory map instead.

Given ~100 active recurring definitions, the actual number of occurrences to compute (even over a multi-decade horizon) is at most a few thousand — a small, bounded arithmetic workload once it isn't paying per-object instantiation cost per occurrence and isn't re-querying the database per month for data that doesn't change within the run.

## The Fix (FR-9)

1. `Transaction::scheduleInstances()` builds each virtual occurrence as a lightweight, non-Eloquent representation (`App\Support\ScheduleInstance`, a plain attribute/relation bag) instead of `replicate()`-ing a full Eloquent model — carrying only the fields the forecast aggregation actually consumes (date, account direction, amount, investment id, resolved `config`).
2. `CalculateAccountMonthlySummary::getInvestmentValueForecastData()` fetches every investment's price once per job run, up front, into an in-memory map — reusing the batching pattern already used for currency rates elsewhere in the codebase (`CurrencyTrait::allCurrencyRatesByMonth()`) — instead of querying per investment per forecast month.

Both are verified by regression tests asserting query count stays flat regardless of the number of virtual occurrences generated or the forecast horizon length (`tests/Unit/Models/TransactionScheduleInstancesTest.php`, `tests/Unit/Jobs/CalculateAccountMonthlySummaryTest.php`) — not merely by observing a faster wall-clock time — with forecast output values asserted unchanged before/after.

## Relationship to the Existing Caching Architecture

If on-demand computation is fast enough, the current `account_monthly_summaries` precomputed table might eventually no longer be necessary *for correctness*. There may still be a separate, much smaller case for a short-TTL cache (e.g. Redis, already used for queues) purely for perceived snappiness when multiple widgets on the same page request the same forecast within a short window — but that is a materially different, simpler thing than today's fragile, user-facing, manually-recalculated summary table, and shouldn't be assumed necessary until actually measured.

**This is speculative, not a plan.** This redesign (specification.md, FR-9) only fixes the *forecast* calculation's performance; it does not remove, and is not a step toward removing, `account_monthly_summaries` — see specification.md's Non-Goals. Nothing here was profiled for the table's other job: caching *fact* data (the actual historical account balance and investment value, read by `AccountApiController::getAccountBalance` and `ReportApiController`'s cashflow queries). Whether that path could also be computed live, fast enough to retire the cache entirely, is unmeasured and is called out as a candidate for future investigation in [future-directions.md](future-directions.md).

## Relationship to Future Scenarios

Scenario projections (see future-directions.md) cannot be precomputed the way the baseline can — there are arbitrarily many hypothetical scenario combinations, and the feature is inherently occasional/exploratory. This means the on-demand computation path has to exist and be reasonably fast regardless of what's decided for the baseline's caching strategy. Fixing this performance issue is therefore a practical prerequisite for scenarios being feasible at all, but it has standalone value independent of them.
