# QIF/CSV Import — Architecture

## What This Is

A backend-driven pipeline that lets a user upload a QIF or CSV bank export, parses it server-side into a list of draft transactions, enriches each draft with payee/duplicate/AI-document matches, and returns the drafts to the browser for review. Nothing is persisted at parse time — the user finalizes each draft one at a time through the **existing** transaction-creation form/endpoint (`store-standard`), the same one used by manual entry and AI document processing. This document describes the feature **as implemented** on `feat/qif-csv-import`, not as originally planned; see `SPECIFICATION.md` for the pre-implementation design and "Related Documents" below for drift notes.

## Tech Stack Pieces Involved

- **Laravel 12 controllers/services**: `App\Http\Controllers\API\ImportApiController`, `App\Http\Controllers\API\FileImportProfileApiController`, `App\Http\Controllers\ImportController` (web shell)
- **Parsers**: `App\Services\Import\CsvParserService` (league/csv), `App\Services\Import\QifParserService` (hand-written line parser)
- **Normalization/enrichment**: `App\Services\Import\ImportNormalizationService`, `App\Services\Import\ImportDuplicateDetectionService` (wraps the pre-existing `App\Services\DuplicateDetectionService`; also scores **scheduled-transaction** candidates via the same engine — see below)
- **AI**: `App\Services\Import\AiImportProfileSuggestionService` via the Prism PHP package (`config/prism.php`), using the requesting user's own `AiProviderConfig` (own API key, `encrypted` cast)
- **Persistence**: `file_import_profiles` table (`App\Models\FileImportProfile`) + `account_entities.preferred_file_import_profile_id` FK. No table stores parsed drafts or import history.
- **Authorization**: `App\Policies\FileImportProfilePolicy`, `App\Policies\ImportPolicy` (Laravel Gate, code-enforced — see `permissions.md`)
- **Frontend**: Vue 3 Options API island mounted on `resources/views/import/index.blade.php`, entry `resources/js/import/index.js`, top component `ImportPage.vue`, with `ImportUploadCard`, `ImportDraftTable`, `FileImportProfileManager`, `ProfileCreationWizard`, `DuplicateCandidatesPanel`, `ScheduleCandidatesPanel`, `RelatedAiDocumentsPanel` as sub-islands. Not an SPA — this page is one island among many, and finalization hands off to the pre-existing transaction-create Vue island via a `window` `CustomEvent` (`initiateCreateFromDraft` / `transaction-created`), not a direct API call from this feature's code. Entering a matched scheduled instance instead uses the pre-existing `initiateEnterInstance` / `transaction-created` event pair (the same one the account schedule table and dashboard schedule calendar already use), consumed by the already-mounted `ModalStandard.vue`.
- **Deploy-time sync**: `App\Console\Commands\SyncSystemFileImportProfilesCommand` (`app:import:sync-system-profiles`), run from `docker/entrypoint.sh:17` on every container start.

## Request/Response Flow

### Upload → Parse → Enrich → Respond (no persistence)

