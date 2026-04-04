# Investment Price Providers (MVP+)

## Feature Summary

Introduce a flexible investment price provider architecture that supports:

- one selected provider per investment,
- provider-specific investment settings without schema churn,
- optional per-user provider credentials (API keys),
- provider-specific rate-limiting and dispatch behavior,
- robust async retrieval in parallel queue environments.

The goal is to replace provider-specific hardcoded fields and hardcoded throttling logic with a small, reliable, extensible system that remains easy to operate.

## Goals / Non-Goals

- Goals:
  - Keep one provider per investment (for now).
  - Move provider-specific investment settings to a generic structure.
  - Support per-user provider credentials (for API-based providers).
  - Generalize provider-specific rate limits for command dispatch and job runtime.
  - Preserve and reuse retrieval state tracking per investment.
  - Keep migration path backward compatible for existing web scraping and Alpha Vantage users.
  - Provide clear backend and frontend integration contracts.

- Non-Goals:
  - Multiple providers per single investment at the same time.
  - Provider marketplace or plugin system in MVP.
  - Historical credential versioning / rotation audit trail.
  - Automatic fallback from one provider to another in one retrieval attempt.
  - Public provider sharing between users.

## Assumptions

- Laravel 12, MySQL, Redis-capable queue/cache setup are available.
- Queue workers may run in parallel; throttling must be global and lock-safe.
- Existing retrieval runs through queued jobs (`GetInvestmentPrices`).
- Existing provider registry pattern remains the foundation.
- Existing `last_price_fetch_*` fields remain valid and should be reused.

## Current Pain Points

- Provider-specific investment settings are normalized from schema and persisted in `provider_settings`.
- API keys are global in environment config, not user-scoped.
- Rate-limiting policy is currently provider-branching logic, not policy-driven.
- Adding a new provider requires touching multiple unrelated layers.

## Recent Implementation Updates (2026-04)

- Investment provider runtime now uses `provider_settings` only; legacy scraping field fallbacks were removed.
- Legacy `scrape_url` and `scrape_selector` columns were backfilled and removed from `investments`.
- `InvestmentProviderConfig.last_validated_at` was removed from runtime usage, API resource output, factories, and schema.
- Investment provider form now includes:
  - provider status icon with tooltip-based detailed state,
  - schema-driven provider setting inputs,
  - test-fetch action.
- Investment provider account settings now include provider-config removal with SweetAlert confirmation for deleting unused credentials/options.

## Target Architecture

### 1. Provider Selection (per investment)

- Keep `investment_price_provider` as the single selected provider key.
- Add `provider_settings` JSON on investments for provider-specific settings.
  - Example:
    - `alpha_vantage`: `{}`
    - `web_scraping`: `{"url":"...","selector":"..."}`

### 2. Provider Credentials (per user)

- New model/table: `InvestmentProviderConfig`.
- One row per user+provider key (unique pair).
- Sensitive fields encrypted (API keys / tokens).
- Used by API-based providers (e.g. Alpha Vantage), optional for others.

### 3. Provider Metadata Contract

Each provider exposes metadata consumed by backend and frontend:

- `key`, `displayName`, `description`
- `investmentSettingsSchema` (validation + UI hints)
- `userSettingsSchema` (credential requirements)
- `rateLimitPolicy` (per-second/per-minute/per-day, reserve)
- `supportsHistoricalSync` (optional capability flag; indicates full-history/refill retrieval support)

Validation contract details:

- `investmentSettingsSchema` is the source of truth for provider-specific investment fields.
- `userSettingsSchema` is the source of truth for provider credential/options fields.
- Schema format should be simple JSON-schema-like metadata (required, type, min/max, enum, pattern, label, help text).
- Backend must always validate from schema; frontend validation is advisory only.

### 4. Resolver Layer

- New service: `InvestmentPriceProviderContextResolver`.
- Input: `Investment` (+ user).
- Output:
  - provider implementation,
  - validated investment settings,
  - user provider config/credentials,
  - resolved rate-limit policy.

All retrieval flows use this resolver to avoid duplicated branching.

### 5. Rate Limiting Generalization

- Register Laravel `RateLimiter::for(...)` buckets dynamically per provider policy.
- Dispatch command computes available daily budget per provider.
- Job middleware applies provider-specific runtime limits.
- Keep `WithoutOverlapping` per investment.

Per-key quota policy:

