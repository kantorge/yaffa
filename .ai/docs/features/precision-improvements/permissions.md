# Permissions: Precision Improvements

## N/A — No New Authorization Surface

This feature changes how money/quantity Eloquent attributes are cast and serialized (`'float'` → `App\Casts\MoneyCast`/`App\Casts\DecimalCast`). It touches the ORM hydration and JSON-serialization boundary only.

Verified by inspecting every file this feature added or changed (`app/Casts/MoneyCast.php`, `app/Casts/DecimalCast.php`, the model `casts()` methods listed in `architecture.md`, `TransactionApiController::convertToBase()`, `TransactionService::getInvestmentConfigCashFlow()`, `TransactionItemMergeService`, `ReportApiController`'s three aggregation methods):

- **No new route, controller, middleware, Policy, or Gate was added.** Every affected endpoint (`TransactionApiController`, `ReportApiController`, `AccountApiController`, `InvestmentPriceController`, etc.) keeps its pre-existing `auth:sanctum`/`verified`/`abilities:*` middleware and Policy checks unchanged — this feature never modifies a `middleware()` declaration or a Policy class.
- **No new role, claim, or ownership rule.** Who may read/write a given `Transaction`/`Account`/`Budget`/etc. row is entirely unaffected — only the PHP type and wire shape of specific numeric fields on those same rows changed.
- **The one behavior a security review might mistake for an authz change is not one:** `MoneyCast`/`DecimalCast::set()` rounds an over-precise incoming value with `RoundingMode::HalfUp` rather than rejecting it (see `flows.md` Flow 2). This is a data-integrity/input-tolerance choice, not an access-control gap — it does not let a caller read or write anything a different caller couldn't already read or write; it only affects how much of a submitted value's precision survives. `specification.md` FR-8 (Phase 5, ✅ Completed) added `TransactionRequest`/`InvestmentPriceRequest`-level validation for this, also not an authz change — a stricter data-shape rule, not a new permission boundary.

For the project's actual authorization model (roles, token abilities, ownership enforcement), see `.ai/docs/features/api-access-and-2fa/permissions.md` and `.ai/docs/features/budget-schedule-redesign/permissions.md` — both apply unchanged to every endpoint this feature touches.
