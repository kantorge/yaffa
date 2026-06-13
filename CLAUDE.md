# YAFFA – Claude Code Context

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