- Provider metadata defines safe default rate limits (free-tier defaults).
- User provider config may optionally include `rate_limit_overrides` (e.g. when using paid key).
- Overrides are validated against provider-allowed bounds (prevent impossible or unsafe values).
- Effective policy is resolved per user+provider config at runtime and used by both dispatch and middleware.

### 6. Provider Availability and Eligibility

- The provider list for the investment modification form should show only providers that are usable by the current user by default.
- Usable means:
  - provider is globally enabled by the application,
  - provider is enabled in user's `InvestmentProviderConfig` when credentials are required,
  - required credentials are present and valid enough for scheduling.
- Optional UX toggle can expose unsupported/unconfigured providers with clear "setup required" badge.
- This keeps the default form compact and prevents selecting providers that cannot run.

### 7. Retrieval State

- Keep and reuse fields:
  - `last_price_fetch_attempted_at`
  - `last_price_fetch_succeeded_at`
  - `last_price_fetch_error_at`
  - `last_price_fetch_error_message`
- Use for fairness and diagnostics independent of provider type.

## Data Model Scope

### Investments

- Add:
  - `provider_settings` JSON nullable

### InvestmentProviderConfig (new)

- `id`
- `user_id` (FK)
- `provider_key` (string)
- `credentials` (encrypted JSON)
- `options` (JSON nullable)
- `enabled` (bool default true)
- `last_error` (text nullable)
- `rate_limit_overrides` (JSON nullable, validated)
- unique index: (`user_id`, `provider_key`)

## Backend Scope (Laravel)

- Models:
  - `Investment` (add `provider_settings` cast)
  - `InvestmentProviderConfig` (new)

- Migrations:
  - add `provider_settings` to `investments`
  - create `investment_provider_configs`
  - optional migration helper from legacy scrape fields to JSON

- Services:
  - `InvestmentPriceProviderContextResolver` (new)
  - `InvestmentProviderRateLimitPolicyResolver` (new, can be merged into context resolver initially)
  - `InvestmentProviderAvailabilityService` (new, determines what providers are selectable per user)
  - `InvestmentProviderPreflightService` (new, checks config/credential availability before retrieval run)
  - Update `InvestmentService` to use resolver context

- Provider Registry:
  - Extend metadata output with settings schemas and rate policy

- Jobs / Commands:
  - `GetInvestmentPrices` job middleware uses provider rate policy
  - `app:investment-prices:get` dispatch logic groups by provider and applies provider budget

- Validation:
  - investment create/update validates provider_settings against provider schema
  - provider config CRUD validates user settings schema
  - provider config CRUD validates `rate_limit_overrides` against provider policy constraints

- Security:
  - credentials encrypted cast
  - policy checks: users can only read/write own provider configs

## Frontend Scope (Vue + Bootstrap)

- Investment form:
  - provider selector remains single-choice
  - show selectable/usable providers, with support-state hints for unavailable entries when returned by API
  - show compact provider status as icon + tooltip
  - embed schema-driven provider settings editor for the selected provider
  - provide `Test fetch` action in the settings panel

- Investment provider settings behavior:
  - provider settings are saved through provider-settings API endpoints
  - settings editor only renders fields defined by `investmentSettingsSchema`
  - if provider has no required settings, show informational guidance instead of empty controls

- User settings (or dedicated provider settings section):
  - provider credential forms per provider
  - provider configuration removal action (with confirmation) to delete unused credentials/config
  - optional advanced rate-limit override fields when allowed by provider policy

- Display and diagnostics:
  - optional investment list/details badges for last fetch error and last success

- UX principles:
  - if provider has no required settings (e.g. symbol-only provider), do not render empty setting blocks
  - if provider requires credentials and missing, show clear actionable warning before enabling auto update

## API Shape (Draft)

- Investment payload additions:
  - `provider_settings` object

- Investment provider settings endpoints:
  - `PATCH /api/v1/investments/{investment}/provider-settings`
  - `DELETE /api/v1/investments/{investment}/provider-settings` (clear all settings)

- New provider config endpoints:
  - `GET /api/v1/investment-provider-configs`
  - `GET /api/v1/investment-provider-configs/{providerKey}`
  - `PATCH /api/v1/investment-provider-configs/{providerKey}`
  - `POST /api/v1/investment-provider-configs/{providerKey}/test`

