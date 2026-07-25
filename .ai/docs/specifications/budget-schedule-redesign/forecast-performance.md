# Forecast & Schedule-Instance Calculation Performance

## Why This Is a Separate Document

This surfaced while discussing whether future forecast scenarios could be computed on demand (see [future-directions.md](future-directions.md)). It affects the existing, already-shipped account-balance and investment-value forecast calculation today, independent of anything else in this folder — the bug exists regardless of the budget/schedule redesign or scenarios ever being built. It's tracked here as its own document because it was discovered during this line of work and the analysis stands on its own, but **the fix is in scope for the current redesign**: it's adopted into [specification.md](specification.md) as FR-9, a prerequisite that ships before FR-8 (inflation) touches the same code paths — see FR-9 for why implementing FR-8 against the unfixed code would make the problem worse, not better. Scenario planning and debt tracking ([future-directions.md](future-directions.md)) remain out of scope; FR-9 only fixes the baseline calculation that already ships today.

## Reported Symptoms

Production experience (self-hosted VPS: 4 vCPU Xeon E5, 4 GB RAM; local dev: Ryzen 9 HX, 32 GB RAM, WSL2 — dev is not meaningfully faster in practice) at approximately 25,000 transactions and 100 active schedules/budgets:

- Account balance recalculation: ~20-30 seconds.
- Forecast recalculation: ~1-2 minutes.

This led to the existing precomputed/cached `account_monthly_summaries` table (`app/Models/AccountMonthlySummary.php`), which is a source of ongoing dissatisfaction: it's fragile, and forced/manual recalculation is exposed directly in the UI in a way that isn't user-friendly.

## Root Cause Findings (From Code, Not Yet Profiled)

Two specific, fixable inefficiencies were identified by reading the actual implementation — neither is an inherent limit of computing recurring-schedule forecasts in PHP at this data volume.

### 1. `Transaction::scheduleInstances()` clones a full Eloquent model per occurrence

`app/Models/Transaction.php:407-418`:

```php
foreach ($transformer->transform($rule, $constraint) as $instance) {
    $newTransaction = $this->replicate();
    $newTransaction->originalId = $this->id;
    $newTransaction->date = \Illuminate\Support\Carbon::instance($instance->getStart());
    $newTransaction->transactionGroup = 'forecast';
    $newTransaction->schedule_first_instance = $first;
    $scheduleInstances->push($newTransaction);
    $first = false;
}
```

`replicate()` is a full Eloquent model clone (attribute copying, cast machinery) called **once per virtual occurrence**, for every active schedule, up to `virtualLimit` (default 500) or out to the user's forecast end date when a schedule has no `end_date`/`count`. With ~100 active schedules and a multi-year horizon (YAFFA supports long-range projections), this is plausibly tens of thousands of `replicate()` calls per forecast run. The virtual instance is only ever used for arithmetic aggregation (summing `amount_from`/`amount_to`/`cashflow_value` per month) — it doesn't need to be a real Eloquent model with relations and cast machinery at all.

### 2. `getInvestmentValueForecastData()` queries the database inside a per-month loop

`app/Jobs/CalculateAccountMonthlySummary.php:501-508`:

```php
$amount = $quantities->map(function ($quantity, $investmentId) use ($carbonEndOfMonth) {
    $investment = Investment::find($investmentId);
    $latestPrice = $this->investmentService->getLatestPrice($investment, 'combined', $carbonEndOfMonth);
    return $quantity * $latestPrice;
})->sum();
```

`Investment::find()` is a real database round trip, executed **inside** the per-month loop, for every investment with a changed quantity, over the full forecast horizon (potentially 100+ months). This is a classic N+1 pattern — the set of investments involved is small and fixed for the whole run, so this could be fetched once, up front, into an in-memory map, rather than re-queried every month. This is very likely the single largest contributor to the 1-2 minute forecast timing.

## Assessment

Given ~100 active recurring definitions, the actual number of occurrences to compute (even over a multi-decade horizon) is at most a few thousand — a small, bounded arithmetic workload once it isn't paying Eloquent's per-object instantiation cost per occurrence and isn't re-querying the database per month for data that doesn't change within the run. There is reasonable confidence (grounded in reading the code, not yet in a profile) that an on-demand, non-precomputed calculation could run in well under a second on modest hardware if these two things are fixed — this is not a case for needing more hardware or a fundamentally different (non-PHP) computation approach.

## Suggested Fixes (Once Confirmed by Profiling)

1. Replace per-occurrence `replicate()` with a lightweight plain array/DTO representation for virtual (non-persisted) instances — only the fields actually consumed by the aggregation (date, account direction, amount, investment id) are needed; no Eloquent casts, relations, or model events are required for a value that's summed and discarded.
2. Fetch all investment prices (and currency rates, which have the same latest-known-rate-lookup shape elsewhere in the codebase, see `CurrencyTrait::allCurrencyRatesByMonth()` for the existing batching pattern already used in `ReportApiController`) once per forecast run, into an in-memory map keyed by investment/date, instead of querying per month inside the loop.
3. Re-check, once the above are fixed and profiled, whether the `recurr` rule-transformation machinery itself (`ArrayTransformer`) carries meaningful overhead beyond what's needed for a simple "how many occurrences fall in month X" aggregation — this may or may not be worth replacing with direct date arithmetic; don't assume it needs changing without measuring first.

## Relationship to the Existing Caching Architecture

