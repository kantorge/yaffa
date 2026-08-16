# Precision Improvements Specification

## 1. Purpose

Eliminate YAFFA's float-precision bug class — currently patched around, not fixed, at two proven sites (`app/Services/TransactionItemMergeService.php`'s epsilon comparison and `find-transactions/helpers.js`'s `round2()`) — and lay a phased, independently-shippable path toward exact decimal arithmetic across the rest of the money/quantity surface. This document is self-contained and intended as an implementation handoff for a coding agent.

See [background.md](background.md) for the current-state analysis, the real-vs-perceived benefit framing, and the library options considered. This document does not restate that rationale — it defines what to build.

## 2. Goals

- Remove the two proven float-precision workarounds (`AMOUNT_COMPARISON_EPSILON`, `round2()`) and replace them with exact decimal arithmetic, at zero new dependency cost (Phase 0).
- Fix the `investment_prices.price` / `transaction_details_investment.price` decimal-scale inconsistency for the same logical value.
- Establish a `MoneyCast`/`DecimalCast` pattern that can replace the blanket `'float'` Eloquent casts incrementally, model by model, without a big-bang rewrite (Phase 1+).
- Close the gap where `Currency.generic_decimal_precision`/`detailed_decimal_precision` are computed but never used to clamp a submitted value (Phase 3).
- Keep each phase independently reviewable, testable, and shippable — a phase must not require a later phase's code to compile or pass its own tests.

## 3. Non-Goals

- A full backend/frontend BigDecimal migration delivered as one change. Phases 1-4 are scoped and sequenced but not committed to a single release. **All named dependencies are approved** (`brick/math`/`brick/money` for Phases 1-2; `decimal.js`'s promotion to a direct npm dependency for Phase 0/FR-3 and Phase 3/FR-6) — every phase may proceed in order.
- FX rate staleness/reconciliation between a transfer's implied rate and the `CurrencyRate` table — an existing, deliberate product decision, not a precision bug (see background.md).
- Investment cost-basis/realized-gain calculation — does not exist in the codebase yet, so there is nothing here to harden.
- Any change to `resources/js/shared/lib/i18n/format.js`'s display formatting — it is already correctly centralized and reused; this work changes what value is computed and stored, not how a correct value is displayed.
- Performance work. Arbitrary-precision arithmetic is not a speed improvement (background.md); no phase here should be justified or measured on that basis.

## 4. Functional Requirements

### FR-1: Exact decimal comparison in `TransactionItemMergeService` — Phase 0, no new dependency — ✅ Completed

Replace the float-tolerance guard in `app/Services/TransactionItemMergeService.php:11,98` (`AMOUNT_COMPARISON_EPSILON = 0.0001`, `abs($originalTotal - $newTotal) > self::AMOUNT_COMPARISON_EPSILON`) with an exact comparison using PHP's `bcmath` extension (`bccomp($originalTotal, $newTotal, $scale)` at a fixed scale matching `transaction_items.amount`'s column precision). `ext-bcmath` is already compiled into the local dev image (`vendor/laravel/sail/runtimes/8.4/Dockerfile:46`), so this requires no `composer require` and no dependency-approval gate. The two summed values being compared should be built from the raw string amounts (as read from the `DECIMAL` column) rather than from the float-cast model attribute, since casting to float before comparing would reintroduce the exact drift this FR removes.

