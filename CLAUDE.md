# YAFFA - Claude Code Context

## Project Overview

YAFFA (Yet Another Free Financial Application) is a self-hosted personal finance web application. It enables multi-account/currency tracking, transaction categorization, investment monitoring, and long-term financial planning. **Conscious manual tracking is a core product value** — it is not a bank-sync tool.

## Tech Stack

- **PHP 8.4 / Laravel 12** — MVC + Services architecture
- **Vue 3 (Options API)** + **Bootstrap 5.3** + **CoreUI** — multi-page app, NOT a SPA
- **MySQL 8** — primary database
- **Redis** — queue backend for background jobs
- **Vite** — asset bundling
- **Laravel Sail** — Docker-based local development

## Key Commands

All PHP/Artisan/Composer/Node commands **must** be prefixed with `vendor/bin/sail`:

```bash
vendor/bin/sail up -d                     # start services
vendor/bin/sail artisan migrate           # run migrations
vendor/bin/sail npm run dev               # build assets (dev)
vendor/bin/sail npm run build             # build assets (production)
vendor/bin/sail artisan test --compact    # run tests
vendor/bin/sail bin pint --dirty          # fix PHP code style
```

## Critical Rules

- **NEVER modify `.env`** — ask the user instead; it is off-limits at all times
- Always rebuild assets after JS/Vue/SCSS changes before testing UI
- Always run Pint before finalizing PHP changes
- Run only the minimum affected tests, then ask if the full suite should follow
- Do not add dependencies or restructure directories without user approval
- **QIF/CSV import — system profiles are code-only**: `FileImportProfile` rows of `type = system` (with executable `matching_rules`) are defined solely in `SystemFileImportProfileRegistry` and applied via `artisan app:import:sync-system-profiles` at deploy time. Never add an API/UI path that lets a user create or mutate a `system`-typed profile or set `options_json.matching_rules`/`actions` on a `user`-typed one — see `.ai/docs/features/qif-csv-import/permissions.md` and `architecture.md` (ReDoS risk note).
- **API personal access token `abilities` are now enforced on every `API` controller, not just token-management and 2FA-management.** Each controller's `middleware()` (via `Illuminate\Routing\Controllers\HasMiddleware`) declares `abilities:read`/`abilities:write`/`abilities:settings` per action, scoped with `Illuminate\Routing\Controllers\Middleware`'s `only:` parameter — a no-op for session requests (`TransientToken::can()` is always `true`), a real gate for bearer tokens. The per-controller mapping, rationale, and the five config/credential controllers that are `settings`-gated on every action (including reads) are documented in `.ai/docs/features/api-access-and-2fa/permissions.md`. Coverage is pinned by `tests/Feature/API/ApiAbilityEnforcementTest.php` (one deny + one allow case per controller) — keep that data provider in sync whenever a new `API` controller or action is added.
- **A stray `storage/app/duskapiconf_tmp.txt` silently overrides live `config()` values project-wide, for any `artisan` command, not just Dusk runs.** `alebatistella/duskapiconf` (used by `tests/DuskTestCase.php` via `setConfig()`/`getConfig()`) persists overrides to this file, and its service provider re-applies the whole file to `config()` on every non-production boot until the file is deleted. `DuskTestCase::tearDown()` deletes it after every Dusk test, but a killed test run (Ctrl+C, OOM) can still leak it. If a `config('yaffa.*')` value looks wrong in this dev environment for no reason you can find in code (e.g. `sandbox_mode` stuck `true`), check this file before chasing anything else — `cat storage/app/duskapiconf_tmp.txt` then `rm` it. See `tests/CLAUDE.md` "Dusk-Specific" for the full mechanism.
- **Money/quantity model attributes are cast via `App\Casts\MoneyCast`/`App\Casts\DecimalCast` (`brick/math`/`brick/money`), never a blanket `'float'` cast.** `MoneyCast` (an actual currency amount — resolves its `Currency` via a per-model `resolve*Currency()` method) yields a `Brick\Money\Money`; `DecimalCast` (a quantity/ratio that isn't itself a currency amount — a share count, an exchange rate) yields a `Brick\Math\BigDecimal`. Both implement `SerializesCastableAttributes`, so `toArray()`/`toJson()` — and therefore every `/api/v1/*` response — emit these fields as **decimal strings, not JSON numbers**; a new API consumer must parse them as such, not assume a JSON number (see `UPGRADE.md`'s "API Response Precision" entry for the exact field list). Never call `Money::formatTo()` from backend code — it would introduce an undocumented `ext-intl` runtime dependency; display formatting is centralized in `resources/js/shared/lib/i18n/format.js` on the frontend instead. Any new non-Eloquent value object that stands in for a Transaction (e.g. `App\Support\ScheduleInstance`) must unwrap `Money`/`BigDecimal` the same way in its own `toArray()`, or it'll silently serialize to `Money`'s own `{"amount":...,"currency":...}` JSON shape instead of matching the cast's decimal-string wire format. See `.ai/docs/specifications/precision-improvements/` for the full rationale.
- **All recurrence-pattern evaluation (budgets and transaction schedules) must go through `RecurrenceRuleService`, never a hand-built `Recurr\Rule`.** It is the only place a `Recurr\Rule` gets constructed (`buildRule()`, `public` specifically so other call sites reuse it) and applies `by_day`/`by_month` ordinal-weekday patterns (e.g. "first Wednesday of every month") via `setByDay()`/`setByMonth()`. `TransactionSchedule::isActive()`/`getNextInstance()`/`occursOn()`, `Budget`, and `Transaction::scheduleInstances()` (forecast/budget-chart projection) all route through it. See `.ai/docs/features/budget-schedule-redesign/architecture.md`.

