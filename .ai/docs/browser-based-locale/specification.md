# Browser-Based Locale Specification

## 1. Purpose

Define a browser-based locale feature for YAFFA that allows users to choose whether date, number, and currency formatting should follow:

- a manually selected locale, or
- the browser locale sent by the client.

This document is self-contained and intended as an implementation handoff for a coding agent.

## 2. Goals

- Add a new user setting for locale source.
- Keep current locale behavior fully backward compatible.
- Ensure frontend and backend use one effective locale value consistently.
- Avoid changing UI translation language behavior in this phase.

## 3. Non-Goals

- Do not add browser-based language selection.
- Do not redesign all i18n helpers in one step.
- Do not change available locale list structure in config.

## 4. Current Constraints

- Supported locales are configured in `config/app.php` under `available_locales`.
- User currently stores a single `locale` value in `users.locale`.
- Many frontend components read `window.YAFFA.userSettings.locale`.
- Some legacy frontend code still reads `window.YAFFA.locale`.

## 5. Functional Requirements

### FR-1: New Locale Source Setting

Add a persisted user setting `locale_source` with allowed values:

- `manual`
- `browser`

Default value for existing and new users: `manual`.

### FR-2: Effective Locale Resolution

The application must resolve an effective locale for each request using the following order:

1. If authenticated user exists and `locale_source = browser`, resolve from browser `Accept-Language` header against supported locales.
2. If step 1 fails or no supported match, use `users.locale`.
3. If user does not exist, keep existing guest behavior.
4. Final fallback: app default locale.

### FR-3: Matching Rules

When resolving from browser header:

1. Parse language tags by priority (q-values).
2. Try full locale match first (example: `fr-FR`).
3. If no full match, try language-only fallback to first supported locale with same language (example: `fr-CA` -> `fr-FR`).
4. If still no match, fail resolution and use fallback chain.

Normalization rules:

- Treat `-` and `_` as equivalent for comparison.
- Matching is case-insensitive.
- Effective locale output must use canonical config key format (example: `fr-FR`).

### FR-4: Registration UI and Persistence

In registration form:

- Add locale source input with values `Manual` and `Browser locale`.
- Keep existing locale selector as manual fallback value.
- If locale source is `browser`, still submit/store selected manual locale as fallback.

Validation:

- `locale_source` required, in `manual,browser`.
- `locale` remains required and must be in supported locales.

User creation:

- Persist both `locale_source` and `locale`.

### FR-5: User Settings UI and API

In user settings screen:

- Add locale source selector with `Manual` and `Browser locale`.
- Keep locale selector for manual fallback.
- Save both fields via existing settings endpoint.

API response after save must include:

- `locale_source`
- `locale` (stored manual fallback)
- `effective_locale` (resolved for the current request)

The frontend must update global state so formatting utilities use the effective locale immediately after save.

### FR-6: Backend Payload to Frontend

Global JS payload must include:

- `YAFFA.userSettings.localeSource` (stored source)
- `YAFFA.userSettings.locale` (effective locale for current request)
- `YAFFA.userSettings.manualLocale` (stored manual fallback)

Backward compatibility:

- Keep `YAFFA.userSettings.locale` available for existing components.
- Optionally keep/update legacy root `YAFFA.locale` for compatibility during migration.

### FR-7: Scope of Locale Influence

Browser-based locale applies only to locale-sensitive formatting concerns:

- number formatting
- currency formatting
- date formatting
- chart locale where applicable

It must not alter persisted user language or translation language in this phase.

## 6. Data Model Changes

### Users Table

Add column:

- `locale_source` VARCHAR(16) NOT NULL DEFAULT `manual`

Allowed values at application level:

- `manual`
- `browser`

Update model fillable and relevant docs/types.

## 7. Backend Components to Update

- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Requests/UserRequest.php`
- `app/Http/Controllers/API/UserApiController.php`
- `app/Http/View/Composers/JavaScriptVariablesComposer.php`
- `app/Models/User.php`
- New locale resolution service/class (recommended).

`SetLocale` middleware should keep language behavior unchanged for this phase.

## 8. Frontend Components to Update

- `resources/views/auth/register.blade.php`
- `resources/js/user/UserSettings.vue`
- Frontend bootstrapping and i18n helper call sites that currently mix `YAFFA.locale` and `YAFFA.userSettings.locale`.

Primary rule for this phase:

- Existing consumers should continue to work if they read `YAFFA.userSettings.locale`.

## 9. Validation and Error Handling

- Invalid `locale_source` must return validation error.
- Invalid or unsupported browser locale header must not fail request.
- In unsupported browser locale cases, use manual locale fallback silently.

## 10. Security and Privacy

- `Accept-Language` is request metadata; do not persist full header.
- Persist only `locale_source` and manual fallback `locale`.
- Do not expose raw request headers to frontend payload.

## 11. Testing Requirements

### Backend Tests

Add or update tests for:

- registration with `locale_source=manual`
- registration with `locale_source=browser`
- user settings update endpoint validation for `locale_source`
- effective locale resolution from `Accept-Language`
- fallback to manual locale when browser locale unsupported
- JS composer payload contains `localeSource`, `manualLocale`, and effective locale

### Frontend/Integration Tests

Add or update tests for:

- user settings form shows locale source selector
- save flow updates global locale state correctly
- at least one representative chart/widget uses effective locale

## 12. Acceptance Criteria

All criteria must pass:

1. Existing users continue to see unchanged locale behavior by default.
2. User can select `Browser locale` in registration and user settings.
3. Formatting changes according to browser locale when supported.
4. Unsupported browser locale falls back to stored manual locale.
5. No regression in language selection/translation behavior.
6. Existing frontend code paths relying on `userSettings.locale` continue working.
7. Automated tests for new behavior pass.

## 13. Rollout Plan

1. Add migration and model/request/controller support.
2. Add locale resolution service and composer payload updates.
3. Add registration and user settings UI changes.
4. Keep compatibility fields for legacy frontend references.
5. Add tests and run focused suite.
6. Optionally follow up with cleanup task to remove legacy `YAFFA.locale` usage.

## 14. Open Questions

Decisions required before implementation finalization:

1. Should guest users also support browser-based locale formatting globally, or remain unchanged?
2. Should API clients be allowed to pass explicit locale override headers beyond `Accept-Language`?
3. Should locale source selection show helper text explaining fallback behavior?
