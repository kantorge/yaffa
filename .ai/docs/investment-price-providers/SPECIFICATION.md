# Investment Price Providers

## Feature Summary

Introduce a flexible investment price provider architecture that supports:

- one selected provider per investment,
- provider-specific investment settings without schema churn,
- optional per-user provider credentials (API keys),
- provider-specific rate-limiting and dispatch behavior,
- robust async retrieval in parallel queue environments.

The goal is to replace provider-specific hardcoded fields and hardcoded throttling logic with a small, reliable, extensible system that remains easy to operate.

## Goals / Non-Goals

Goals:

- Keep one provider per investment.
- Store provider-specific investment settings in a generic JSON structure.
- Support per-user provider credentials for API-based providers.
- Drive rate limits for command dispatch and job execution from provider policy, not from hardcoded branching.
- Preserve retrieval state tracking per investment regardless of provider.
- Keep the migration path backward compatible for existing users.

Non-Goals:

- Multiple simultaneous providers per investment.
- Provider marketplace or plugin system.
- Historical credential versioning or rotation audit trail.
- Automatic fallback from one provider to another within a single retrieval attempt.
- Public provider sharing between users.

## Target Architecture

### 1. Provider Selection (per investment)

Each investment has exactly one selected provider. Provider-specific settings are stored as a JSON object on the investment, keyed by the provider. No provider-specific columns are added to the investments table.

### 2. Provider Credentials (per user)

Provider credentials are stored per user and per provider key. Sensitive values (API keys, tokens) are encrypted at rest. A user may have at most one credential record per provider. Credentials are optional for providers that do not require authentication.

### 3. Provider Metadata Contract

Each provider self-describes its requirements through a metadata contract that includes:

- display name and description,
- an investment settings schema that defines what fields the provider requires on the investment,
- a user settings schema that defines what credentials the provider requires from the user,
- a rate-limit policy defining the provider's safe call limits,
- an optional capability flag indicating whether the provider supports full historical price retrieval.

The investment settings schema is the authoritative source for validating provider-specific investment fields. The user settings schema is the authoritative source for validating provider credentials. The backend always validates against these schemas; frontend validation is advisory.

### 4. Resolver Layer

A resolver service accepts an investment and its owner, and returns the fully resolved context for a retrieval run: the provider implementation, validated investment settings, user credentials, and effective rate-limit policy. All retrieval flows consume this resolver to avoid duplicated conditional branching.

### 5. Rate Limiting

Rate limits are driven by provider policy rather than hardcoded per-provider logic. The dispatch command groups investments by provider and computes a daily budget before enqueueing jobs. For the purposes of daily budget calculation, usage is counted by the number of investments that have run with a given provider on the same calendar day, not by exact API request count.

The effective rate-limit policy is resolved from the provider's defaults combined with any per-user overrides stored in the user's provider config. Overrides are validated against provider-allowed bounds. Per-investment overlap protection is enforced independently of provider-level throttling.

### 6. Provider Availability and Eligibility

By default the investment form shows all providers that are available for the current user. There's a clear indication, if a provider is available but not yet configured.

### 7. Retrieval State

Each investment tracks the timestamp and outcome of the last retrieval attempt regardless of provider. These fields are updated consistently by all providers and are used for fairness scheduling and user-facing diagnostics.

## Investment Form Behavior

- The provider selector is single-choice.
- When a provider is selected, the form renders only the fields defined by that provider's investment settings schema.
- If a provider has no required settings, informational guidance is shown instead of an empty editor.
- If a provider requires credentials and they are missing, a clear actionable warning is shown before the investment can be enabled for automatic updates.
- A test-fetch action in the settings panel lets the user verify the current configuration before saving.
- The provider status is shown as a compact icon with a detailed tooltip.

## User Provider Settings Behavior

- Users manage credentials per provider through a dedicated settings section.
- A connection test action is available to verify credentials before they are relied upon.
- Existing provider configs can be removed with a confirmation step.
- When a provider policy permits it, users may supply rate-limit overrides for their own key tier.

## Processing Flow

1. User selects a provider on the investment and fills in any required provider settings.
2. The backend validates the provider settings against the provider's investment settings schema on save.
3. When needed, the user configures credentials once in their provider settings.
4. Before enqueueing retrieval jobs, a preflight check confirms that required credentials and settings are present.
5. The dispatch command groups investments by provider and caps the batch size to the effective daily budget.
6. Jobs execute with provider-specific rate-limit middleware and per-investment overlap protection.
7. Retrieval state fields are updated on attempt, success, and failure.
8. The UI surfaces the retrieval status to users for troubleshooting.

## Backward Compatibility

Provider-specific investment settings that were previously stored in dedicated columns are migrated into the generic JSON settings structure. Legacy columns are removed after the data is backfilled. Existing investments continue to work without manual user intervention.

## Reliability and Security

- Decrypted credentials must never be exposed in API responses.
- Provider config endpoints enforce strict ownership; users may only access their own records.
- When provider config is missing or invalid, the retrieval failure is logged in the investment's retrieval state and a descriptive error is recorded.
- Preflight failures must prevent jobs from being enqueued.
- Rate-limiter buckets are provider-scoped and shared across all queue workers.

## Acceptance Criteria

- A provider can be added without adding provider-specific columns to the investments table.
- A provider can require user credentials without relying on global environment keys.
- Rate-limiting behavior is driven entirely by provider policy, not by hardcoded per-provider branching.
- Exactly one provider per investment is enforced.
- Provider settings and credentials are validated server-side and protected by authorization checks.