- Provider availability endpoint (for compact forms):
  - `GET /api/v1/investment-price-providers/available`
  - returns only selectable providers for current user (with reason flags)

- Provider metadata endpoint enhancement:
  - include settings schemas and capability/rate-limit metadata for UI generation

## Processing Flow

1. User selects provider on investment.
2. Frontend fetches provider metadata and renders required fields.
3. User saves investment; backend validates `provider_settings` against provider schema.
4. User configures provider credentials once (per user/provider) when needed.
5. Scheduler preflight checks config availability (provider enabled, credentials available, required settings present).
6. Scheduler command groups investments by provider and computes dispatch budget by effective policy (default or user-tier override).
7. Jobs execute with provider-specific middleware limits and per-investment overlap protection.
8. Retrieval status fields are updated on attempt/success/failure.
9. UI can surface status to users for troubleshooting.

## Backward Compatibility

- Web scraping settings are stored in `provider_settings` only.
- Legacy scrape columns are removed after data backfill.
- Existing databases use explicit cleanup migrations for removed legacy columns/fields.

## Phased Delivery Plan

Status legend: [x] completed, [ ] pending.

### Phase 1: Foundation (Backend)

- [x] Add `provider_settings` JSON column and model cast.
- [x] Create `InvestmentProviderConfig` model+migration+policy.
- [x] Add API endpoints for provider config CRUD/test.
- [x] Extend provider registry metadata with schema placeholders and rate policies.
- [x] Add tests for model, policy, and endpoint authorization/validation.

### Phase 2: Runtime Generalization (Backend)

- [x] Implement context resolver and update retrieval flow to consume it.
- [x] Generalize command dispatch budgeting by provider policy.
- [x] Generalize job middleware rate-limiting by provider policy.
- [x] Add preflight config checks before enqueueing jobs.
- [x] Keep existing fetch state tracking, ensure all providers update consistently.
- [x] Add focused tests for:
  - grouped dispatch per provider,
  - budget capping,
  - middleware composition,
  - fallback behavior for missing credentials/settings.

### Phase 3: Investment Form Integration (Frontend + Backend)

- [x] Keep one-provider-per-investment selector and schema-driven settings wiring.
- [x] Add provider availability endpoint usage in selector.
- [x] Embed provider settings editor in investment form (schema-driven fields + test fetch).
- [x] Save/read `provider_settings` from investment API.
- [x] Implement backward-compatible fallback for legacy scraping fields.
- [x] Add feature tests for provider-specific validation and persistence.

### Phase 4: User Provider Settings UI (Frontend + Backend)

- [x] Add per-user provider credential management UI.
- [x] Add connection test action and user feedback.
- [x] Wire encrypted credential persistence via API endpoints.
- [x] Add tests for form behavior and API contracts.

### Phase 5: Migration Hardening and Cleanup

- [x] Backfill legacy scraping fields into `provider_settings`.
- [x] Remove legacy read fallback.
- [x] Drop `scrape_url` / `scrape_selector` columns.
- [ ] Update docs and changelog.

## Testing Strategy

- Unit tests:
  - provider metadata/schema contract
  - resolver behavior
  - rate-limit policy resolution

- Feature tests:
  - investment save with provider_settings validation
  - investment provider settings clear endpoint behavior
  - provider config CRUD and auth
  - command dispatch capping by provider
  - job middleware rate limiting composition

- Integration tests:
  - end-to-end retrieval for:
    - provider with no user credentials
    - provider with required user credentials
    - provider requiring custom investment settings

- Regression tests:
  - existing web scraping workflow remains functional during migration
  - existing Alpha Vantage behavior remains stable with per-user credential requirements

## Reliability and Security Notes

- Never expose decrypted credentials in API responses.
- Enforce strict ownership for provider config endpoints.
- Gracefully degrade when provider config is missing:
  - track failure in investment retrieval status,
  - return actionable error message.
- Preflight validation failures must not enqueue retrieval jobs.
- Keep rate-limiter bucket keys provider-scoped and shared across workers.

## Acceptance Criteria

- A provider can be added without adding provider-specific columns to investments.
- A provider can require user credentials without using global `.env` keys.
- Command/job rate-limiting behavior is provider-policy driven, not hardcoded per provider in multiple places.
- Existing one-provider-per-investment model remains intact.
- Existing investments continue to work during migration window.
- Provider settings and credentials are validated, secure, and covered by automated tests.
