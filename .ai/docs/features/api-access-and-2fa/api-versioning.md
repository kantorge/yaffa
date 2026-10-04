# API versioning and breaking-change policy (`/api/v1`)

The OpenAPI document is generated from the code with Scramble (`php artisan scramble:export`, served at `/docs/api`).
CI (`.github/workflows/openapi-spec.yml`) regenerates it on relevant PRs and attaches it to each published release as
`openapi.json`; its `info.version` is the YAFFA release version. Generated clients (for example the Kotlin app client)
should be built from the spec attached to the release they target.

## Error envelope

Every `api/v1` error response is `{"error": {"code": "UPPER_SNAKE_CODE", "message": "..."}}` (schema `Error`),
rendered centrally in `bootstrap/app.php`. 422 responses keep Laravel's `message` and `errors` map as well
(dotted field keys such as `items.0.category_id`). Codes: `UNAUTHENTICATED` 401, `FORBIDDEN` 403, `NOT_FOUND` 404,
`METHOD_NOT_ALLOWED` 405, `CONFLICT` 409, `VALIDATION_ERROR`/`FILE_TOO_LARGE`/`IDEMPOTENCY_KEY_*` 422,
`PAYLOAD_TOO_LARGE` 413, `TOO_MANY_REQUESTS` 429. Controllers add their own domain codes (for example `AI_DISABLED`).
Document a new code where it is introduced; never repurpose or rename one.

## What counts as breaking (not allowed within `v1`)

- Removing or renaming a route, a request field, a response field or an error code.
- Making an optional request field required, or tightening its validation.
- Changing a field's type or meaning (for example number to string; money and quantity fields are already decimal strings).
- Changing the status code of a success or documented error response.
- Removing a value from an enum, or changing authentication or ability requirements to be stricter.

## Allowed in `v1` (additive)

- New routes, new optional request fields, new response fields, new enum values, new error codes.
- Clients must therefore ignore unknown response fields and treat unknown enum values and error codes as generic.

## Making a breaking change

1. Prefer an additive alternative (new field or route, keep the old one working).
2. Otherwise ship it under `/api/v2`, keep `v1` running for at least one major release, and list it under the major
   version in `UPGRADE.md`.
3. Deprecations are announced in `UPGRADE.md` and in the spec (`deprecated: true`) at least one minor release before removal.

`GET /api/v1/meta` reports `api_version` and `min_app_version`; raise `MOBILE_APP_MIN_VERSION` when a server change
makes older app builds unusable.