1. Browser reads the file via a normal `<input type=file>` / `FormData` upload (not read client-side beyond that) and POSTs multipart to `POST /api/v1/imports/parse` with `file`, `source_type`, `account_id`, optional `file_import_profile_id` — `ImportApiController::parse()` (`app/Http/Controllers/API/ImportApiController.php:38`).
2. `ImportParseRequest` validates file type/size (`app/Http/Requests/ImportParseRequest.php`), then the controller authorizes `import.parse` on the target `AccountEntity` (`ImportPolicy::parse`, `app/Policies/ImportPolicy.php:10`) — ownership check, not just a form-request rule.
3. CSV path: resolves a `FileImportProfile` (explicit `file_import_profile_id` or the account's `preferred_file_import_profile_id`), each resolution path re-checks `Gate::authorize('view', $profile)`. QIF path: same profile resolution, applied via `QifParserService::applyProfile()`.
4. `CsvParserService::parseFile()` / `QifParserService::parseFile()` read the file server-side (`file_get_contents` on the `UploadedFile`'s real path), normalize encoding to UTF-8, and enforce a row cap (`config('yaffa.import_max_rows')`) by throwing a `RuntimeException` once exceeded.
5. `ImportNormalizationService::enrichDraftsWithPayeeMatches()` fuzzy-matches payee text against the user's own payees; `ImportDuplicateDetectionService::enrichDrafts()` scores drafts against the user's own transactions within a date window; `ImportDuplicateDetectionService::enrichDraftsWithScheduleCandidates()` scores drafts against the user's own schedule-owning transactions (`schedule = true`, with an active `TransactionSchedule`) using the **same scoring engine and settings** as duplicate detection, but windowed/compared against the schedule's `next_date` instead of the transaction's `date` (`DuplicateDetectionService::findDuplicatesFromWindow()`'s new optional `$candidateDateResolver` closure parameter); `ImportNormalizationService::enrichDraftsWithRelatedAiDocuments()` scores drafts against the user's own `ready_for_review` AI documents.
6. Controller returns a JSON DTO (`drafts[]`, `warnings[]`, `summary`) — nothing is written to the database in this request.

### Finalize (existing endpoint, no import-specific code)

The Vue draft table dispatches a `initiateCreateFromDraft` browser event with a payload shaped like the standard transaction-create form; the existing transaction-create island (not part of this feature) listens for it and POSTs to `POST /api/v1/transactions/store-standard`, the same endpoint used for manual entry. This feature does not add or modify any transaction-write endpoint — see `flows.md` for the sequence.

### Enter a matched scheduled instance (existing endpoint/event, no import-specific write code)

`ScheduleCandidatesPanel.vue`'s "Enter this scheduled transaction" button fetches the full schedule-owning transaction (`GET /api/v1/transactions/{id}`, the same read endpoint `DuplicateCandidatesPanel` already uses), forces `schedule = false` / `budget = false` / `date = transaction_schedule.next_date` on the in-memory copy, and dispatches the pre-existing `initiateEnterInstance` event — the identical mechanism the account schedule table and dashboard schedule calendar already use to enter a due schedule instance. The already-mounted `ModalStandard.vue` opens with `action: 'enter'` and POSTs to `store-standard`; `TransactionApiController::handleSourceTransactionUpdates()` (unchanged) then advances the source schedule's `next_date` via `TransactionSchedule::skipNextInstance()`. No import-specific write path exists for this flow — it is pure reuse of infrastructure that predates this feature.

### Profile CRUD (separate from parsing)

`FileImportProfileApiController` exposes `index`/`store`/`update`/`destroy`/`suggest` under `/api/v1/imports/file-profiles*`. `store`/`update` force `type`, `user_id`, and (on update) `file_type` to be server-controlled (`FileImportProfileRequest`, `'user_id' => ['prohibited']` etc., `app/Http/Requests/FileImportProfileRequest.php:36`), so a client cannot mint a `system`-typed or other-user-owned profile through this endpoint.

### System profile sync (deploy-time)

`docker/entrypoint.sh:17` runs `php artisan app:import:sync-system-profiles --no-interaction` on every container start. The command reads a hardcoded PHP array from `SystemFileImportProfileRegistry::profiles()` and upserts by `key` (`app/Console/Commands/SyncSystemFileImportProfilesCommand.php`). There is no admin UI or API to create/edit system profiles — the only way to add one is to edit the registry class and redeploy.

## Trust Boundaries

| Boundary | Where | Notes |
|---|---|---|
| Browser → server (file upload) | `POST /api/v1/imports/parse`, `POST /api/v1/imports/file-profiles/suggest` | File is never parsed in the browser; the server reads it via `UploadedFile::getRealPath()` + `file_get_contents()` (`CsvParserService.php:30`, `QifParserService.php:60`). Size capped by `config('yaffa.import_max_file_size_mb')`; row/entry count capped by `config('yaffa.import_max_rows')`. |
| Server → AI provider | `AiImportProfileSuggestionService::callAiProvider()` (`app/Services/Import/AiImportProfileSuggestionService.php:118`) | Sends up to the first 10 data rows of the uploaded CSV (`MAX_SAMPLE_DATA_ROWS`, line 22) — real bank transaction dates/amounts/payee names — to whichever provider/model/API key the requesting user configured in their own `AiProviderConfig`. Each user's own key is used (`FileImportProfileApiController::suggest()`, line 111); one user's data is never sent using another user's key. |
| Server → DB | Throughout | `FileImportProfile` CRUD, `AccountEntity.preferred_file_import_profile_id` writes, and read-only queries against the user's own `transactions` (both plain and schedule-owning), `payees`, `ai_documents` during enrichment (all scoped by `user_id` — see `permissions.md`). |
| Browser (draft display) → server (duplicate/schedule detail lookup) | `DuplicateCandidatesPanel.vue`/`ScheduleCandidatesPanel.vue` call `GET /api/v1/transactions/{id}` on demand | Reuses the pre-existing transaction-show endpoint (out of scope of this feature's new code, but is how far a candidate's summary can be inspected, or a schedule instance entered, from the import UI). |

## Known Risks / Assumptions

- **No rate limiting on `/imports/parse` or `/imports/file-profiles/suggest`.** These are the two most expensive endpoints in the app (file parsing up to `import_max_rows`, or a paid outbound AI call), and `routes/api.php` applies no `throttle` middleware to any API route; `bootstrap/app.php:21-39` does not attach a throttle middleware to the `api` group either. This is not specific to this feature — it's an app-wide gap — but this feature is the first to pair user-uploaded files with an outbound paid AI call, which raises the cost of abuse. (`routes/api.php:135-147`, `bootstrap/app.php:29-30`)
- **System-profile `matching_rules` support a `matches_regex` operator that runs user-authored regex via `preg_match` with no complexity guard** (`CsvParserService.php:467`, `matchesSingleCondition`). Today this is safe because `options_json.matching_rules` is `prohibited` for user-submitted profiles (`FileImportProfileRequest.php:59`) — only the hardcoded `SystemFileImportProfileRegistry` can populate it. If that constraint is ever relaxed (e.g. to let power users write matching rules), this becomes a ReDoS surface.
- **`IMPORT_MAX_FILE_SIZE_MB` and `IMPORT_MAX_ROWS` are implemented but undocumented** — they exist in `config/yaffa.php:12-13` and are read in `ImportParseRequest`, `SuggestFileImportProfileRequest`, `CsvParserService`, `QifParserService`, but neither is listed in `.env.example`. Operators will not know these are tunable. See `variables.md`.
- **Draft finalization has no server-side idempotency guard.** `SPECIFICATION.md` explicitly punts this to the frontend ("disable the finalize button after first click"); the implementation does exactly that (`ImportPage.vue` tracks `finalizingDraftIndex`) and nothing server-side prevents a double-submit from creating two transactions (this is inherited from the pre-existing `store-standard` endpoint, not new).
- **`ImportPolicy::parse` and duplicate/related-document enrichment assume `$request->user()` is always present** because middleware requires `auth:sanctum` + `verified` — not re-verified per file.
- **Entering a schedule instance from one draft does not refresh `schedule_candidates` on other drafts in the same parsed batch.** `TransactionSchedule::skipNextInstance()` advances `next_date` server-side at the moment of entry, but the browser's in-memory draft list was scored once, at parse time. If two lines in the same imported statement both plausibly match the same recurring schedule, entering it from one draft leaves the (now stale) `next_date` in the other draft's candidate summary until the file is re-parsed. This is an accepted, explicitly-requested limitation, not a bug.
- **The "enter" button only works end-to-end for `config_type = standard` schedule candidates.** `ScheduleCandidatesPanel` dispatches `initiateEnterInstance` regardless of the candidate's `config_type`, but `resources/views/import/index.blade.php` only mounts `<transaction-create-standard-modal>` (no investment equivalent) — this mirrors the pre-existing `onFinalizeDraft()` limitation (`ImportPage.vue` hardcodes `type: 'standard'` for `initiateCreateFromDraft`, too). An investment-type schedule can still be *scored and displayed* as a candidate; clicking "enter" for one currently has no visible effect because no listener is mounted on this page. Not introduced by this change — inherited from the page's existing scope.

## Related Documents

- `SPECIFICATION.md` — pre-implementation design; `flows.md` and this document call out where the shipped code diverges from it.
- `flows.md` — step-by-step sequences for parse (CSV/QIF), profile CRUD, AI suggestion, finalize, and deploy-time sync, with the authz check at each protected step.
- `permissions.md` — resource × operation × role matrix for `FileImportProfile` and the account preferred-profile FK.
- `variables.md` — every env var actually read by import code, with the `IMPORT_MAX_*` documentation gap called out.
- `tests.md` — not yet produced; will be added by `/derive-tests`.
