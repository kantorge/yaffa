# Testing Context – YAFFA (`tests/`)

See root [CLAUDE.md](../CLAUDE.md) for project overview and commands.
See [.ai/agents/testing.agent.md](../.ai/agents/testing.agent.md) for full testing rules.

## Test Level Guide

| Level | Location | Use when |
|-------|----------|----------|
| Unit | `tests/Unit/` | Pure isolated logic, no DB or HTTP |
| Feature | `tests/Feature/` | HTTP endpoints, policies, validation, DB |
| Browser (Dusk) | `tests/Browser/` | Critical E2E user journeys only |

Prefer Feature tests. Use Dusk sparingly — only when a full browser interaction is essential.

## Running Tests

```bash
# Single file
vendor/bin/sail artisan test --compact tests/Feature/SomeTest.php

# Filter by name
vendor/bin/sail artisan test --compact --filter=testMethodName

# Full suite
vendor/bin/sail artisan test --compact
```

## Conventions

- PHPUnit only — no Pest
- Test names describe behavior: `it_rejects_a_transaction_without_an_account`
- Arrange / Act / Assert structure
- One behavior per test method
- Use factories for all test data; check for existing factory states before adding new ones
- Reuse the **generic test user** and their seeded assets by default
- UI/text assertions: use English locale unless testing locale-specific behavior
- Seed the database once per class where possible (`setUpBeforeClass` / `RefreshDatabase`)

## Dusk-Specific

- Prefer `data-testid` selectors over CSS class or text selectors
- No `sleep()` — use `waitFor` and `waitUntilMissing` instead
- Focus on E2E journeys, not duplicating feature-level coverage

## Do NOT

- Remove existing tests without explicit user approval
- Weaken assertions to make tests pass
- Introduce new testing frameworks
- Add arbitrary delays or flaky environment-dependent behavior
