# Flows: Precision Improvements

Only flows that touch data integrity or an external-consumer-facing contract change are documented here. This feature adds no new permission checks or side effects beyond what already existed on these endpoints — the flow that matters is the **wire-format change itself**, not a new user journey.

## 1. API Consumer Reads a Transaction/Investment/Account/Currency-Rate Record

- **Actor:** any API consumer — the bundled frontend, or a third-party integration using a personal access token.
- **Precondition:** an authenticated request (session or bearer token with `read` ability) to an endpoint returning a `Transaction`, `TransactionItem`, `TransactionDetailStandard`/`TransactionDetailInvestment` (as `config`), `Account`, `InvestmentPrice`, `CurrencyRate`, or `Budget` model.
- **Success outcome (unchanged):** the model's data is returned as JSON, same shape/fields as before this feature.
- **Behavior that changed:** the affected numeric fields (see `architecture.md`'s field table) serialize as **decimal strings**, not JSON numbers.

| Step | Boundary crossed | Behavior before this feature | Behavior after this feature |
|---|---|---|---|
| 1. `GET /api/v1/transactions/{id}` (or any endpoint serializing one of the affected models). | Browser/client → server | — | — |
| 2. Eloquent hydrates the model; the affected attribute is cast. | Server (DB → PHP) | `'float'` cast — attribute becomes a PHP `float`, already lossy at hydration time for high-scale columns. | `MoneyCast`/`DecimalCast` — attribute becomes `Brick\Money\Money`/`Brick\Math\BigDecimal`, exact. |
| 3. `toArray()`/`toJson()` serializes the model for the response (no Resource-class layer intercepts these fields — see `background.md`'s "Wire format" section for why the cast alone is sufficient here). | Server (PHP → JSON) | Plain float → JSON number (e.g. `12.34`). | `SerializesCastableAttributes::serialize()` → JSON **string** (e.g. `"12.3400000000"`, at the column's own scale — not trimmed). |
| 4. Response reaches the consumer. | Server → client | Consumer's JSON parser yields a `number`; any consumer treating it as one worked, silently accepting prior float drift. | Consumer's JSON parser yields a `string`. **A consumer that does not update its parsing will get a type mismatch** (e.g. arithmetic on the value in a dynamically-typed language may coerce or concatenate instead of adding) — this is the deliberate breaking change. |
| 5. Bundled frontend: `processTransaction()` (`resources/js/shared/lib/helpers/index.js:184-202`) explicitly converts each affected field back to a `Number` via `toNumberOrNull()` immediately after the response is parsed. | Client (JS) | N/A (fields were already numbers). | Handles the string→number conversion for `cashflow_value`, item `amount`/`amount_in_base`, and `config.amount_from`/`amount_to`/`price`/`quantity`/`commission`/`tax`/`dividend` — **except this conversion is lossy for `config.price`'s `DECIMAL(20,10)` scale**, see below and `architecture.md`'s Known Risks. |

**Report endpoints are the deliberate exception.** `GET /api/v1/reports/*` (`ReportApiController::getCategoryWaterfallData()`/`budgetChart()`/`getCashflowData()`) accumulate via `BigDecimal` internally but explicitly collapse to `float` before `response()->json()` — these responses are unchanged JSON numbers, per `UPGRADE.md`. A consumer of report endpoints needs no update.

## 2. API Consumer Writes a Transaction/Investment/Account Field

- **Actor:** any API consumer submitting a create/update request with an amount/price/quantity value.
- **Precondition:** authenticated, `write`-ability request.
- **Success outcome:** the value is persisted at the column's own DECIMAL scale.

| Step | Boundary crossed | Authz/validation | Side effect |
|---|---|---|---|
| 1. `POST`/`PATCH` with a numeric field, sent as either a JSON number or a decimal string — the cast's `set()` accepts both (`$value instanceof Money ? ... : BigDecimal::of((string) $value)`, `app/Casts/MoneyCast.php:47`). | Client → server | Existing FormRequest validation (`TransactionRequest`, etc.) is unchanged by this feature — still `numeric`/`gt:0`/range rules. | None yet. |
| 2. `MoneyCast::set()`/`DecimalCast::set()` rounds the incoming value to the column's fixed scale via `RoundingMode::HalfUp` (`app/Casts/MoneyCast.php:51`, `app/Casts/DecimalCast.php:37`) before it's written. | Server | **Data-integrity note, not an authz check:** this rounding is deliberately permissive of over-precise input — it tolerates a client submitting more fractional digits than the column supports rather than rejecting the request, until real input-side clamping exists end-to-end (see `architecture.md`'s Known Risks and the frontend-precision-followup). A client relying on exact preservation of a high-precision value it submits (e.g. more than 10 significant fractional digits for `price`) may see it silently rounded, not rejected. | Row inserted/updated at the resolved scale. |
| 3. Response reflects the stored (rounded) value, serialized per Flow 1. | Server → client | — | — |

## 3. Legacy Investment Transaction With Mismatched Leg Currencies

- **Actor:** the system, computing `cashflow_value` for an investment transaction whose account/investment currency was changed after the transaction was originally recorded (only possible for data predating the currency-mismatch guard this feature's rollout also added to the relevant forms).
- **Precondition:** a `TransactionDetailInvestment` row exists where the investment-currency-denominated (`price`) and account-currency-denominated (`commission`/`tax`/`dividend`) legs no longer share a currency.
- **Success outcome:** the transaction still loads and serializes without a 500 — `cashflow_value` degrades to `null` instead.

| Step | Boundary crossed | Check | Side effect |
|---|---|---|---|
| 1. `TransactionService::getInvestmentConfigCashFlow()` sums the investment's `Money` terms. | Server | Compares each term's `Currency` before combining (`app/Services/TransactionService.php:~196-205`). | None if all match — normal exact-arithmetic path. |
| 2. On mismatch: a `warning`-level log entry is written (`"Investment transaction cash flow spans mismatched currencies (legacy data)"`, transaction id included) and the method returns `null` rather than letting `Money::plus()`'s `MoneyMismatchException` escape. | Server | Not an authz check — a data-integrity guard against a legacy-data edge case. | `cashflow_value` for that transaction becomes `null` until an operator manually corrects the account/investment currency or the transaction, per `UPGRADE.md`'s upgrade action item. |
