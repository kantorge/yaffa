# Architecture: Precision Improvements

## Overview

This feature (PR #522, `67c3b1e4` "feat: precision improvements") replaces YAFFA's blanket `'float'` Eloquent casts on money/quantity columns with two custom casts — `App\Casts\MoneyCast` and `App\Casts\DecimalCast` — built on `brick/money`/`brick/math`. It closes a documented gap (`.ai/docs/product-context.md:56`, "Precision handling for monetary values is currently limited") and removes two proven float-drift workarounds (an epsilon comparison in `TransactionItemMergeService`, a `round2()` helper in a report component).

This is a cross-cutting correctness change to the ORM/serialization boundary, not a new user-facing feature — there is no new screen, endpoint, or permission surface. The main externally visible effect is a **wire-format break**: affected `/api/v1/*` fields now serialize as decimal strings instead of JSON numbers.

Full rationale and phased rollout live in `.ai/docs/specifications/precision-improvements/` (`background.md`, `specification.md`, `frontend-precision-followup.md`); this document describes the implemented result as of `release/v4` and cross-references where a gap remains.

## What Changed

- **Added**: `app/Casts/MoneyCast.php`, `app/Casts/DecimalCast.php` — both implement `CastsAttributes` + `SerializesCastableAttributes`. `MoneyCast` wraps a genuine currency amount as `Brick\Money\Money`, resolving its `Currency` via a per-model method (e.g. `resolveAmountCurrency()`, `resolveCashflowCurrency()`) rather than a fixed relation, since the same field can be denominated differently depending on transaction shape (transfers, investment legs). `DecimalCast` wraps a quantity/ratio (share count, exchange rate) as `Brick\Math\BigDecimal` — no currency involved.
- **Added**: `ext-bcmath` (required extension), `brick/math` (`^0.14`), `brick/money` (`^0.11.2`) in `composer.json`. `decimal.js` promoted from a transitive (`mathjs`) to a direct frontend dependency.
- **Removed**: `TransactionItemMergeService::AMOUNT_COMPARISON_EPSILON` (float-tolerance guard, replaced by exact `bccomp()`); `round2()` in `resources/js/reports/components/find-transactions/helpers.js` (replaced by `decimal.js` rounding).
- **Changed**: `transaction_details_investment.price` widened from `decimal(10,4)` to `decimal(20,10)`, matching `investment_prices.price` (fixes a same-value/different-scale inconsistency). `TransactionApiController`'s base-currency conversion (`convertToBase()`, `app/Http/Controllers/API/TransactionApiController.php:524-529`) now uses `Money::convertedTo(..., RoundingMode::HalfUp)` instead of raw `amount * rate`. Materialized caches (`transactions.cashflow_value`, `account_monthly_summaries.amount`) and report aggregation (`ReportApiController::getCategoryWaterfallData()`/`budgetChart()`/`getCashflowData()`) now accumulate via `BigDecimal`, collapsing to `float` only at the JSON response boundary — reports therefore still emit plain JSON numbers, unlike transaction/investment/account endpoints.

## Which Models/Fields Use Which Cast

Confirmed by grepping every model's `casts()` for `MoneyCast::class`/`DecimalCast::class`:

| Model | Field | Cast | Scale | Currency resolver |
|---|---|---|---|---|
| `TransactionItem` | `amount` | `MoneyCast` | 4 | `resolveAmountCurrency()` |
| `TransactionDetailStandard` | `amount_from` | `MoneyCast` | 4 | `resolveAmountFromCurrency()` |
| `TransactionDetailStandard` | `amount_to` | `MoneyCast` | 4 | `resolveAmountToCurrency()` |
| `TransactionDetailInvestment` | `price` | `MoneyCast` | 10 | `resolveInvestmentCurrency()` |
| `TransactionDetailInvestment` | `commission`/`tax`/`dividend` | `MoneyCast` | 4 | `resolveAccountCurrency()` |
| `TransactionDetailInvestment` | `quantity` | `DecimalCast` | 4 | n/a (not a currency amount) |
| `InvestmentPrice` | `price` | `MoneyCast` | 10 | `resolveInvestmentCurrency()` |
| `Transaction` | `cashflow_value` | `MoneyCast` | 4 | `resolveCashflowCurrency()` → `transaction_currency` |
| `Account` | `opening_balance` | `MoneyCast` | 10 | `resolveOpeningBalanceCurrency()` |
| `AccountMonthlySummary` | `amount` | `MoneyCast` | 4 | `resolveAmountCurrency()` |
| `Budget` | `amount` | `MoneyCast` | 4 | `currency()` |
| `CurrencyRate` | `rate` | `DecimalCast` | 10 | n/a |

`app/Models/Currency.php:146` and `app/Models/Account.php:115` both call `MoneyCast::toFloat()`/`DecimalCast::toFloat()` — the documented unwrap path for consumers not yet migrated to exact arithmetic.

## Tech Stack Notes Specific to This Feature

- **`brick/math`/`brick/money`** — chosen over `moneyphp/money` specifically because this schema is decimal-scale-native (4/10 fractional digits for investment fields), not cents-native; see `background.md` "Options Considered." `brick/math` auto-upgrades to a faster `BcMathCalculator` now that `ext-bcmath` is required, at no extra dependency cost.
- **`MoneyCast`/`DecimalCast::set()` both round with `RoundingMode::HalfUp` on write**, deliberately looser than the `RoundingMode::Unnecessary`-style strictness a `get()` implicitly has (a DB value is already at-scale) — this tolerates over-precise input until real input clamping exists end-to-end in the UI layer. **This is a currently-open gap, not a resolved one** — see Known Risks below.
- **`Money::formatTo()` (locale-aware, `ext-intl`-dependent) is never called anywhere in `app/`** — confirmed by grep (`resources/js/shared/ui/form/MathInput.vue:79`'s `formatToParts` is `Intl.NumberFormat`'s own method, unrelated). Display formatting stays centralized in `resources/js/shared/lib/i18n/format.js` (`toFormattedCurrency()`) and always will — confirming the root `CLAUDE.md`'s rule against introducing an undocumented `ext-intl` dependency. `format.js`'s `getDecimalPrecision()` helper — which originally doubled as the input-clamp source before FR-8 — was removed once FR-8 re-pointed every consumer at `STORAGE_SCALE` instead; `toFormattedCurrency()` no longer forces `minimumFractionDigits = maximumFractionDigits`, computing a floor (currency precision)/ceiling (storage scale) split instead, without touching the underlying `Intl.NumberFormat` mechanism.
- **`app/Support/ScheduleInstance.php`** (a pre-existing, unrelated feature's non-Eloquent Transaction stand-in for virtual forecast occurrences) correctly unwraps both new types in its own `toArray()` (lines 88-93): a `Money` instance serializes as `(string) $value->getAmount()` and a `BigDecimal` as `(string) $value`, explicitly commented as matching `MoneyCast::serialize()`/`DecimalCast::serialize()`'s shape rather than `Money`'s own `JsonSerializable` (`{"amount":...,"currency":...}`). This was verified, not assumed — it is the one non-Eloquent value object the root `CLAUDE.md` specifically warns could silently diverge.
- **Legacy-data currency mismatch handled without throwing.** `TransactionService::getInvestmentConfigCashFlow()` (`app/Services/TransactionService.php:~196-207`) catches the case where an investment transaction's leg currencies no longer match (an account or investment's currency changed after the transaction was recorded) — `Money::plus()` would throw `MoneyMismatchException`; instead the method logs a `warning` ("Investment transaction cash flow spans mismatched currencies (legacy data)") with the transaction id and returns `null` for `cashflow_value`. Documented as an upgrade action item in `UPGRADE.md`'s "API Response Precision" entry.

## Trust Boundaries

| Boundary | Notes |
|---|---|
| Server (Eloquent hydration) → PHP application code | Unchanged authorization surface — the cast changes the *type* of a hydrated attribute (`Money`/`BigDecimal` instead of `float`), not who can read/write it. No new Policy, Gate, or middleware was introduced. |
| Server → `/api/v1/*` JSON response | **This is the boundary that actually changed.** Previously a plain JSON number; now a decimal string, for every field listed in "Which Models/Fields Use Which Cast" above, plus their computed `amount_from_base`/`amount_to_base`/`amount_in_base` counterparts. See `flows.md`. |
| Server → `/api/v1/reports/*` JSON response | **Deliberately unchanged** — report endpoints accumulate via `BigDecimal` internally but collapse to `float` before `response()->json()`, so existing report consumers see no wire-format change. |
| Bundled frontend → API | Already updated to expect decimal strings for the affected non-report endpoints (`resources/js/shared/lib/helpers/index.js`'s `processTransaction()`), **except one confirmed, still-open gap** — see Known Risks. |

## Known Risks / Assumptions

- **Breaking change for any custom/third-party API consumer.** Any external integration reading `/api/v1/*` transaction, investment, account, or currency-rate fields as a JSON number will now receive a string and must be updated to parse it as such. This is the single largest real-world impact of this feature and is called out explicitly in `UPGRADE.md`'s "API Response Precision" section (`UPGRADE.md:135-153`) with the full field list.
- **`transaction_details_investment.price` frontend precision gap is real and still open**, per `.ai/docs/specifications/precision-improvements/frontend-precision-followup.md` (status: not started as of that document, and confirmed still current by code inspection here). `resources/js/shared/lib/helpers/index.js:198` (`processTransaction()`) still converts `config.price` — a `DECIMAL(20,10)` field, whose significant-digit count can exceed what a JS `Number` holds exactly — to a native `Number` on every transaction fetch. Opening an existing high-precision investment transaction (typically written by an automated price-provider import, not manual entry) and saving it **without touching the price field** can silently truncate that price. Scale-4 fields (`amount`, `quantity`, `commission`, `tax`, `dividend`) are not at risk — only `price`'s scale-10 columns are. No fix for this shipped as part of PR #522; it is scoped as follow-up work, not a regression introduced silently.
- **Resolved: input-side precision clamping now targets each field's actual storage scale, not a currency's display precision.** `MathInput.vue` gained a `precision` prop (Phase 3, FR-6) that initially sourced it from `Currency.generic_decimal_precision`/`detailed_decimal_precision` — a per-currency **display** setting — which conflated a currency's conventional precision with a hard input ceiling and silently capped what a user could store, not just how it displayed. `.ai/docs/specifications/precision-improvements/specification.md` FR-8 (Phase 5, ✅ Completed) closed this: `resources/js/shared/lib/money/scale.js`'s `STORAGE_SCALE` constant is now the input/validation ceiling everywhere (`MathInput` consumers and `TransactionRequest`'s new `decimal:0,<scale>` rules), and the currency's configured precision is a display-only floor in `format.js`, paired with the storage scale as the display ceiling so `Intl.NumberFormat` trims trailing zeros instead of forcing an exact digit count. `MoneyCast`/`DecimalCast::set()` itself still rounds with `RoundingMode::HalfUp` rather than throwing — that stays a deliberate cast-layer backstop for any write path that bypasses `TransactionRequest`, not a gap FR-8 was meant to close at the cast level.
- **This feature has no new authorization surface** — see `permissions.md` for the one-line confirmation.

## Related Documents

- [flows.md](flows.md) — the API-consumer-facing wire-format change: what a client sees on read/write, before and after.
- [permissions.md](permissions.md) — confirms no new authz surface (n/a note, with rationale).
- [variables.md](variables.md) — confirms no new config/secrets (n/a note).
- `.ai/docs/specifications/precision-improvements/background.md` — pre-migration baseline, rationale, and library options considered.
- `.ai/docs/specifications/precision-improvements/specification.md` — the FR-1 through FR-8 implementation-handoff spec, all phases (0-5) completed.
- `.ai/docs/specifications/precision-improvements/frontend-precision-followup.md` — the one confirmed-still-open gap (investment `price` field precision on the frontend), status "not started."
- `UPGRADE.md` "API Response Precision (Decimal String Wire Format)" section — the user-facing upgrade note with the full affected-field list and the legacy-data currency-mismatch action item.
- `.ai/docs/product-context.md:56` — the original non-goal/limitation this feature closes.
