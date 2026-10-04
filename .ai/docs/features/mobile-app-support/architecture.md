# Mobile app support (server side)

The Android companion app talks only to the user's own instance through `api/v1`, with one full-access
Sanctum token per device. This documents what the server provides for it. Not yet built: the push
sender itself (UnifiedPush channel, optional FCM relay); endpoints and notifications are in place for it.

## Authentication

- **Token header.** `AppServiceProvider::boot()` registers `Sanctum::getAccessTokenFromRequestUsing()`: `X-Yaffa-Token`
  wins, `Authorization: Bearer` is the fallback. This lets a proxy keep `Authorization` for nginx `auth_basic`.
- **Route audit.** Middleware is declared per controller, not on the `v1` group, so a controller that forgets
  `#[Middleware('auth:sanctum')]` is open. `tests/Feature/API/V1/ApiRouteAuthTest.php` walks every `api/v1` route:
  anonymous = 401 `UNAUTHENTICATED`, token without a session = not 401. Deliberately public routes are listed in
  `PUBLIC_API_ROUTES` in that test (currently `api.v1.meta`).
- **`/maintenance/*`.** Reachable by a full-access device token by decision (they keep their `write`/`settings`
  gates). That includes `cleanup-ai-document-old-files`, which irreversibly deletes finalized documents.

## Pairing

`POST /api/v1/users/me/tokens/pairing {name}` (session only, blocked in sandbox mode) creates a `read,write,settings`
token via `ApiTokenService::createPairing()` and returns, once: `token`, `deep_link`
(`yaffa://pair?v=1&url=<APP_URL>&token=<token>`), `qr_svg` (`bacon/bacon-qr-code`), and `warnings`
(`not_https`, `localhost`, computed from `APP_URL`). UI: "Connect mobile app" in `ApiTokenManager.vue`.
Revoking the token (existing endpoint) makes the app's next call return 401.

## Server info

`GET /api/v1/meta` is public. Anonymous: `yaffa_version`, `api_version`, `min_app_version`
(`MOBILE_APP_MIN_VERSION`), `token_header`, `features`. With a valid token it adds `user`: `base_currency`, `locale`,
`max_upload_mb`, `max_files_per_submission`. Date format is not sent: it is derived from `locale` client-side.

## Retry safety

`EnsureIdempotent` (alias `idempotent`) is applied to `documents.store` and `transactions.store-standard`.
Records live in `idempotency_keys` (unique per user + route + key), pruned daily after
`IDEMPOTENCY_RETENTION_DAYS` (default 7).

- Same key and same request: the stored 2xx response is replayed with `Idempotent-Replayed: true`; the controller,
  its events and side effects do not run again.
- Same key, different request: 422 `IDEMPOTENCY_KEY_REUSED`. Original still running: 409
  `IDEMPOTENCY_REQUEST_IN_PROGRESS` (a claim older than 10 minutes is treated as crashed and released).
- Non-2xx responses and exceptions are not remembered, so the client can fix the request and retry with the same key.
- Multipart requests are compared by file name, size and content hash, not the raw body.

## Document upload

- Accepted types come from `AI_DOCUMENT_ALLOWED_TYPES` (default `txt`; the app needs `jpg,jpeg,png,pdf`).
  Multi-page = a PDF, or up to `AI_DOCUMENT_MAX_FILES_PER_SUBMISSION` images.
- New optional fields: `source` (`mobile_scan`|`mobile_share`, stored as `source_type`), `captured_at`, `note`.
- `GET /documents?updated_since=` filters on `updated_at`; `per_page` is capped at 100.
- Stored file names get an index prefix so same-named files cannot overwrite each other.
- **Limits.** `docker/php-uploads.ini` sets `upload_max_filesize=20M`, `post_max_size=100M` (5 x 20 MB). They must be
  raised together with `AI_DOCUMENT_MAX_FILE_SIZE_MB` / `..._MAX_FILES_PER_SUBMISSION`. `UploadLimitService::maxFileMb()`
  returns the lower of the app and PHP limits; it drives the validation message, `/meta`, and the 413 body.
  Reverse proxies have their own limit (nginx `client_max_body_size` defaults to 1 MB).