**New runtime requirement to declare and document.** `ext-bcmath` isn't in YAFFA's currently-published required-extensions list ([yaffa.cc/documentation/getting-started/installation/technology/](https://yaffa.cc/documentation/getting-started/installation/technology/) lists 13 extensions — Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML — `bcmath` is not among them). It's present in the Sail dev image but that's not guaranteed on a self-hosted deployment running its own PHP stack. Two actions, both in scope for this FR:

1. Add `"ext-bcmath": "*"` to `composer.json`'s `require` block, so `composer install` fails fast with a clear message on a host missing it, instead of a runtime fatal error the first time this service runs.
2. Flag the gap to whoever maintains the yaffa.cc documentation site (not part of this repo — no local mirror of that requirements list was found in `README.md`, `UPGRADE.md`, or a dedicated `INSTALL.md`) so "BCMath PHP Extension" gets added to the published required-extensions list.

### FR-2: Align `investment_prices.price` / `transaction_details_investment.price` scale — Phase 0 — ✅ Completed

Add a migration that changes `transaction_details_investment.price` from `decimal(10,4)` to `decimal(20,10)`, matching `investment_prices.price`'s existing scale, so the same logical value (an investment's price at a point in time) is stored at the same precision regardless of which table it appears in. Implement a reversible `down()` per `.ai/agents/laravel-backend.agent.md`'s migration rules ("migrations must be reversible," "no destructive changes without confirmation"). The `up()` widening itself is non-destructive — no existing value can fail to fit a larger scale. The `down()` narrowing is not unconditionally safe, though: any row written after the widening with more than four fractional digits, or a magnitude beyond `decimal(10,4)`'s range, would silently lose precision or overflow on rollback, so `down()` must validate the current data fits `decimal(10,4)` before running the narrowing `ALTER TABLE`, and abort otherwise. No application code depends on the current narrower scale in a way that would break from widening it; confirm this by grepping for `transaction_details_investment.*price` usage before writing the migration.

Align the FormRequest validating this field with its sibling: `app/Http/Requests/TransactionRequest.php:393`'s `getInvestmentAmountRules()` currently validates the BUY/SELL `config.price` field as bare `'required|numeric|gt:0'`, with no upper-bound/scale check at all, unlike `app/Http/Requests/InvestmentPriceRequest.php:23-29` (which validates `investment_prices.price` and already carries an explicit `min:0.0000000001|max:9999999999.9999999999` pair with a `// Fit in signed DECIMAL(20,10) range` comment). Add the identical bound and comment to `TransactionRequest.php`'s `config.price` rule, now that both columns share the same scale — this doesn't fix a break (the looser rule was never wrong), it closes a validation-consistency gap between two request classes validating the same logical value.

### FR-3: Replace `round2()` with `decimal.js` — Phase 0, dependency-promotion approved — ✅ Completed

Replace `round2()` in `resources/js/reports/components/find-transactions/helpers.js:46-55` with a `decimal.js`-based rounding call at its five call sites inside `processCategoryGroup()` (lines 200-216). Promote `decimal.js` from a transitive dependency (currently resolved only via `mathjs`) to a direct entry in `package.json` — **approved**. No new package is downloaded; the same version already exists in `package-lock.json`, only the direct declaration is new.

### FR-4: `MoneyCast`/`DecimalCast` and first two field groups — Phase 1, dependencies approved (`brick/math`, `brick/money`) — ✅ Completed

Introduce `brick/math` and `brick/money` via Composer — **approved**. Add a custom Eloquent cast (e.g. `app/Casts/MoneyCast.php`) implementing both `Illuminate\Contracts\Database\Eloquent\CastsAttributes` (so PHP code receives a `Brick\Math\BigDecimal`/`Brick\Money\Money` instance instead of a float) and `Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes` (so `toArray()`/`toJson()` emit a decimal string, not a JSON number — see background.md's "Wire format" section for why this is sufficient without a Resource-layer rewrite). Apply it first to `transaction_items.amount` and `transaction_details_standard.amount_from`/`amount_to` — the split/allocation-prone fields where Phase 0 already proved real drift exists.

**Dependency footprint.** Neither library hard-requires a new PHP extension: `brick/math`'s `composer.json` requires only `php: ^8.2` and ships a pure-PHP fallback calculator, auto-upgrading to a faster `BcMathCalculator` now that FR-1 already requires `ext-bcmath` — a free synergy, not an extra ask. `brick/money` additionally pulls in `psr/simple-cache` (a small new indirect Composer package, not an extension) and requires `brick/math`. `Money::formatTo()` (locale-aware formatting) depends on `ext-intl` at call time, but **this cast must never call it** — YAFFA's display formatting is already fully centralized in `resources/js/shared/lib/i18n/format.js` on the frontend (background.md), so backend `Money` usage stays scoped to arithmetic and `SerializesCastableAttributes`-driven serialization only. This avoids introducing an undocumented `ext-intl` runtime dependency for no functional gain.

### FR-5: Extend the cast to investment and currency fields — Phase 2, dependencies approved — ✅ Completed

Extend `MoneyCast` to `transaction_details_investment.price/commission/tax/dividend` and `investment_prices.price` — all genuine currency amounts. `transaction_details_investment.quantity` (a share count) and `CurrencyRate.rate` (a currency-to-currency ratio) are not themselves currency amounts, so both use `DecimalCast`/`brick/math`'s `BigDecimal` directly rather than `Money`.

Use `RoundingMode::HALF_UP` for the currency-conversion arithmetic — **decided**. This only affects derived, display-oriented base-currency conversions (`amount_to_base`/`amount_from_base`/`amount_in_base`, never the stored source-of-truth transaction amount), so the two candidate modes can only ever diverge by one unit at the smallest configured decimal place, on values landing exactly on a tie — imperceptible at YAFFA's per-transaction scale, and neither mode is more "correct" given the product's standing disclaimer that reports aren't "exact accounting precision." `HALF_UP` is chosen because it matches how everyday calculators and non-accounting software round, which is what a personal-finance app's users expect; `HALF_EVEN` (banker's rounding) exists to cancel statistical bias across millions of roundings in ledger-scale accounting systems, which doesn't apply here.

Correct the arithmetic's location while implementing this: the actual multiplication happens in `app/Http/Controllers/API/TransactionApiController.php:424,427,433` (`$transaction->config->amount_to_base = $transaction->config->amount_to * $transaction->currencyRateToBase;` and the equivalent `amount_from_base`/`item->amount_in_base` lines), not in `app/Http/Traits/CurrencyTrait.php` — that trait only resolves the applicable rate (`getLatestRateFromMap()`); it never applies it. `brick/money`'s `Money::convertedTo($currency, $exchangeRate, roundingMode: RoundingMode::HalfUp)` is a direct fit for these three call sites, replacing plain `*` with an explicit, currency-aware, rounded conversion.

A second concrete illustrative site: `app/Services/TransactionService.php`'s `getInvestmentConfigCashFlow()` (`~lines 144-167`) computes `$transaction->transaction_type->amountMultiplier() * $config->price * $config->quantity + $config->dividend - $config->tax - $config->commission` in raw floats — once `price`/`dividend`/`tax`/`commission` are `Money` and `quantity` is `BigDecimal`, this becomes `$price->multipliedBy($quantity, RoundingMode::HalfUp)->plus($dividend)->minus($tax)->minus($commission)` — the explicit rounding mode is required here because `Money::multipliedBy()` defaults to `RoundingMode::Unnecessary` and throws if multiplying by an arbitrary-scale `BigDecimal` quantity doesn't land exactly on the `Money`'s own scale — gaining an automatic currency-mismatch guard (an accidental cross-currency mix throws instead of silently producing a wrong total) alongside exact arithmetic. Its result still feeds `cashflow_value`, which itself is not migrated until Phase 4 (FR-7) — this FR fixes the inputs, FR-7 fixes where they're written to.

### FR-6: Frontend decimal adoption — Phase 3, builds on FR-3's direct `decimal.js` dependency — ✅ Completed

Clamp `MathInput.vue`'s emitted value (`resources/js/shared/ui/form/MathInput.vue:40,56`) to the relevant field's expected precision, finally using `Currency.generic_decimal_precision`/`detailed_decimal_precision` for more than display (background.md notes these fields are currently display-only). Extend `decimal.js` usage to `TransactionItemContainer.vue`'s allocation path (replacing the `toFixed(4)`/remainder-reconciliation workaround at lines 404-436), `TransactionFormStandard.vue`'s `allocatedAmount`/`remainingAmount*` computed properties (lines 802-827), and the API-response parsing layer, which must start parsing FR-4's decimal-string JSON output instead of assuming a JSON number.

### FR-7: Migrate materialized caches onto the new arithmetic path — Phase 4 — ✅ Completed

Move `transactions.cashflow_value`'s write path (`TransactionService::getTransactionCashFlow()`, called from `app/Listeners/ProcessTransactionCreated.php:23` and `ProcessTransactionUpdated.php:38`) and `account_monthly_summaries.amount`'s write path (`CalculateAccountMonthlySummary` job, `TransactionService::recalculateMonthlySummaries()`) onto the cast/decimal-library arithmetic established in Phases 1-2, so drift cannot re-enter through these denormalized, cron- and event-rebuilt caches after everything upstream has been fixed.

Concrete illustrative site: `app/Models/AccountMonthlySummary.php`'s `calculateAccountBalanceFact()` (lines 82-132) sums three independently-computed float components — `return $valueInvestment + $valueTo - $valueFrom;` (line 132) — into the balance written for every account, every month, on the daily 05:00 cron (`routes/console.php:36`). This is exactly the repeated-summation drift pattern `AMOUNT_COMPARISON_EPSILON` was invented to tolerate (FR-1), just one layer further downstream; combining these three already-DECIMAL SQL sums via `BigDecimal` (not `Money` — all three are already denominated in the account's own currency, so this step performs no FX conversion) removes the same bug class at the point where it currently compounds the most. The account's currency is attached only later, when `amount` is read back through `AccountMonthlySummary`'s `MoneyCast` (`resolveAmountCurrency()`); the bulk `insert()` this method's caller uses (`CalculateAccountMonthlySummary`) writes the `BigDecimal` result as a plain decimal string and bypasses the cast entirely (see the `AccountMonthlySummary::insert()` note below).

**FR-7 follow-up — investment valuation (`quantity × price`) — ✅ Completed.** The initial Phase 4 pass left `AccountMonthlySummary::calculateInvestmentValueFact()` and `CalculateAccountMonthlySummary::getInvestmentValueForecastData()` on native float arithmetic, reasoning that quantity × price is investment *valuation*, not the cost-basis/realized-gain *calculation* the Non-Goals section excludes. On review that reasoning was too broad: the Non-Goal is about cost-basis/gain math that doesn't exist in the codebase, not about the precision of a valuation multiplication that already exists and already writes to the same `account_monthly_summaries.amount` column FR-7 targets. Both call sites are now migrated:

- `App\Services\InvestmentService` gained `getLatestPriceExact()` and `getLatestPricesBatchExact()`, `BigDecimal`-returning counterparts of `getLatestPrice()`/`getLatestPricesBatch()` (which now delegate to them and collapse to `float` only at their own return boundary, for the display-only consumers that were already calling them — `InvestmentController`, `InvestmentApiController`). `resolveCombinedPrice()`/`extractTransactionPrice()`/`getLatestCombinedPrice()` were changed to compute `BigDecimal` internally instead of calling `MoneyCast::toFloat()`, removing several of that helper's deliberate "not-yet-migrated consumer" warning-log call sites in the process.
- `AccountMonthlySummary::calculateInvestmentValueFact()` now returns `BigDecimal`, multiplying each investment's exact quantity (`BigDecimal::of()` on the raw SQL `SUM()` string) by `getLatestPriceExact()`'s result and summing with `plus()`, instead of native `sum()`/`*`.
- `CalculateAccountMonthlySummary::getInvestmentValueForecastData()` no longer calls `DecimalCast::toFloat()` on `TransactionDetailInvestment::quantity` (already `BigDecimal`-cast per FR-5) — it multiplies the `BigDecimal` quantity directly by `getLatestPricesBatchExact()`'s `BigDecimal` price map, via a new `sumBigDecimal()` helper alongside the job's existing `sumMoney()`.
- Both `handleInvestmentValueFact()`/`handleInvestmentValueForecast()`'s `insert()` calls now receive `(string) $amount` (an exact decimal string), matching the pattern the job's other three `get*Data()` methods already used — closing the gap noted in this FR: `AccountMonthlySummary::insert()` is a query-builder passthrough that bypasses `MoneyCast` entirely (Eloquent's static `insert()` never hydrates a model instance), so a `BigDecimal` result still has to be explicitly stringified before the bulk insert or it silently degrades back to a float at that boundary.

`float` remains at exactly two points in this path, both deliberate: `InvestmentService::getLatestPrice()`/`getLatestPricesBatch()`'s own return type (display-only callers, per above) and `Investment::getCurrentQuantity()`/`enrichInvestmentWithQuantityHistory()` (unrelated display/chart-history endpoints, out of scope for this follow-up).

**FR-7 follow-up — report aggregation in `ReportApiController` — ✅ Completed.** background.md names "report aggregation" alongside transaction-item allocation and monthly summary recomputation as one of the proven repeated-summation drift risks (not speculative), but `app/Http/Controllers/API/ReportApiController.php` had not yet been touched by any phase. Three report endpoints accumulated money via plain float `+=` across many rows/transactions before this follow-up:

- `getCategoryWaterfallData()` (FR-7's originally-flagged site) — the per-category bucket in both the standard-transaction and investment-transaction loops.
- `budgetChart()` — `$standardCompact[period][currency]`, `$dataByPeriod[period]['actual'/'budget']`, the per-budget-transaction item sum, and `$budgetCompact[period][currency]`. `'actual'`'s `null`-vs-`BigDecimal::zero()` distinction was preserved (a period with zero standard transactions must stay `null`, not `0` — `budgetchart.js` relies on this to find the last period with real data).
- `getCashflowData()` — `$compact[month][transaction_type]` and the cross-month `$runningTotal` (a running total across a user's entire history is exactly the kind of long summation chain most prone to float drift).

All three now accumulate via `BigDecimal`, collapsing to `float` only once, immediately before each `response()->json()` call — the same "exact accumulation, `Number` only at the chart-consumption boundary" rule Phase 3 established for `MonthlyTimeline.vue` on the frontend, applied here on the backend side. The currency-rate multiplication itself (`$value->multipliedBy((string) ($rate ?? 1))`) stays on the `BigDecimal` path rather than being upgraded to `Money::convertedTo()`/an explicit rounding mode: `$rate` comes from `allCurrencyRatesByMonth()`'s `AVG(rate)` SQL aggregate, already an inexact monthly average, so a rounding mode at that step wouldn't add anything — matching background.md's "modest risk, not correctness-critical" framing for conversion rounding, as distinct from its "real, already-proven risk" framing for summation drift. A pre-existing, unrelated latent bug was found and deliberately left alone: `getCashflowData()`'s `if ($summary->amount === 0)` guard compares a raw SQL `SUM()` result (a numeric string) to an `int` with `===`, so it never actually fires; fixing it would change which zero-valued months appear in `chartData`, a distinct behavior change outside this follow-up's scope.

## 5. Data Model Changes

- **Phase 0**: `transaction_details_investment.price` widened from `decimal(10,4)` to `decimal(20,10)` (FR-2). This is the only schema change in this document.
- **Phase 1+**: no further column-type changes are needed. `MoneyCast` reads the existing `DECIMAL` columns' string representation as PDO already returns them (absent a cast, a `DECIMAL` column comes back from the query as a string, not a pre-converted float) — only the PHP-side attribute type and JSON serialization shape change, not the underlying storage.

## 6. Backend Components to Update

**Phase 0 (this document's implementation-ready scope):**

- `app/Services/TransactionItemMergeService.php` — replace the epsilon comparison with `bccomp()` (FR-1).
- `composer.json` — add `"ext-bcmath": "*"` to `require` (FR-1).
- New migration for `transaction_details_investment.price` (FR-2).
- `app/Http/Requests/TransactionRequest.php` — add the `min`/`max` DECIMAL(20,10)-range bound to `getInvestmentAmountRules()`'s `config.price` rule, matching `InvestmentPriceRequest.php` (FR-2).
- `tests/Unit/Services/TransactionItemMergeServiceTest.php` (or equivalent existing test file) — add/update coverage for the exact-comparison change.

**Phase 1-2 (dependencies approved — `brick/math`, `brick/money`):**

- `composer.json` — add `brick/math`, `brick/money` (FR-4; pulls in `psr/simple-cache` transitively — no extension, no action needed).
- `app/Casts/MoneyCast.php` (new) — `CastsAttributes` + `SerializesCastableAttributes`; must never call `Money::formatTo()` (FR-4 — keeps `ext-intl` out of the runtime requirement list).
- `app/Models/TransactionItem.php` (`amount`), `app/Models/TransactionDetailStandard.php` (`amount_from`, `amount_to`) — first casts to convert (FR-4).
- `app/Models/TransactionDetailInvestment.php` (`price`, `commission`, `tax`, `dividend`), `app/Models/InvestmentPrice.php` (`price`) — `Money`-cast second wave; `app/Models/TransactionDetailInvestment.php` (`quantity`) and `app/Models/CurrencyRate.php` (`rate`) — `BigDecimal`-cast, not `Money`, since neither is itself a currency amount (FR-5).
- `app/Http/Controllers/API/TransactionApiController.php:424,427,433` — replace `amount * currencyRateToBase` with `Money::convertedTo()` and an explicit rounding mode (FR-5).
- `app/Services/TransactionService.php` (`getInvestmentConfigCashFlow()`) — convert to `Money`/`BigDecimal` arithmetic, feeding Phase 4's `cashflow_value` migration (FR-5, consumed by FR-7).

**Phase 4 (roadmap detail, depends on Phase 1-2's casts landing first):**

- `app/Services/TransactionService.php`, `app/Jobs/CalculateAccountMonthlySummary.php`, `app/Models/AccountMonthlySummary.php` (`calculateAccountBalanceFact()`) — migrate `cashflow_value`/`account_monthly_summaries` write paths (FR-7).
- Remaining models still on a blanket `'float'` cast for a money/quantity field (`app/Models/Account.php` — `opening_balance`; `app/Models/Transaction.php` — `cashflow_value`; `app/Models/AccountMonthlySummary.php` — `amount`) convert alongside FR-7, since they're part of the same cache-rebuild path.
- **FR-7 follow-up (investment valuation):** `app/Services/InvestmentService.php` (`getLatestPriceExact()`/`getLatestPricesBatchExact()` added; `resolveCombinedPrice()`/`extractTransactionPrice()`/`getLatestCombinedPrice()` now compute `BigDecimal`), `app/Models/AccountMonthlySummary.php` (`calculateInvestmentValueFact()` now returns `BigDecimal`), `app/Jobs/CalculateAccountMonthlySummary.php` (`getInvestmentValueFactData()`/`getInvestmentValueForecastData()` — exact quantity × price, new `sumBigDecimal()` helper, `(string) $amount` before `insert()`).
- **FR-7 follow-up (report aggregation):** `app/Http/Controllers/API/ReportApiController.php` (`getCategoryWaterfallData()`, `budgetChart()`, `getCashflowData()` — all category/period/month accumulators now `BigDecimal`, collapsed to `float` only at each `response()->json()` boundary), `app/Models/Transaction.php` (`$sum` dynamic-property docblock changed from `float|null` to `BigDecimal|null`, matching `budgetChart()`'s now-exact per-transaction item sum).

## 7. Frontend Components to Update

**Phase 0:**

- `resources/js/reports/components/find-transactions/helpers.js` — replace `round2()` with `decimal.js` rounding (FR-3).
- `package.json` — promote `decimal.js` to a direct dependency (FR-3, approved).

**Phase 3 (dependency approved — `decimal.js`):**

- `resources/js/shared/ui/form/MathInput.vue` — clamp emitted value to field precision (FR-6).
- `resources/js/transactions/components/TransactionItemContainer.vue` — replace `toFixed(4)`/remainder workaround with `decimal.js` (FR-6).
- `resources/js/transactions/components/form/TransactionFormStandard.vue` — allocation/remainder computed properties (FR-6).
- API-response parsing layer (wherever transaction/investment JSON responses are consumed) — parse decimal-string fields into `Decimal` instances instead of assuming a JSON number (FR-6).
- `resources/js/transactions/components/form/TransactionFormInvestment.vue`, `resources/js/investments/components/ResultsCard.vue`, `resources/js/reports/components/widgets/MonthlyTimeline.vue` — convert remaining raw-float arithmetic sites identified in background.md, once the fields they read are backed by FR-5's cast.

## 8. Testing Requirements

Per `.ai/agents/testing.agent.md`:

### Backend Tests (Phase 0)

- Unit test for the `bccomp()`-based comparison in `TransactionItemMergeService`: confirm it rejects a genuinely unequal merge (correctness preserved from the old epsilon check) and confirm it does not itself introduce a false positive/negative at the boundary the old `0.0001` tolerance used to paper over.
- Feature test covering the transaction-item split/merge flow end-to-end, asserting no regression in the existing merge behavior.
- Test for the new migration: confirm `transaction_details_investment.price` accepts a value with 10 decimal places without truncation after the scale change.
- Test for `TransactionRequest`'s updated `config.price` rule: confirm a value within the new `DECIMAL(20,10)` range passes and a value exceeding `9999999999.9999999999` fails validation, mirroring the existing `InvestmentPriceRequest` coverage.

### Backend Tests (Phase 1-2, approved)

- Unit tests for `MoneyCast`/`DecimalCast`: round-trip correctness (DB string → `BigDecimal`/`Money` → JSON decimal string), and that `toArray()`/`toJson()` emit a string, not a float.
- Unit test confirming `Money`'s currency-mismatch guard: an operation mixing two different currencies (e.g. a corrupted-data scenario) throws rather than silently producing a wrong total.
- Unit test for the `TransactionApiController` conversion rewrite: `Money::convertedTo()` with `RoundingMode::HALF_UP` matches the previous `amount * rate` result for a representative set of rates, plus an explicit tie-breaking case (a conversion landing exactly on a half-unit at the smallest configured decimal place) confirming it rounds away from zero.
- Feature tests asserting API responses for affected endpoints serialize the converted fields as JSON strings post-migration (a deliberate breaking change to the wire format — must be visible in test assertions, not just implied).
- Static/manual check: confirm no call site invokes `Money::formatTo()` (would introduce an undocumented `ext-intl` runtime dependency — FR-4).

### Backend Tests (Phase 4 / FR-7 investment-valuation follow-up)

- `InvestmentServicePriceTest`: `getLatestPriceExact()`/`getLatestPricesBatchExact()` each resolve to the same value as their existing float-returning counterpart (just as `BigDecimal` instead of `float`), including the null-result case (no price found).
- `AccountMonthlySummaryTest`: `calculateInvestmentValueFact()`'s existing assertions were switched from `assertEquals()` (loose float equality) to an exact-string `BigDecimal` comparison, since the method's return type changed from an implicit float/int to `BigDecimal`. That comparison helper was itself found to have a latent bug — see "test-helper precision" below.
- `CalculateAccountMonthlySummaryTest`: the two investment-forecast tests previously asserted with a `0.001` float delta (`assertSummaryAmountEqualsWithDelta()`), tolerating the pre-fix drift. Both were tightened to the exact-match helper (`assertSummaryAmountEquals()`) now that the forecast path is exact end-to-end, and the now-unused delta helper was removed.
- **Test-helper precision finding:** both test files' exact-comparison helpers originally built their expected string via `number_format($expected, $scale, '.', '')`. At `calculateInvestmentValueFact()`'s scale of 14 (quantity's scale 4 + price's scale 10, added by `BigDecimal` multiplication), `number_format()` itself round-trips the expected value through a PHP double and can misrender an exact input like `50` as `"50.00000000000001"` — reintroducing, inside the test helper, the exact float-precision bug class FR-1 through FR-7 exist to eliminate. Both helpers (`AccountMonthlySummaryTest::assertBalanceFactEquals()`, `CalculateAccountMonthlySummaryTest::assertSummaryAmountEquals()`) were changed to build the expected string via `BigDecimal::of((string) $expected)->toScale($scale)` instead, avoiding the double round-trip entirely.

### Backend Tests (Phase 4 / FR-7 report-aggregation follow-up)

- `ReportApiWaterfallTest`, new `ReportApiBudgetChartTest`: each asserts a category/period bucket built from two `0.10`/`0.20` transaction items resolves to exactly `-0.30` (a classic IEEE-754 case — native float `+=` produces `-0.30000000000000004`), confirming the `BigDecimal` accumulation and the float-collapse-at-response-boundary both work end to end, not just internally.
- `ReportApiCashflowTest` (pre-existing, unchanged) continued to pass unmodified against the rewritten `getCashflowData()`, since its assertions already used `assertEqualsWithDelta()`/whole-number fixtures that don't distinguish the fix from the prior float path — no new drift-specific case was added here since the existing running-total test already exercises the accumulation path structurally.

### Manual Verification (Phase 0)

- Manually exercise the transaction-item split/merge flow in the UI and confirm no false "amounts don't match" error appears for values that previously relied on the epsilon tolerance.
- Manually load the Monthly Breakdown report (`find-transactions`) and confirm totals/averages render identically to before the `round2()` replacement.

## 9. Acceptance Criteria

1. `TransactionItemMergeService` no longer contains a float-epsilon tolerance; the comparison is exact at the target scale, using `bcmath` (no new Composer dependency).
2. `investment_prices.price` and `transaction_details_investment.price` share the same decimal scale (`decimal(20,10)`), verified by a passing migration test.
3. `round2()` is removed from `find-transactions/helpers.js` and replaced by `decimal.js`-based rounding.
4. `vendor/bin/sail artisan test --compact` (scoped to `TransactionItemMergeService` and the new migration test), `./vendor/bin/pint --dirty`, and `npx eslint resources/js --ext .js,.vue` (scoped to the changed file) all pass.
5. `brick/math`/`brick/money` (FR-4/FR-5) and `decimal.js` as a direct dependency (FR-3/FR-6) are all approved — no phase in this document is blocked on a dependency decision.
6. `composer.json` declares `"ext-bcmath": "*"`, and no code path calls `Money::formatTo()` — the runtime extension footprint stays limited to `bcmath` (documented per FR-1) with no undocumented `ext-intl` dependency introduced.

## 10. Rollout Plan

This is an **implementation order**. All four phases are unblocked on dependencies — `decimal.js` (FR-3/FR-6) and `brick/math`/`brick/money` (FR-4/FR-5) are all approved — so the ordering below reflects build sequencing (what depends on what compiling/passing tests first), not approval gates.

### Phase 0 — Fix the two proven bugs, zero new dependencies except one direct-dependency promotion — ✅ Completed

**Scope:** FR-1, FR-2, FR-3.
**Depends on:** nothing.
**Dependencies:** `decimal.js` promoted from transitive to direct in `package.json` (FR-3) — approved; FR-1/FR-2 need none, since `bcmath` is already available.
**State at end of phase:** the two already-shipped workarounds are gone, replaced by exact arithmetic; the investment price scale mismatch is fixed. Nothing else in the app changes behavior.
**Shipped:** `TransactionItemMergeService` now compares raw DECIMAL strings via `bccomp()` (FR-1); `transaction_details_investment.price` widened to `decimal(20,10)` with `TransactionRequest`'s `config.price` rule aligned to `InvestmentPriceRequest` (FR-2); `round2()` reimplemented on `decimal.js`, promoted to a direct dependency (FR-3). All Phase 0 backend/frontend tests, Pint, and PHPStan pass.

### Phase 1 — Backend core: `MoneyCast`, first two field groups — ✅ Completed

**Scope:** FR-4.
**Depends on:** Phase 0 (proves the cast approach is worth investing in, on the same fields already known to be risky).
**Dependency approval:** granted — `brick/math`, `brick/money` approved via Composer (`ext-bcmath` already required by Phase 0; no `ext-intl` dependency, per FR-4's `formatTo()` constraint).
**State at end of phase:** `transaction_items.amount` and `transaction_details_standard.amount_from`/`amount_to` are backed by exact decimal arithmetic in PHP and serialize as decimal strings over the API. No other model has changed.
**Shipped:** `app/Casts/MoneyCast.php` and `app/Casts/DecimalCast.php` (`CastsAttributes` + `SerializesCastableAttributes`); applied to `TransactionItem::amount` and `TransactionDetailStandard::amount_from`/`amount_to`, each resolving its currency via a per-model resolver method (`resolveAmountCurrency()`, `resolveAmountFromCurrency()`/`resolveAmountToCurrency()`) rather than a fixed relation path, since a standard transaction's two sides can be denominated in different currencies (transfers) or mirror each other (withdrawal/deposit, where one side is a payee with no currency of its own). Every real call site this broke across `app/` (not just the sites FR-4/FR-5 named as illustrative) was updated to unwrap via `MoneyCast::toFloat()`/`DecimalCast::toFloat()` or to adopt exact arithmetic, per Goal 5 ("a phase must not require a later phase's code to compile").

### Phase 2 — Investment and currency fields — ✅ Completed

**Scope:** FR-5.
**Depends on:** Phase 1 (reuses `MoneyCast`).
**Dependency approval:** none beyond Phase 1's (same, already-approved libraries, more fields and call sites — `TransactionApiController`'s conversion arithmetic, `TransactionService::getInvestmentConfigCashFlow()`).
**State at end of phase:** investment price/quantity/commission/tax/dividend fields and `CurrencyRate.rate` are exact; currency conversion in `TransactionApiController` has an explicit, documented rounding mode via `Money::convertedTo()`.
**Shipped:** `MoneyCast` applied to `TransactionDetailInvestment::price/commission/tax/dividend` and `InvestmentPrice::price`; `DecimalCast` applied to `TransactionDetailInvestment::quantity` and `CurrencyRate::rate`. `TransactionApiController::convertToBase()` replaces the three `amount * currencyRateToBase` sites with `Money::convertedTo(..., RoundingMode::HalfUp)`. `TransactionService::getInvestmentConfigCashFlow()` now composes `Money`/`BigDecimal` arithmetic (gaining the currency-mismatch guard FR-5 describes) before unwrapping to a float at the `cashflow_value` boundary, which stays float-cast until Phase 4. Two pre-existing, previously-latent test-infrastructure bugs surfaced and were fixed along the way: `ModelOwnedByUserTrait` was silently overriding an explicitly-set `user_id` whenever any user was authenticated (breaking factory `.for($otherUser)` calls), and `TransactionFactory`'s `withdrawal()`/`deposit()`/`transfer()`/investment state methods never bound the transaction's own `user_id` to the `$user` they otherwise scope everything else to.

### Phase 3 — Frontend adoption — ✅ Completed

**Scope:** FR-6.
**Depends on:** Phase 0 (direct `decimal.js` dependency present) and Phase 1 (decimal-string API responses to parse).
**Dependencies:** same as Phase 0's FR-3 — `decimal.js` as a direct dependency — approved.
**State at end of phase:** `MathInput.vue` clamps to field precision; the allocation/remainder call sites and API-response parsing use `decimal.js` instead of raw floats.
**Shipped:** `MathInput.vue` gained a `precision` prop (a decimal place count) that clamps the `mathjs`-evaluated result via `decimal.js` before emitting; a new `getDecimalPrecision(currencySettings, 'generic'|'detailed')` helper in `format.js` resolves it from a `Currency`'s `generic_decimal_precision`/`detailed_decimal_precision`, threaded down to every `MathInput` usage that has a currency to clamp against (`TransactionFormStandard.vue`'s amount_from/amount_to, `TransactionItem.vue`'s item amount via a new `precision` prop on `TransactionItemContainer.vue`/`TransactionItem.vue`, and `TransactionFormInvestment.vue`'s price/commission/tax/dividend — `quantity` is left unclamped, since a share count isn't a currency amount). `TransactionItemContainer.vue`'s `buildItemsFromStats()` allocation/remainder-reconciliation and `TransactionFormStandard.vue`'s `allocatedAmount`/`remainingAmountToPayeeDefault`/`remainingAmountNotAllocated` now compose `Decimal` operations instead of the previous `toFixed(4)`/plain-float arithmetic. `processTransaction()` (`resources/js/shared/lib/helpers/index.js`) — the shared point every transaction API response passes through (report/import/account/schedule/form consumers alike) — now normalizes `cashflow_value`, item `amount`, and `config.amount_from`/`amount_to`/`price`/`quantity`/`commission`/`tax`/`dividend` from FR-4/FR-5's decimal-string wire format back to plain JS numbers. This also fixed two live bugs that Phase 1/2/4's decimal-string serialization had silently introduced ahead of this phase: `TransactionFormInvestment.vue`'s `total` computed and `ResultsCard.vue`'s investment-ROI `summary()` both used native `+` across these now-string fields, which JS string-concatenates instead of adding — both rewritten on `Decimal`. `MonthlyTimeline.vue`'s monthly cashflow aggregation and `TransactionFormInvestment.vue`'s "existing price for date" display value were also moved onto `Decimal`/`Number`-safe handling of the same wire format, converting back to plain `Number` only at the chart/display boundary (amCharts and `toFormattedCurrency()` both require a native `Number`, per background.md's chart-boundary guidance).

### Phase 4 — Materialized caches — ✅ Completed

**Scope:** FR-7.
**Depends on:** Phases 1-2 (the arithmetic these caches call into must already be exact).
**Dependency approval needed:** none beyond what's already been approved by this point.
**State at end of phase:** `cashflow_value` and `account_monthly_summaries` are computed via the same exact-decimal path as everything upstream of them — drift can no longer re-enter through denormalized data.
**Shipped:** `MoneyCast` applied to `Account::opening_balance` (own currency), `Transaction::cashflow_value` (the transaction's own currency, reusing `transaction_currency`'s base-currency fallback), and `AccountMonthlySummary::amount` (the account's currency when `account_entity_id` is set, else the user's base currency for generic budgets). `TransactionService::getTransactionCashFlow()` now returns `Money` end-to-end (previously unwrapped to float internally even after FR-5). `AccountMonthlySummary::calculateAccountBalanceFact()` combines its three raw SQL `SUM()` results via `BigDecimal` instead of native float `+`/`-`. `CalculateAccountMonthlySummary`'s forecast and budget paths gained a `sumMoney()` helper to combine Money-cast Collection values exactly, replacing the `Collection::sum('cashflow_value')` string-key form that can't handle Money objects at all. `AccountMonthlySummary::insert()` (bulk, cast-bypassing) still receives plain decimal strings, unchanged. Investment valuation (`quantity × price`) was initially left on its existing float path — reasoned at the time to be covered by the Non-Goals note on investment cost-basis/valuation — but that call was revisited and migrated in the FR-7 follow-up above: it's a valuation multiplication already writing to `account_monthly_summaries.amount`, not the cost-basis/gain calculation the Non-Goal actually excludes.
