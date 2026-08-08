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

### FR-1: Exact decimal comparison in `TransactionItemMergeService` — Phase 0, no new dependency

Replace the float-tolerance guard in `app/Services/TransactionItemMergeService.php:11,98` (`AMOUNT_COMPARISON_EPSILON = 0.0001`, `abs($originalTotal - $newTotal) > self::AMOUNT_COMPARISON_EPSILON`) with an exact comparison using PHP's `bcmath` extension (`bccomp($originalTotal, $newTotal, $scale)` at a fixed scale matching `transaction_items.amount`'s column precision). `ext-bcmath` is already compiled into the local dev image (`vendor/laravel/sail/runtimes/8.4/Dockerfile:46`), so this requires no `composer require` and no dependency-approval gate. The two summed values being compared should be built from the raw string amounts (as read from the `DECIMAL` column) rather than from the float-cast model attribute, since casting to float before comparing would reintroduce the exact drift this FR removes.

**New runtime requirement to declare and document.** `ext-bcmath` isn't in YAFFA's currently-published required-extensions list ([yaffa.cc/documentation/getting-started/installation/technology/](https://yaffa.cc/documentation/getting-started/installation/technology/) lists 13 extensions — Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML — `bcmath` is not among them). It's present in the Sail dev image but that's not guaranteed on a self-hosted deployment running its own PHP stack. Two actions, both in scope for this FR:

1. Add `"ext-bcmath": "*"` to `composer.json`'s `require` block, so `composer install` fails fast with a clear message on a host missing it, instead of a runtime fatal error the first time this service runs.
2. Flag the gap to whoever maintains the yaffa.cc documentation site (not part of this repo — no local mirror of that requirements list was found in `README.md`, `UPGRADE.md`, or a dedicated `INSTALL.md`) so "BCMath PHP Extension" gets added to the published required-extensions list.

### FR-2: Align `investment_prices.price` / `transaction_details_investment.price` scale — Phase 0

Add a migration that changes `transaction_details_investment.price` from `decimal(10,4)` to `decimal(20,10)`, matching `investment_prices.price`'s existing scale, so the same logical value (an investment's price at a point in time) is stored at the same precision regardless of which table it appears in. Implement a reversible `down()` per `.ai/agents/laravel-backend.agent.md`'s migration rules ("migrations must be reversible," "no destructive changes without confirmation" — widening a decimal column's scale is non-destructive and safe to reverse). No application code depends on the current narrower scale in a way that would break from widening it; confirm this by grepping for `transaction_details_investment.*price` usage before writing the migration.

Align the FormRequest validating this field with its sibling: `app/Http/Requests/TransactionRequest.php:393`'s `getInvestmentAmountRules()` currently validates the BUY/SELL `config.price` field as bare `'required|numeric|gt:0'`, with no upper-bound/scale check at all, unlike `app/Http/Requests/InvestmentPriceRequest.php:23-29` (which validates `investment_prices.price` and already carries an explicit `min:0.0000000001|max:9999999999.9999999999` pair with a `// Fit in signed DECIMAL(20,10) range` comment). Add the identical bound and comment to `TransactionRequest.php`'s `config.price` rule, now that both columns share the same scale — this doesn't fix a break (the looser rule was never wrong), it closes a validation-consistency gap between two request classes validating the same logical value.

### FR-3: Replace `round2()` with `decimal.js` — Phase 0, dependency-promotion approved

Replace `round2()` in `resources/js/reports/components/find-transactions/helpers.js:46-55` with a `decimal.js`-based rounding call at its five call sites inside `processCategoryGroup()` (lines 200-216). Promote `decimal.js` from a transitive dependency (currently resolved only via `mathjs`) to a direct entry in `package.json` — **approved**. No new package is downloaded; the same version already exists in `package-lock.json`, only the direct declaration is new.

### FR-4: `MoneyCast`/`DecimalCast` and first two field groups — Phase 1, dependencies approved (`brick/math`, `brick/money`)