## Error envelope

`bootstrap/app.php` renders every `api/v1/*` error as `{error: {code, message}}`: 401 `UNAUTHENTICATED`, 403
`FORBIDDEN`, 404 `NOT_FOUND`, 413 `PAYLOAD_TOO_LARGE` (+`limit_mb`), 422 `VALIDATION_ERROR`, 429 `TOO_MANY_REQUESTS`,
other HTTP errors `HTTP_ERROR`/`BAD_REQUEST`/`METHOD_NOT_ALLOWED`/`CONFLICT`. Laravel's `message` (and, for 422,
the `errors` map with dotted keys such as `items.0.category_id`) stay alongside it for backward compatibility. An
oversize upload is 422 `FILE_TOO_LARGE` with `limit_mb`.

## Mobile payment notifications (text-only documents)

`POST /documents` with `source=mobile_notification`, `source_app`, `text` (required), `title`, `posted_at`, and no file.
`posted_at` is stored as `captured_at`. The text is written in the same `Subject/From/Date/---/body` layout a forwarded
email gets (`App\Support\SourceTextFormatter`, shared with `CreateAiDocumentFromSource`), as a `.txt` document file, and
processed by the normal `AiProcessingJob`. For this source `EnsureIdempotent` matches on the key alone, so the bank
app updating and re-posting a notification with changed text replays the original document instead of failing or
creating a second one.

**Duplicates.** After successful processing the job stores `processed_transaction_data.duplicate_candidates =
{transactions, documents}` (`DuplicateDetectionService::findForDocument`). Documents are compared on date window,
amount (two documents with clearly different amounts are never duplicates) and matched accounts, within the user's
duplicate settings. `POST /documents/{id}/check-duplicates` also returns `document_duplicates`. Detection only reads:
it never modifies a transaction or another document. A later receipt scan is flagged against the earlier notification;
the earlier notification is not re-flagged retroactively.

## Notifications and devices

`AiDocumentProcessedEvent` / `AiDocumentProcessingFailedEvent` are turned into database notifications by
`NotifyAiDocumentStatus` (`ai_document.ready_for_review`, `ai_document.processing_failed`; "processed" and "needs
review" are the same transition today). Payload: `type`, `entity_type`, `entity_id`, generic `title`; no amounts, payees
or account names. `GET /notifications?since=&unread=&limit=` (read), `POST /notifications/{id}/read` and
`/notifications/read-all` (write). `POST/DELETE /devices` registers a UnifiedPush URL or FCM token bound to the calling
API token (`devices.personal_access_token_id`, cascade on revoke); session callers get 422 `TOKEN_REQUIRED`. Before a
sender is built, endpoints must pass `PublicEndpointUrlValidator` (SSRF).
`/meta` reports `features.notifications=true`, `features.push=false` until a sender exists.

## Reference data and summary

- `GET /reference-data[?updated_since=]` (read): accounts, payees, categories, tags, currencies. With `updated_since`
  only changed rows plus `ids` (all current ids) so deletions can be detected; accounts are always full (their settings
  table has no timestamps). `ETag` / `If-None-Match` gives 304; `server_time` is the next cursor.
- `GET /summary` (read): base-currency balances (reuses `AccountApiController::getAccountBalance`), current-month
  income and expense (non-scheduled standard withdrawals/deposits, `cashflow_value`), and budget status (active
  withdrawal budgets' occurrences this month vs spending in their categories and direct children). Decimal strings.
  Returns `{"result":"busy"}` while summaries recalculate.

## OpenAPI

See `.ai/docs/features/api-access-and-2fa/api-versioning.md` (spec generation, error envelope, breaking-change policy).
