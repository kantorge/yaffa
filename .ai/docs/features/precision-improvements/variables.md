# Variables: Precision Improvements

## No New Environment Variables, Config Values, or Secrets

Verified by grepping every file this feature added or changed (`app/Casts/MoneyCast.php`, `app/Casts/DecimalCast.php`, the affected models, `TransactionApiController`, `TransactionService`, `TransactionItemMergeService`, `ReportApiController`) for `config(`, `env(`, `Cache::`/`cache(`, and `->onQueue(` — none found:

- **No config-driven behavior.** Every scale (`4`, `10`) and rounding mode (`RoundingMode::HalfUp`) is a hardcoded literal at each cast-declaration/call site (e.g. `'price' => MoneyCast::class . ':10,resolveInvestmentCurrency'` in `app/Models/InvestmentPrice.php:52`), not a config value — matching each column's own fixed `DECIMAL(x,scale)` definition.
- **No new secrets or third-party credentials.** `brick/math`/`brick/money` are pure in-process arithmetic libraries with no outbound calls, API keys, or credentials of any kind.
- **New Composer/npm dependencies (not secrets, but worth listing for a dependency audit):** `brick/math` (`^0.14`), `brick/money` (`^0.11.2`), `ext-bcmath` (PHP extension requirement) in `composer.json`; `decimal.js` promoted from transitive to direct in `package.json` (no new package downloaded — same version already resolved via `mathjs`).
- **One new operational requirement, not a secret:** `ext-bcmath` must be compiled into any self-hosted PHP runtime that isn't the bundled Sail/Docker image (already present there). `composer install` now fails fast if it's missing, per `UPGRADE.md`.

No pre-existing variable is newly read by this feature either — the change is confined to Eloquent attribute casting and JSON serialization, neither of which is config-gated in this codebase.