Introduce `brick/math` and `brick/money` via Composer — **approved**. Add a custom Eloquent cast (e.g. `app/Casts/MoneyCast.php`) implementing both `Illuminate\Contracts\Database\Eloquent\CastsAttributes` (so PHP code receives a `Brick\Math\BigDecimal`/`Brick\Money\Money` instance instead of a float) and `Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes` (so `toArray()`/`toJson()` emit a decimal string, not a JSON number — see background.md's "Wire format" section for why this is sufficient without a Resource-layer rewrite). Apply it first to `transaction_items.amount` and `transaction_details_standard.amount_from`/`amount_to` — the split/allocation-prone fields where Phase 0 already proved real drift exists.

**Dependency footprint.** Neither library hard-requires a new PHP extension: `brick/math`'s `composer.json` requires only `php: ^8.2` and ships a pure-PHP fallback calculator, auto-upgrading to a faster `BcMathCalculator` now that FR-1 already requires `ext-bcmath` — a free synergy, not an extra ask. `brick/money` additionally pulls in `psr/simple-cache` (a small new indirect Composer package, not an extension) and requires `brick/math`. `Money::formatTo()` (locale-aware formatting) depends on `ext-intl` at call time, but **this cast must never call it** — YAFFA's display formatting is already fully centralized in `resources/js/shared/lib/i18n/format.js` on the frontend (background.md), so backend `Money` usage stays scoped to arithmetic and `SerializesCastableAttributes`-driven serialization only. This avoids introducing an undocumented `ext-intl` runtime dependency for no functional gain.

### FR-5: Extend the cast to investment and currency fields — Phase 2, dependencies approved

Extend `MoneyCast` to `transaction_details_investment.price/commission/tax/dividend` and `investment_prices.price` — all genuine currency amounts. `transaction_details_investment.quantity` (a share count) and `CurrencyRate.rate` (a currency-to-currency ratio) are not themselves currency amounts, so both use `DecimalCast`/`brick/math`'s `BigDecimal` directly rather than `Money`.

Use `RoundingMode::HALF_UP` for the currency-conversion arithmetic — **decided**. This only affects derived, display-oriented base-currency conversions (`amount_to_base`/`amount_from_base`/`amount_in_base`, never the stored source-of-truth transaction amount), so the two candidate modes can only ever diverge by one unit at the smallest configured decimal place, on values landing exactly on a tie — imperceptible at YAFFA's per-transaction scale, and neither mode is more "correct" given the product's standing disclaimer that reports aren't "exact accounting precision." `HALF_UP` is chosen because it matches how everyday calculators and non-accounting software round, which is what a personal-finance app's users expect; `HALF_EVEN` (banker's rounding) exists to cancel statistical bias across millions of roundings in ledger-scale accounting systems, which doesn't apply here.

Correct the arithmetic's location while implementing this: the actual multiplication happens in `app/Http/Controllers/API/TransactionApiController.php:424,427,433` (`$transaction->config->amount_to_base = $transaction->config->amount_to * $transaction->currencyRateToBase;` and the equivalent `amount_from_base`/`item->amount_in_base` lines), not in `app/Http/Traits/CurrencyTrait.php` — that trait only resolves the applicable rate (`getLatestRateFromMap()`); it never applies it. `brick/money`'s `Money::convertedTo($currency, $exchangeRate, RoundingMode::HALF_UP)` is a direct fit for these three call sites, replacing plain `*` with an explicit, currency-aware, rounded conversion.

A second concrete illustrative site: `app/Services/TransactionService.php`'s `getInvestmentConfigCashFlow()` (`~lines 144-167`) computes `$transaction->transaction_type->amountMultiplier() * $config->price * $config->quantity + $config->dividend - $config->tax - $config->commission` in raw floats — once `price`/`dividend`/`tax`/`commission` are `Money` and `quantity` is `BigDecimal`, this becomes `$price->multipliedBy($quantity)->plus($dividend)->minus($tax)->minus($commission)`, gaining an automatic currency-mismatch guard (an accidental cross-currency mix throws instead of silently producing a wrong total) alongside exact arithmetic. Its result still feeds `cashflow_value`, which itself is not migrated until Phase 4 (FR-7) — this FR fixes the inputs, FR-7 fixes where they're written to.

### FR-6: Frontend decimal adoption — Phase 3, builds on FR-3's direct `decimal.js` dependency

Clamp `MathInput.vue`'s emitted value (`resources/js/shared/ui/form/MathInput.vue:40,56`) to the relevant field's expected precision, finally using `Currency.generic_decimal_precision`/`detailed_decimal_precision` for more than display (background.md notes these fields are currently display-only). Extend `decimal.js` usage to `TransactionItemContainer.vue`'s allocation path (replacing the `toFixed(4)`/remainder-reconciliation workaround at lines 404-436), `TransactionFormStandard.vue`'s `allocatedAmount`/`remainingAmount*` computed properties (lines 802-827), and the API-response parsing layer, which must start parsing FR-4's decimal-string JSON output instead of assuming a JSON number.

### FR-7: Migrate materialized caches onto the new arithmetic path — Phase 4

Move `transactions.cashflow_value`'s write path (`TransactionService::getTransactionCashFlow()`, called from `app/Listeners/ProcessTransactionCreated.php:23` and `ProcessTransactionUpdated.php:38`) and `account_monthly_summaries.amount`'s write path (`CalculateAccountMonthlySummary` job, `TransactionService::recalculateMonthlySummaries()`) onto the cast/decimal-library arithmetic established in Phases 1-2, so drift cannot re-enter through these denormalized, cron- and event-rebuilt caches after everything upstream has been fixed.

Concrete illustrative site: `app/Models/AccountMonthlySummary.php`'s `calculateAccountBalanceFact()` (lines 82-132) sums three independently-computed float components — `return $valueInvestment + $valueTo - $valueFrom;` (line 132) — into the balance written for every account, every month, on the daily 05:00 cron (`routes/console.php:36`). This is exactly the repeated-summation drift pattern `AMOUNT_COMPARISON_EPSILON` was invented to tolerate (FR-1), just one layer further downstream; converting these three values to `Money` before summing removes the same bug class at the point where it currently compounds the most.

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

### Phase 0 — Fix the two proven bugs, zero new dependencies except one direct-dependency promotion

**Scope:** FR-1, FR-2, FR-3.
**Depends on:** nothing.
**Dependencies:** `decimal.js` promoted from transitive to direct in `package.json` (FR-3) — approved; FR-1/FR-2 need none, since `bcmath` is already available.
**State at end of phase:** the two already-shipped workarounds are gone, replaced by exact arithmetic; the investment price scale mismatch is fixed. Nothing else in the app changes behavior.

### Phase 1 — Backend core: `MoneyCast`, first two field groups

**Scope:** FR-4.
**Depends on:** Phase 0 (proves the cast approach is worth investing in, on the same fields already known to be risky).
**Dependency approval:** granted — `brick/math`, `brick/money` approved via Composer (`ext-bcmath` already required by Phase 0; no `ext-intl` dependency, per FR-4's `formatTo()` constraint).
**State at end of phase:** `transaction_items.amount` and `transaction_details_standard.amount_from`/`amount_to` are backed by exact decimal arithmetic in PHP and serialize as decimal strings over the API. No other model has changed.

### Phase 2 — Investment and currency fields

**Scope:** FR-5.
**Depends on:** Phase 1 (reuses `MoneyCast`).
**Dependency approval:** none beyond Phase 1's (same, already-approved libraries, more fields and call sites — `TransactionApiController`'s conversion arithmetic, `TransactionService::getInvestmentConfigCashFlow()`).
**State at end of phase:** investment price/quantity/commission/tax/dividend fields and `CurrencyRate.rate` are exact; currency conversion in `TransactionApiController` has an explicit, documented rounding mode via `Money::convertedTo()`.

### Phase 3 — Frontend adoption

**Scope:** FR-6.
**Depends on:** Phase 0 (direct `decimal.js` dependency present) and Phase 1 (decimal-string API responses to parse).
**Dependencies:** same as Phase 0's FR-3 — `decimal.js` as a direct dependency — approved.
**State at end of phase:** `MathInput.vue` clamps to field precision; the allocation/remainder call sites and API-response parsing use `decimal.js` instead of raw floats.

### Phase 4 — Materialized caches

**Scope:** FR-7.
**Depends on:** Phases 1-2 (the arithmetic these caches call into must already be exact).
**Dependency approval needed:** none beyond what's already been approved by this point.
**State at end of phase:** `cashflow_value` and `account_monthly_summaries` are computed via the same exact-decimal path as everything upstream of them — drift can no longer re-enter through denormalized data.