If on-demand computation becomes fast enough, the current `account_monthly_summaries` precomputed table might eventually no longer be necessary *for correctness*. There may still be a separate, much smaller case for a short-TTL cache (e.g. Redis, already used for queues) purely for perceived snappiness when multiple widgets on the same page request the same forecast within a short window — but that is a materially different, simpler thing than today's fragile, user-facing, manually-recalculated summary table, and shouldn't be assumed necessary until the underlying computation is actually fast.

**This is speculative, not a plan.** The current redesign (specification.md, FR-9) only fixes the *forecast* calculation's performance; it does not remove, and is not a step toward removing, `account_monthly_summaries` — see specification.md's Non-Goals. Nothing here was profiled for the table's other job: caching *fact* data (the actual historical account balance and investment value, read by `AccountApiController::getAccountBalance` and `ReportApiController`'s cashflow queries). Whether that path could also be computed live, fast enough to retire the cache entirely, is unmeasured and is called out as a candidate for future investigation in [future-directions.md](future-directions.md).

## Relationship to Future Scenarios

Scenario projections (see future-directions.md) cannot be precomputed the way the baseline can — there are arbitrarily many hypothetical scenario combinations, and the feature is inherently occasional/exploratory. This means the on-demand computation path has to exist and be reasonably fast regardless of what's decided for the baseline's caching strategy. Resolving this performance issue is therefore a practical prerequisite for scenarios being feasible at all, but it has standalone value independent of them.

## Next Step

Profile a real recalculation run with Telescope (already a dependency, disabled by default via `TELESCOPE_ENABLED`) before committing to any specific fix, to confirm whether the cost is dominated by query count/duration (point 2 above), PHP-side object-instantiation overhead (point 1 above), or something not yet identified.

## Profiling Results (Local, 2026-07-25)

Confirmed against the local dev database (much smaller than production: user 1 has 1,605 transactions, 21 active schedules, 11 investments — roughly 6-15x smaller than the reported production scale) by dispatching real `account_balance-forecast` and `investment_value-forecast` jobs via `app:cache:account-monthly-summaries`, draining the queue with `queue:work --stop-when-empty`, and querying Telescope's own `telescope_entries` table directly (rather than the web UI) to correlate job execution windows with the queries issued during them.

**Individual job durations, drained in one run**: the large majority of `CalculateAccountMonthlySummary` jobs (fact/budget tasks, or forecast for accounts with little investment history) completed in 70-300ms. But several forecast jobs took **16s, 19s, 3-4s** individually — even at this reduced local scale. This alone corroborates that forecast calculation scales badly, well before reaching production volume.

**Root cause, precisely confirmed** by inspecting the queries issued during the two slowest jobs (16s and 19s windows):

```
x600: select * from `investments` where `investments`.`id` = 9 limit 1
x600: select * from `investments` where `investments`.`id` = 10 limit 1
x601: select * from `transaction_details_investment` where `transaction_details_investment`.`id` in (5)
```

The count (~600) is not a coincidence: the affected user's `end_date` is set to 2076 — roughly 600 months from now. `getInvestmentValueForecastData()` (app/Jobs/CalculateAccountMonthlySummary.php:476-523) loops once per forecast month regardless of whether anything changed that month, and for **every** month it re-fetches each investment via `Investment::find()` and re-touches `$transaction->config` on the schedule's virtual instances. The `transaction_details_investment` query repeating ~600 times for a single `config_id` reveals a second contributor beyond what was originally suspected: **`Transaction::replicate()` does not carry over already-loaded Eloquent relations** (Laravel's `replicate()` copies attributes, not the `$relations` array), so every one of the ~600 virtual instances generated from the same source schedule has to re-query its own `config` relation from scratch the moment it's accessed, even though it was eager-loaded once on the original (non-replicated) transaction.

**Time breakdown**: total query time across ~4,500 queries in each slow window was only ~3.4-3.8 **seconds** out of the job's 16-19 **second** total wall time. That means roughly 75-80% of the time is pure PHP-side overhead (model instantiation via `replicate()`, attribute casting, collection operations) rather than database time — even though the query *count* (driven by the N+1 pattern) is what's producing that PHP overhead in the first place, by forcing thousands of extra `replicate()`-adjacent relation loads and model hydrations.

**Revised understanding vs. the original hypothesis above**: both original suspects are confirmed, but the specific mechanism for #1 is now precise — it isn't just "replicate() is called a lot," it's "replicate() silently drops loaded relations, causing every virtual instance to independently re-trigger relation queries it should never have needed to make at all."

**Implication for the fix**: caching the per-investment latest price *once per job run* (addressing the `Investment::find()`/`getLatestPrice()` N+1) and either re-attaching loaded relations after `replicate()` or restructuring virtual instances as plain arrays/DTOs that carry the already-resolved `config` data directly (avoiding the relation-drop problem entirely) would eliminate the overwhelming majority of the ~1,200+ redundant queries seen in a single job — and, since query time was only ~20-25% of the total, likely a proportionally larger share of wall-clock time once the cascading PHP-side rehydration those queries trigger is also removed.

## Status

Adopted into [specification.md](specification.md) as **FR-9**, scheduled to ship early in a 3.x release, ahead of FR-8 and the rest of the 4.0.0 work (specification.md, Section 12, Rollout Plan). Not deferred, and not scenario-gated — FR-8's inflation-compounding logic is built on top of the FR-9-simplified `scheduleInstances()`/`getInvestmentValueForecastData()`, not the current implementation.