## Domain Documentation

Read `.ai/docs/` before implementing a feature — it describes the domain model and product intent:

| Path                          | Contents                                                                     |
| ----------------------------- | ---------------------------------------------------------------------------- |
| `.ai/docs/product-context.md` | Philosophy, goals, non-goals                                                 |
| `.ai/docs/assets/`            | Entity definitions (account, transaction, category, payee, investment, etc.) |
| `.ai/docs/features/`          | Feature specifications (AI document processing, reports, dashboard, etc.)    |
| `.ai/docs/specifications/`    | Implementation specs                                                         |

Code is always the source of truth if docs and code conflict. Notify the user if you find discrepancies, and suggest doc updates.

## Agent Role Files

Role-specific implementation guidelines live in `.ai/agents/`:

| File                       | Purpose                                     |
| -------------------------- | ------------------------------------------- |
| `planning.agent.md`        | Feature scoping and requirement structuring |
| `laravel-backend.agent.md` | Laravel backend implementation rules        |
| `frontend.agent.md`        | Vue/Blade frontend implementation rules     |
| `testing.agent.md`         | Test design and coverage rules              |
| `documentation.agent.md`   | Feature documentation extraction            |

## Architecture Highlights

- **Services over controllers**: business logic lives in `app/Services/`
- **Form Requests**: all validation via dedicated `app/Http/Requests/` classes
- **No SPA state**: Blade pages are independent; Vue components are self-contained islands
- **PHPUnit only** — no Pest
- **Feature tests preferred** over Dusk; Dusk only for critical E2E flows
- **Build output** (`public/js/`, `public/css/`) is Git-ignored — do not commit built assets

## Directory Reference

```
app/Http/Controllers/   thin controllers
app/Services/           business logic
app/Models/             Eloquent models
app/Policies/           authorization
app/Jobs/               queue jobs
resources/views/        Blade templates
resources/js/           Vue components + JS
tests/Unit/             pure logic tests
tests/Feature/          HTTP/API tests
tests/Browser/          Dusk E2E tests
.ai/docs/               domain documentation
.ai/agents/             agent role instructions
```

## Linting

**Run linters before committing code to catch style and quality issues.**

```bash
# PHP linting (PSR-12 code style)
./vendor/bin/pint              # Auto-fixes style issues

# PHP static analysis (PHPStan Level 5)
./vendor/bin/phpstan analyse   # Finds type errors and bugs

# JavaScript/Vue linting
npx eslint resources/js --ext .js,.vue
```

**Note:** Pint excludes `vendor/`, `public/`, `storage/`, `bootstrap/` directories. PHPStan analyzes `app/` directory only.
