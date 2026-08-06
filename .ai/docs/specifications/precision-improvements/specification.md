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

- A full backend/frontend BigDecimal migration delivered as one change. Phases 1-4 are scoped and sequenced but not committed to a single release; each is written at roadmap detail, not full implementation detail, because each introduces a new dependency that requires explicit user approval before work starts (`brick/math`/`brick/money` in Phase 1, promoting `decimal.js` to a direct npm dependency in Phase 0/3) — this document flags where approval is needed but cannot grant it.
- FX rate staleness/reconciliation between a transfer's implied rate and the `CurrencyRate` table — an existing, deliberate product decision, not a precision bug (see background.md).
- Investment cost-basis/realized-gain calculation — does not exist in the codebase yet, so there is nothing here to harden.
- Any change to `resources/js/shared/lib/i18n/format.js`'s display formatting — it is already correctly centralized and reused; this work changes what value is computed and stored, not how a correct value is displayed.
- Performance work. Arbitrary-precision arithmetic is not a speed improvement (background.md); no phase here should be justified or measured on that basis.

## 4. Functional Requirements

### FR-1: Exact decimal comparison in `TransactionItemMergeService` — Phase 0, no new dependency

Replace the float-tolerance guard in `app/Services/TransactionItemMergeService.php:11,98` (`AMOUNT_COMPARISON_EPSILON = 0.0001`, `abs($originalTotal - $newTotal) > self::AMOUNT_COMPARISON_EPSILON`) with an exact comparison using PHP's `bcmath` extension (`bccomp($originalTotal, $newTotal, $scale)` at a fixed scale matching `transaction_items.amount`'s column precision). `ext-bcmath` is already compiled into the local dev image (`vendor/laravel/sail/runtimes/8.4/Dockerfile:46`), so this requires no `composer require` and no dependency-approval gate. The two summed values being compared should be built from the raw string amounts (as read from the `DECIMAL` column) rather than from the float-cast model attribute, since casting to float before comparing would reintroduce the exact drift this FR removes.

### FR-2: Align `investment_prices.price` / `transaction_details_investment.price` scale — Phase 0

Add a migration that changes `transaction_details_investment.price` from `decimal(10,4)` to `decimal(20,10)`, matching `investment_prices.price`'s existing scale, so the same logical value (an investment's price at a point in time) is stored at the same precision regardless of which table it appears in. Implement a reversible `down()` per `.ai/agents/laravel-backend.agent.md`'s migration rules ("migrations must be reversible," "no destructive changes without confirmation" — widening a decimal column's scale is non-destructive and safe to reverse). No application code depends on the current narrower scale in a way that would break from widening it; confirm this by grepping for `transaction_details_investment.*price` usage before writing the migration.

### FR-3: Replace `round2()` with `decimal.js` — Phase 0, requires dependency-promotion approval

Replace `round2()` in `resources/js/reports/components/find-transactions/helpers.js:46-55` with a `decimal.js`-based rounding call at its five call sites inside `processCategoryGroup()` (lines 200-216). This requires promoting `decimal.js` from a transitive dependency (currently resolved only via `mathjs`) to a direct entry in `package.json`. **No new package is downloaded** — the same version already exists in `package-lock.json` — but adding a direct dependency declaration is still a dependency change and must be explicitly approved before implementation begins, per this repo's "do not add dependencies without user approval" rule.

### FR-4: `MoneyCast`/`DecimalCast` and first two field groups — Phase 1, requires new-dependency approval (`brick/math`, `brick/money`)

