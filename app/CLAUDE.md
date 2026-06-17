# Backend Context – YAFFA (`app/`)

See root [CLAUDE.md](../CLAUDE.md) for project overview and commands.
See [.ai/agents/laravel-backend.agent.md](../.ai/agents/laravel-backend.agent.md) for full backend implementation rules.

## Architecture Patterns

- **Services** (`app/Services/`) — all business logic; controllers stay thin
- **Controllers** — validate input via Form Request → call service → return response
- **Form Requests** (`app/Http/Requests/`) — every user input validated here, not inline
- **Policies** (`app/Policies/`) — all authorization; never bypass them
- **Jobs** (`app/Jobs/`) — async/background work via queue

## Key Domain Models

`Transaction`, `TransactionItem`, `TransactionSchedule`, `Account`, `AccountGroup`,
`Category`, `Payee`, `Tag`, `Investment`, `InvestmentGroup`, `Currency`, `CurrencyRate`

Read `.ai/docs/assets/` for entity definitions before modifying models.

## Migration Rules

- Always implement `down()` (reversible)
- When modifying a column, re-declare **all** previously defined attributes — omitting any drops them
- No destructive changes without explicit user confirmation

## Conventions

- Explicit return types on all methods
- PHPDoc blocks preferred over inline comments
- Constructor property promotion
- `Model::query()` over `DB::` raw queries
- Eager-load relationships to prevent N+1 problems

## Scope Boundaries

- Backend only — do not touch `resources/js/`, CSS, or Blade beyond routing/response
- Do not invent features beyond what is explicitly asked
- Do not add Composer packages without user approval