Introduce `brick/math` and `brick/money` via Composer (explicit approval required before this phase starts). Add a custom Eloquent cast (e.g. `app/Casts/MoneyCast.php`) implementing both `Illuminate\Contracts\Database\Eloquent\CastsAttributes` (so PHP code receives a `Brick\Math\BigDecimal`/`Brick\Money\Money` instance instead of a float) and `Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes` (so `toArray()`/`toJson()` emit a decimal string, not a JSON number — see background.md's "Wire format" section for why this is sufficient without a Resource-layer rewrite). Apply it first to `transaction_items.amount` and `transaction_details_standard.amount_from`/`amount_to` — the split/allocation-prone fields where Phase 0 already proved real drift exists.

### FR-5: Extend the cast to investment and currency fields — Phase 2

Extend `MoneyCast`/`DecimalCast` to `transaction_details_investment.price/quantity/commission/tax/dividend`, `investment_prices.price`, and `CurrencyRate.rate`. Define an explicit rounding mode (e.g. `RoundingMode::HALF_UP`, matching typical financial convention) for the currency-conversion arithmetic in `app/Http/Traits/CurrencyTrait.php`, which today performs `$value * $rate` with no rounding-mode control at all.

### FR-6: Frontend decimal adoption — Phase 3, builds on FR-3's direct `decimal.js` dependency

Clamp `MathInput.vue`'s emitted value (`resources/js/shared/ui/form/MathInput.vue:40,56`) to the relevant field's expected precision, finally using `Currency.generic_decimal_precision`/`detailed_decimal_precision` for more than display (background.md notes these fields are currently display-only). Extend `decimal.js` usage to `TransactionItemContainer.vue`'s allocation path (replacing the `toFixed(4)`/remainder-reconciliation workaround at lines 404-436), `TransactionFormStandard.vue`'s `allocatedAmount`/`remainingAmount*` computed properties (lines 802-827), and the API-response parsing layer, which must start parsing FR-4's decimal-string JSON output instead of assuming a JSON number.

### FR-7: Migrate materialized caches onto the new arithmetic path — Phase 4

Move `transactions.cashflow_value`'s write path (`TransactionService::getTransactionCashFlow()`, called from `app/Listeners/ProcessTransactionCreated.php:23` and `ProcessTransactionUpdated.php:38`) and `account_monthly_summaries.amount`'s write path (`CalculateAccountMonthlySummary` job, `TransactionService::recalculateMonthlySummaries()`) onto the cast/decimal-library arithmetic established in Phases 1-2, so drift cannot re-enter through these denormalized, cron- and event-rebuilt caches after everything upstream has been fixed.

## 5. Data Model Changes

- **Phase 0**: `transaction_details_investment.price` widened from `decimal(10,4)` to `decimal(20,10)` (FR-2). This is the only schema change in this document.
- **Phase 1+**: no further column-type changes are needed. `MoneyCast` reads the existing `DECIMAL` columns' string representation as PDO already returns them (absent a cast, a `DECIMAL` column comes back from the query as a string, not a pre-converted float) — only the PHP-side attribute type and JSON serialization shape change, not the underlying storage.

## 6. Backend Components to Update

**Phase 0 (this document's implementation-ready scope):**

- `app/Services/TransactionItemMergeService.php` — replace the epsilon comparison with `bccomp()` (FR-1).
- New migration for `transaction_details_investment.price` (FR-2).
- `tests/Unit/Services/TransactionItemMergeServiceTest.php` (or equivalent existing test file) — add/update coverage for the exact-comparison change.

**Phase 1+ (roadmap detail — each gated on the dependency approval named in its FR):**

- `composer.json` — add `brick/math`, `brick/money` (FR-4, needs approval).
- `app/Casts/MoneyCast.php` (new) — `CastsAttributes` + `SerializesCastableAttributes` (FR-4).
- `app/Models/TransactionItem.php` (`amount`), `app/Models/TransactionDetailStandard.php` (`amount_from`, `amount_to`) — first casts to convert (FR-4).
- `app/Models/TransactionDetailInvestment.php` (`price`, `quantity`, `commission`, `tax`, `dividend`), `app/Models/InvestmentPrice.php` (`price`), `app/Models/CurrencyRate.php` (`rate`) — second wave of casts (FR-5).
- `app/Http/Traits/CurrencyTrait.php` — explicit rounding mode for conversion arithmetic (FR-5).
- `app/Services/TransactionService.php`, `app/Jobs/CalculateAccountMonthlySummary.php` — migrate `cashflow_value`/`account_monthly_summaries` write paths (FR-7).
- Remaining models still on a blanket `'float'` cast for a money/quantity field (`app/Models/Account.php` — `opening_balance`; `app/Models/Transaction.php` — `cashflow_value`; `app/Models/AccountMonthlySummary.php` — `amount`) convert alongside FR-7, since they're part of the same cache-rebuild path.

## 7. Frontend Components to Update

**Phase 0:**

- `resources/js/reports/components/find-transactions/helpers.js` — replace `round2()` with `decimal.js` rounding (FR-3).
- `package.json` — promote `decimal.js` to a direct dependency (FR-3, needs approval).

**Phase 3 (roadmap detail, gated on FR-3's approval already having landed):**

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

### Backend Tests (Phase 1+, roadmap detail)

- Unit tests for `MoneyCast`/`DecimalCast`: round-trip correctness (DB string → `BigDecimal` → JSON decimal string), and that `toArray()`/`toJson()` emit a string, not a float.
- Feature tests asserting API responses for affected endpoints serialize the converted fields as JSON strings post-migration (a deliberate breaking change to the wire format — must be visible in test assertions, not just implied).

### Manual Verification (Phase 0)

- Manually exercise the transaction-item split/merge flow in the UI and confirm no false "amounts don't match" error appears for values that previously relied on the epsilon tolerance.
- Manually load the Monthly Breakdown report (`find-transactions`) and confirm totals/averages render identically to before the `round2()` replacement.

## 9. Acceptance Criteria

1. `TransactionItemMergeService` no longer contains a float-epsilon tolerance; the comparison is exact at the target scale, using `bcmath` (no new Composer dependency).
2. `investment_prices.price` and `transaction_details_investment.price` share the same decimal scale (`decimal(20,10)`), verified by a passing migration test.
3. `round2()` is removed from `find-transactions/helpers.js` and replaced by `decimal.js`-based rounding — only after `decimal.js`'s promotion to a direct dependency has been explicitly approved.
4. `vendor/bin/sail artisan test --compact` (scoped to `TransactionItemMergeService` and the new migration test), `./vendor/bin/pint --dirty`, and `npx eslint resources/js --ext .js,.vue` (scoped to the changed file) all pass.
5. No Phase 1-4 work begins without the dependency approval its FR names (`brick/math`/`brick/money` for FR-4/FR-5; `decimal.js` as a direct dependency for FR-3/FR-6) having been explicitly granted first.

## 10. Rollout Plan

This is an **implementation order**, not a set of independently-approved production commitments beyond Phase 0. Phase 0 is written at full implementation detail and is ready to build now, with no dependency-approval blocker. Phases 1-4 are written at roadmap detail (FRs and components named, not line-by-line) because each is gated on a dependency decision this document flags but cannot grant.

### Phase 0 — Fix the two proven bugs, zero new dependencies except one direct-dependency promotion

**Scope:** FR-1, FR-2, FR-3.
**Depends on:** nothing.
**Dependency approval needed:** promoting `decimal.js` from transitive to direct in `package.json` (FR-3 only — FR-1/FR-2 need no approval, since `bcmath` is already available).
**State at end of phase:** the two already-shipped workarounds are gone, replaced by exact arithmetic; the investment price scale mismatch is fixed. Nothing else in the app changes behavior.

### Phase 1 — Backend core: `MoneyCast`, first two field groups

**Scope:** FR-4.
**Depends on:** Phase 0 (proves the cast approach is worth investing in, on the same fields already known to be risky).
**Dependency approval needed:** `brick/math`, `brick/money` via Composer.
**State at end of phase:** `transaction_items.amount` and `transaction_details_standard.amount_from`/`amount_to` are backed by exact decimal arithmetic in PHP and serialize as decimal strings over the API. No other model has changed.

### Phase 2 — Investment and currency fields

**Scope:** FR-5.
**Depends on:** Phase 1 (reuses `MoneyCast`).
**Dependency approval needed:** none beyond Phase 1's (same library, more fields).
**State at end of phase:** investment price/quantity/commission/tax/dividend and currency-rate fields are exact; currency conversion has an explicit, documented rounding mode.

### Phase 3 — Frontend adoption

**Scope:** FR-6.
**Depends on:** Phase 0 (direct `decimal.js` dependency already present) and Phase 1 (decimal-string API responses to parse).
**Dependency approval needed:** none beyond Phase 0's (same library, more call sites).
**State at end of phase:** `MathInput.vue` clamps to field precision; the allocation/remainder call sites and API-response parsing use `decimal.js` instead of raw floats.

### Phase 4 — Materialized caches

**Scope:** FR-7.
**Depends on:** Phases 1-2 (the arithmetic these caches call into must already be exact).
**Dependency approval needed:** none beyond what's already been approved by this point.
**State at end of phase:** `cashflow_value` and `account_monthly_summaries` are computed via the same exact-decimal path as everything upstream of them — drift can no longer re-enter through denormalized data.
