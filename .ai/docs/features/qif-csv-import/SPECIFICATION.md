# QIF and CSV Import (MVP)

## Feature Summary

Introduce a backend-driven, synchronous transaction import pipeline for QIF and CSV files that creates reviewable draft transactions before any real transaction is saved.

The workflow keeps the current user control model:

- user uploads a file,
- system parses and normalizes rows/entries into draft transactions,
- system checks potential duplicates,
- user reviews each draft,
- user finalizes selected drafts through the existing transaction form flow,
- only finalized drafts become actual transactions.

This feature intentionally does not auto-create transactions directly from parsed files.

The import is treated as a one-off activity:

- uploaded source files are not persisted,
- no import history page is introduced,
- no long-term processing logs are introduced.

## Why This Architecture

Current CSV import is frontend-heavy and format-specific, with local parsing and rule logic tied to one script/rule engine combination.

For QIF and long-term CSV flexibility, parsing and normalization should be backend-owned because:

- parsing correctness is centralized and deterministic,
- duplicate detection can reuse existing backend logic and user thresholds,
- review/finalization contract remains identical for QIF and CSV,
- tests can cover parser edge cases deeply,
- format support can evolve without shipping parser logic in the browser.

Frontend remains responsible for interactive review UX, not financial parsing logic.

## Goals / Non-Goals

- Goals:
  - Add first-class QIF import with minimal user guesswork.
  - Move CSV parsing/normalization into the same backend pipeline.
  - Preserve review-first, user-finalized workflow.
  - Reuse existing transaction creation endpoints and validation.
  - Reuse existing duplicate detection logic and AI-related duplicate thresholds.
  - Support extensible CSV format mapping for advanced users.

- Non-Goals:
  - Auto-record transactions immediately after parsing.
  - Route QIF/CSV through AI document processing.
  - Build a universal, perfect parser for every malformed dialect in MVP.
  - Replace existing transaction form UX.
  - Introduce user-managed custom code execution for mapping/transforms.

## Assumptions

- Laravel 12 + MySQL are available.
- CSV and QIF imports are processed synchronously in the request lifecycle.
- Files in the expected size range (hundreds of KB to about 1-2 MB) are feasible for synchronous parsing on standard server hardware.
- Existing transaction APIs remain the canonical write path.
- Existing duplicate detection service remains source of truth for duplicate scoring.
- Import files are private to the owner and must follow existing auth/ownership constraints.
- QIF support starts with bank/cash style transaction sections (MVP); investment-specific QIF can be phased in later.
- Uploaded files are handled as temporary request input only and are not retained after parsing completes.
- All import amounts are treated as denominated in the currency of the selected target account. No currency conversion is applied during import.
- File size and row count limits for import are enforced via environment variables (e.g., `IMPORT_MAX_FILE_SIZE_MB`, `IMPORT_MAX_ROWS`). Parse requests that exceed these limits must be rejected with user-facing error messages suggesting batch splitting or profile adjustments.

## Backend Scope (Laravel)

- Models:
  - No new import batch/draft persistence models in MVP.
  - CsvImportProfile
    - Stores reusable CSV import profile definitions with `type = system|user`.

- Migrations:
  - No mandatory import batch/draft persistence migrations in MVP.
  - csv_import_profiles.

- Controllers / APIs:
  - ImportApiController (new)
    - Parse upload
    - Return normalized draft list and duplicate candidates

- Services / Jobs:
  - ImportOrchestratorService (new)
    - Coordinates parser + normalization + duplicate enrichment for response payload.
  - QifParserService (new)
    - Reads QIF into normalized intermediate records.
  - CsvParserService (new)
    - Uses selected CSV import profile configuration to normalize CSV rows.
  - ImportNormalizationService (new)
    - Converts parser output into transaction-form-compatible draft payload.
  - ImportDuplicateDetectionService (new adapter)
    - Calls existing DuplicateDetectionService with extracted fields.
  - SystemCsvImportProfileRegistry (new)
    - PHP array definitions of all system profiles; single source of truth for profile content.
  - SyncSystemCsvImportProfilesCommand (new Artisan command: `import:sync-system-profiles`)
    - Idempotent sync of registry entries to the database via `updateOrCreate` keyed on `key`.
  - No background parsing job in MVP.

- Policies / Auth:
  - Enforce authenticated ownership checks and resource access controls on import and profile endpoints.

- Events / Notifications:
  - No mandatory import events in MVP.
  - No email notification required for MVP.

## Frontend Scope (Vue + Bootstrap)

- Pages / Routes:
  - Import page evolves from CSV-only to source-aware flow (CSV or QIF).
  - Existing navigation entry remains Import transactions.

- Components:
  - ImportSourceSelector
  - ImportUploadCard
  - ImportDraftTable
  - ImportUnmatchedEntriesPanel
  - DuplicateCandidatesPanel
  - Reuse existing transaction display/create modal interactions.

- State management:
  - Page-local state only.
  - Parsed drafts are kept in page-local runtime state for review.

- API interactions:
  - Upload and parse file.
  - Receive parsed draft rows and duplicate candidates in response.
  - Finalize one draft via existing transaction creation contract.
  - Ignore draft without transaction creation (client-side review state).

- UX / validation rules:
  - No silent auto-record.
  - Every draft has explicit status: pending_review, ignored, finalized, failed_validation.
  - Show parser warnings per draft (date ambiguity, unsupported fields, normalization fallback).
  - Keep quick-view and duplicate warning patterns consistent with existing import UX.
  - Error transparency: parsing errors and warnings are displayed prominently in the review UI without losing valid drafts. Malformed entries are clearly labelled with the reason (e.g., "Invalid date format", "Unmatched column") and show the raw entry for manual correction.
  - On upload, provide field mapping validation feedback for CSV profiles before processing begins.

- Migration from legacy CSV import page:
  - The legacy import page will be replaced with the new unified QIF/CSV import flow in a single deployment step.
  - All existing functionality (auto-detect format, populate fields, etc.) is preserved in the new parser and profile system.

## Data and API Design

- Entities:
  - Runtime Import Parse Result (response DTO, not persisted)
    - source_type
    - summary
    - drafts[]
      - draft_index
      - raw_entry (raw text block of the original CSV row or QIF entry, for display and troubleshooting)
      - normalized_transaction (follows existing transaction form payload contract; key fields: date, amount, payee, comment, account_id, transaction_type; optional fields populated where source data permits)
      - warnings[]
      - duplicate_candidates[]
      - related_ai_documents[] (optional candidate list of AI Documents in state ready_for_review that likely represent the same purchase/receipt)
  - CsvImportProfile (persisted entity)
    - id
    - type (`system` or `user`)
    - user_id nullable
    - key nullable, required for system profiles
    - name
    - delimiter
    - has_header_row
    - date_format nullable (user profiles only)
    - decimal_separator nullable (user profiles only)
    - thousand_separator nullable (user profiles only)
    - sign_handling nullable (user profiles only)
    - mapping_json
      - for `type = system`: raw header aliases to canonical field names consumed by matching rules and actions
      - for `type = user`: direct source header aliases to canonical transaction fields used in mapping-only normalization
    - options_json
      - for `type = system`: matching_rules, action arguments, transforms, defaults, warnings, metadata, parser_settings
      - for `type = user`: normalization flags and parser options only
      - user profiles MUST NOT define `matching_rules`, `actions`, or custom transform catalogs
    - active
    - created_at, updated_at

- Existing model changes:
  - AccountEntity (account type only)
    - preferred_csv_import_profile_id nullable

- Relationships:
  - User has many CsvImportProfiles of type `user`.
  - AccountEntity belongs to preferred CsvImportProfile (nullable foreign key).

- Endpoints (draft):
  - POST /api/v1/imports/parse
    - Request: multipart/form-data; required fields: `file`, `source_type` (`qif` or `csv`), `account_id`; optional: `csv_import_profile_id` (CSV only; omit to use account preferred or application default)
    - Response: Runtime Import Parse Result DTO
  - CSV import profile endpoints:
    - GET /api/v1/imports/csv-profiles
    - POST /api/v1/imports/csv-profiles
    - PATCH /api/v1/imports/csv-profiles/{profile}
    - DELETE /api/v1/imports/csv-profiles/{profile}
  - Generic account update endpoint addition:
    - PATCH /api/v1/accounts/{accountEntity}
    - Allowed fields include `preferred_csv_import_profile_id`.

- Finalization flow:
  - Finalization uses existing transaction create endpoints via modal-based form workflow. (Refer to AI Document finalization flow for details.)
  - Import context remains transient on the client side.

## QIF Parsing Strategy

### Parser Decision

Implement QIF parsing in-house for MVP.

Reason:

- QIF grammar is simple enough for a deterministic line-based parser.
- Avoid adding abandoned dependencies for a critical financial input path.
- Full control over edge-case handling and normalization behavior.

External QIF library may be reconsidered later only if:

- actively maintained,
- recent releases,
- clear test quality,
- compatible with PHP 8.4 and Laravel 12 ecosystem standards.

### Dependency Decision (CSV and QIF)

- CSV parsing:
  - Use a maintained parsing library for low-level CSV reading/tokenization.
  - Recommended package: `league/csv`.
  - The parser library must correctly support multiline field values, quoted fields, delimiter characters inside quoted fields, and escaped quotes / doubled quotes inside quoted fields.
  - Keep import-domain behavior (mapping, normalization, matching, warnings) in application services.

- QIF parsing:
  - Use an in-house parser service in MVP.
  - Reason: no clear, widely adopted and actively maintained QIF package with strong long-term confidence for this project.
  - Scope the parser to supported markers and produce clear warnings for unsupported or ambiguous input.

- Package governance rule:
  - New parsing dependencies are accepted only when they are actively maintained, version-compatible, and covered by reliable tests.
  - If these criteria are not met, implement and test parser behavior in application code.

### QIF Rules for MVP

- Supported `!Type:` values:
  - `Bank` (bank account transactions)
  - `Cash` (cash account transactions)
  - `CCard` (credit card transactions)
  - Any other `!Type:` value (e.g., `Invst`, `Oth A`, `Oth L`) causes a non-blocking warning and skips the section entirely.

- `!Account` block handling:
  - Some QIF exporters prefix sections with an `!Account` header block declaring the account name and type.
  - The parser recognises and skips `!Account` blocks without error.
  - The declared account name may be captured in `raw_entry` for informational display but is not used for auto-assignment.

- Entry markers parsed per entry:
  - D (date)
  - T (amount)
  - P (payee)
  - M (memo)
  - L (category/class)
  - N (number/reference)
  - ^ (entry terminator)

- Split transaction handling (S / E / $ lines):
  - QIF split lines decompose a transaction into sub-categories. Some exporters include them alongside the top-level `T` amount.
  - For MVP: read the top-level `T` amount only; collect all S/E/$ lines in `raw_entry`; add a non-blocking warning indicating that split detail was not imported.
  - Users may add line items during finalization via the existing transaction form.

- Date handling:
  - Attempt the following patterns in order:
    - `YYYY-MM-DD` (ISO 8601)
    - `DD/MM/YYYY`
    - `MM/DD/YYYY`
    - `DD/MM/YY`
    - `MM/DD/YY`
    - `D MMM YYYY` (e.g., `1 Jan 2025`)
    - `D MMM YY` (e.g., `1 Jan 25`)
  - If the format is ambiguous (e.g., `01/02/2025` could be 1 Feb or 2 Jan), apply the first matching pattern and record a warning on affected entries.

- Amount handling:
  - Normalize sign and decimal format.
  - Preserve original raw value for troubleshooting.

- End-of-file without `^` terminator:
  - If the parser reaches EOF and the last entry has no `^` terminator, treat the entry as complete and add a non-blocking warning.

- Unsupported/unknown lines:
  - Keep in raw_entry.
  - Add non-blocking warning.

## CSV Flexibility Strategy

### MVP Baseline

- Backend CSV parser normalizes rows using CSV import profiles.
- CSV parsing must preserve valid multiline text fields and correctly parse standard CSV quoting variants handled by the underlying library.
- Provide one system profile equivalent to current CSV import behavior.

### Advanced User Support

- Model-driven mapping supports:
  - custom delimiter,
  - optional header row,
  - column-to-field mapping,
  - basic normalization options (trim, decimal separator, date format).

- Out of scope for MVP:
  - arbitrary user scripting,
  - regex-based custom code execution on server.

## CSV Import Profiles and Rules

### Conceptual Model

CSV import uses a single persisted profile model with two types:

1. System CSV Import Profiles

- `type = system`
- immutable, application-managed definitions shipped with the application,
- selectable by users,
- versioned and fully covered by automated tests,
- intended for bank/source-specific formats similar to the current Raiffeisen rule file.

2. User CSV Import Profiles

- `type = user`
- user-managed reusable configurations,
- may be created from scratch or cloned from a system profile,
- allow safe customization of parser settings and field mappings,
- do not allow arbitrary code execution.

- Users are not allowed to directly edit system CSV profiles
- allow users to create and edit custom user CSV profiles,
- allow accounts to store one preferred CSV import profile (user or system).

### Why Not User-Editable Full Rules

The current file-based rule engine in [resources/js/import/rules/hun_raiffeisen_v1.js](resources/js/import/rules/hun_raiffeisen_v1.js) contains:

- arbitrary condition structures,
- regex logic,
- field-level custom functions,
- bank-specific assumptions hardcoded in JavaScript.

This is acceptable for application-maintained code, but not appropriate as raw end-user configuration because it would:

- be difficult to validate safely,
- create substantial support/debugging burden,
- make backend behavior harder to guarantee and test,
- effectively introduce a custom programming surface.

Therefore, the recommended design is:

- one persisted model structure is used for both system and user entries,
- full system rule definitions are application-managed,
- user customization is limited to constrained user-owned profile entries.

MVP capability boundary:

- `type = system` profiles execute declarative rule matching and action pipelines.
- `type = user` profiles are mapping-oriented only and do not execute rule conditions/actions.
- Runtime parsing must branch by profile type.

### System CSV Import Profile Structure

Each system profile should have:

- key
  - stable identifier, for example `hun_raiffeisen_v1`
  - naming convention: `{format_identifier}_v{N}`, where N is incremented only on breaking changes; non-breaking updates may reuse the existing key
  - version information is embedded in the key; no separate version field is needed
  - required for system profiles, null for user profiles
  - unique for system profiles, and unique per user for user profiles
- name
  - user-facing name; may include a version reference, for example `Raiffeisen Hungary v1`
- metadata
  - country, institution, language, optional notes
  - stored in `options_json`; not a separate database column
- parser_settings
  - delimiter
  - has_header_row
  - header normalization rules
  - date parsing formats
  - decimal separator / thousand separator rules
  - encoding detection strategy and fallback list (no explicit per-profile fixed encoding field in MVP)
- mapping_json
  - source column/header aliases mapped to canonical fact names used by matching rules and actions
- matching_rules[]
  - ordered row-level rules used to classify a parsed row into a normalized transaction type
- defaults
  - default transaction config values when rule matches
- warnings
  - optional warning messages or conditions attached to parser output
- active
  - whether model is selectable

Currency is not a profile setting. Imported amounts are always interpreted in the selected account's currency as a global import behavior.

- For future versions, it can be an extension to parse the currency from the input file, and compare it to the currency of the selected account, but this is not required for MVP.

For `type = system`, these profiles are maintained programmatically or seeded and not exposed to user CRUD.
For `type = user`, the same structure is reused, but only safe editable fields are exposed to the user.

Normative capability rules:

- System profiles may include and execute `options_json.matching_rules`.
- User profiles must not include executable rule definitions.
- User profiles are limited to direct field mapping plus safe parser/normalization options.

### Recommended Rule DSL for System Profiles

Instead of reproducing the JavaScript rule engine literally, the backend should use a constrained declarative structure.

Minimal recommended structure:

- `conditions`
  - all / any groups
  - supported operators:
    - equal
    - in
    - matches_regex
    - starts_with
    - ends_with
    - contains
    - amount_sign_is
- `actions`
  - set static value
  - map source column to normalized field
  - apply built-in transform and assign output

Rule order is the order in `matching_rules[]`.
When multiple rules can match, the first matching rule wins.

Example conceptual action types:

- set `transaction_type = withdrawal`
- map `config.amount_from` from `amount` using `parse_localized_amount`
- map `date` from `Értéknap` using `parse_date_yyyy_mm_dd_dot`
- map `comment` from `Közlemény/2` when fallback payee was used

Built-in transforms should be whitelisted server-side and limited to the required MVP catalog below.

Custom closures/functions should not be stored in DB.

### JSON Config Runtime Contract (Sample-Aligned)

The file [sample-hun_raiffeisen_v1-profile.json](.ai/docs/qif-csv-import/sample-hun_raiffeisen_v1-profile.json) is the reference shape for MVP runtime behavior.

Processing stages and component usage:

1. Profile selection

- Import request selects a system or user profile.
- Parser loads top-level profile fields (`delimiter`, `has_header_row`, `mapping_json`, `options_json`).

2. Raw row normalization

- CSV row is read using profile parser settings (`delimiter`, header behavior, trim/skip-empty flags).
- Raw headers are canonicalized through `mapping_json`.
  - Example: `Közlemény/3` becomes canonical fact `notice_3`.
- Matching rules and actions reference canonical fact names, not raw source headers.

3. Rule matching

- For `type = system` profiles:
  - `options_json.matching_rules` is evaluated in array order.
  - First matching rule wins.
  - Each rule uses:
    - `conditions` to decide applicability,
    - `actions` to build `normalized_transaction` fields.
- For `type = user` profiles:
  - this stage is skipped and direct mapping is used.

4. Action execution

- For `type = system` profiles:
  - Action list is applied in-order inside the matched rule.
  - Supported action types in MVP:
    - `set`: assign static value to a target path.
    - `copy`: copy canonical fact value to a target path.
    - `map_transform`: read canonical fact, apply transform, assign result.
    - `apply_transform`: apply transform without direct source fact (context-driven).
    - `conditional_copy`: copy source only when condition is true.
- For `type = user` profiles:
  - no action execution stage exists in MVP.

5. Default and warning enrichment

- `options_json.defaults` is applied after actions for any still-missing optional fields.
- `options_json.warnings` provides reusable warning templates and profile-level warning metadata.

6. Output

- `normalized_transaction` is emitted in the parse response draft payload.
- Duplicate detection runs on normalized fields.

Design constraints:

- `mapping_json` is the canonicalization contract between source headers and rules.
- Currency is global import behavior, not a profile option.
- Profile JSON remains declarative and deterministic; no executable code.

### Required Built-In Transforms (MVP)

1. `parse_localized_amount`

- Input: canonical amount string (for example `-12 345,67`).
- Behavior: remove grouping separators, normalize decimal separator, parse numeric value.
- Supports options such as absolute value handling.
- No currency exchange is performed.

2. `parse_date`

- Input: canonical date string.
- Behavior: parse using explicit named format from args (for example `Y.m.d.`).
- Adds warning when parsing fails.

3. `extract_date_regex`

- Input: canonical text containing embedded date.
- Behavior: extract parts with configured regex and build normalized date.
- Adds warning when regex does not match.

4. `selected_account_context`

- Input: none (uses request context).
- Behavior: provides selected account object/id required by transaction payload.
- Used for either `config.account_from` or `config.account_to` depending on rule.

5. `resolve_payee_by_name_or_alias`

- Input: canonical text field.
- Behavior: resolve payee by exact match and alias match against user payees.
- Supports fallback payee name when no match is found.

6. `normalize_whitespace`

- Input: string field.
- Behavior: trim and collapse repeated whitespace.

7. `invert_sign`

- Input: numeric amount.
- Behavior: multiply by `-1` when source conventions require sign inversion.

8. `to_lowercase`

- Input: string field.
- Behavior: lowercase normalization for robust matching.

9. `to_uppercase`

- Input: string field.
- Behavior: uppercase normalization for robust matching.

Transform implementation requirements:

- Deterministic and side-effect free.
- Returns typed result or structured warning.
- Recoverable failures produce draft-level warnings instead of aborting full import.

### User CSV Import Profile Structure

User-owned profiles should be intentionally simpler in editable surface than system profiles.

Recommended persisted fields:

- id
- type = `user`
- user_id
- name
- delimiter
- has_header_row
- date_format nullable
- decimal_separator nullable
- thousand_separator nullable
- sign_handling nullable
- mapping_json
  - source column/header alias -> canonical field name used for direct mapping into normalized transaction fields
- options_json
  - trim strings
  - skip empty rows
  - lowercase/uppercase normalization where allowed
  - sign inversion toggle
  - comment concatenation options
  - must not include `matching_rules`, `actions`, or transform registry overrides
- active

User-owned profiles should support:

- creating from scratch,
- cloning from a system profile,
- editing parser options,
- editing column mappings,
- deleting when no longer needed.

Runtime behavior for user profiles:

- Apply header canonicalization from `mapping_json`.
- Map canonical fields directly to normalized transaction fields.
- Apply only allowed built-in normalization transforms when configured in safe options.
- Do not evaluate rule conditions or action lists.

### Minimal CRUD Required for MVP

Minimal profile behavior:

1. List all selectable profiles

- returns system profiles plus current user's user profiles

2. Create user profile

- from scratch or cloned from a system profile
- reject payload keys `options_json.matching_rules`, `options_json.actions`, and transform catalog overrides

3. Update user profile

- parser settings and mappings
- reject payload keys `options_json.matching_rules`, `options_json.actions`, and transform catalog overrides

4. Delete user profile

- blocked or validated when used as account default

No user CRUD is needed for system profiles in MVP.
Those are read-only to users.

### User Permissions and Editing Rules

- System CSV import profiles:
  - read/select only
  - not editable by end users
- User CSV import profiles:
  - fully CRUD within ownership scope
  - no shared/community profiles in MVP
  - no rule DSL editing in MVP

Clone behavior from system profile to user profile:

- Allowed to copy: parser settings, mapping aliases, safe normalization options, and display metadata.
- Not allowed to copy: `matching_rules`, action pipelines, or any executable DSL sections.
- Clone output is always a mapping-oriented `type = user` profile.

### System Profile Definition and Maintenance

System profiles are defined in application code and written to the database automatically on deploy. They are not created or managed via API or UI.

#### Context: Production Deploy Path

The application does not run `artisan db:seed` in production. The Docker entrypoint and Deployer recipe both run only `artisan migrate --force`. Any mechanism for loading system profiles must fit into this constraint.

#### Recommended Approach: Artisan Sync Command

- Define each system profile as a PHP array in a dedicated registry class, for example `App\Services\Import\SystemCsvImportProfileRegistry`.
- Implement a dedicated Artisan command, for example `artisan import:sync-system-profiles`, that calls `updateOrCreate` keyed on `key` for each entry in the registry.
- Add the command to `docker/entrypoint.sh` immediately after `php artisan migrate --force`.
- Adding a new format, updating field mappings, or retiring a profile is done by editing the registry class; the next deploy applies changes automatically.
- System profiles are never created, updated, or deleted through user-facing API endpoints.
- This must be added to the various deployment documentation, especially for the ones not relying on the standard Docker entrypoint.

This approach ensures:

- profiles are version-controlled as PHP source code,
- the sync command is idempotent and safe to re-run at any time,
- tests can invoke the command or call the registry directly without mocking,
- no migration file churn is required when a profile's content changes.

#### Considered Alternative: Data Migration

Inserting system profiles directly in a migration file is also viable and requires no changes to the deploy scripts. However, this means every profile content change (new mapping, updated rule) requires a new migration file. This creates noise in the migration history and is harder to maintain as the number of supported formats grows. Not recommended unless the command approach presents integration challenges.

### Account Default CSV Import Preference

Accounts should gain a new preference for CSV import defaults.

Recommended account-level field:

- `preferred_csv_import_profile_id` nullable

Expected behavior:

- user selects account first,
- import UI auto-selects preferred profile,
- user may override for the current import run.

### API Shape for CSV Import Profiles

Recommended endpoints:

- `GET /api/v1/imports/csv-profiles`
  - returns system profiles plus current user's user profiles
- `POST /api/v1/imports/csv-profiles`
  - create user profile
- `PATCH /api/v1/imports/csv-profiles/{profile}`
  - update user profile
- `DELETE /api/v1/imports/csv-profiles/{profile}`
  - delete user profile
- `PATCH /api/v1/accounts/{accountEntity}`
  - generic account update endpoint
  - supports whitelisted account-level settings, including `preferred_csv_import_profile_id`

### Import Request Behavior for CSV

For CSV parse requests, request payload should allow:

- selected CSV import profile id, or
- no selection, in which case default resolution is applied.

If selected profile is of type `system`, it is read-only.
If selected profile is of type `user`, ownership validation is required.

### File Encoding Handling

File encoding should be handled by automatic detection first, with deterministic fallback behavior. CSV exports from European banking applications commonly use ISO-8859-1 or Windows-1252 rather than UTF-8.

Approach:

- `CsvParserService` attempts charset detection from the uploaded bytes (BOM + heuristic detection).
- If confidence is high, the detected encoding is used and converted to UTF-8 before parsing.
- If confidence is low or ambiguous, parser falls back to a configured encoding candidate order (for example: UTF-8, Windows-1252, ISO-8859-2, ISO-8859-1).
- Input that still cannot be reliably converted to UTF-8 should produce a user-facing parse error with a clear message.
- Profile-level fixed encoding is not required in MVP; if a future profile consistently needs a hard override, this can be introduced later as an advanced option.

For QIF:

- QIF files are typically ASCII or UTF-8; encoding issues are less common.
- If a non-UTF-8 QIF file is encountered, the same conversion path applies.
- No encoding field is required on the QIF parse request for MVP; UTF-8 is the default assumption.

### Migration Path from Current Rule File

The current Raiffeisen rule file should be migrated into:

- one backend system profile with key similar to `hun_raiffeisen_v1`,
- a set of built-in transforms that capture current custom-function behavior,
- automated parser tests covering representative CSV rows.

The JavaScript rule file should be treated as legacy reference material during migration, not as long-term runtime architecture. Removed as frontend code after migration.

## Duplicate Detection Reuse

Use existing DuplicateDetectionService for import drafts.

- Input fields are mapped from normalized draft data.
- Duplicate candidates are returned with similarity score and key summary fields.
- Sorting and threshold rules come from existing user-aware settings resolver behavior.
- Duplicate check timing: duplicate detection runs eagerly for all drafts immediately at parse completion, as this is a fundamental part of the review process and affects user decision-making on which drafts to finalize.

This replaces frontend-only similarity heuristics as source of truth.

### Related AI Document Matching (ready_for_review)

In addition to transaction duplicate detection, parse response enrichment should include a lightweight candidate list of related AI Documents that are currently in state `ready_for_review`.

Purpose:

- If a receipt is already uploaded and AI-processed, finalizing the AI Document can be the preferred user action instead of creating a new manual transaction from import draft.

Scope for MVP extension:

- Query only AI Documents owned by the current user and in state `ready_for_review`.
- Match candidates against each normalized draft using deterministic heuristics.
- Return top candidates per draft in `related_ai_documents[]` with a confidence score and compact summary fields.

Recommended matching signals (weighted):

- amount proximity (exact or within a small tolerance),
- date proximity (same day or small window),
- payee/merchant similarity,

Suggested response shape for each candidate:

- ai_document_id
- status
- confidence_score
- matched_on (array of signals such as amount/date/payee)
- summary (merchant, total_amount, document_date)

Behavior notes:

- Candidate discovery is advisory only; no automatic linking or finalization is performed.
- Candidate search must be bounded (for example recent time window, capped candidate count) to avoid parse-time latency spikes.
- If no candidate is found, `related_ai_documents[]` is empty.

## Processing Flow

1. User uploads QIF or CSV file.
2. Parser is selected by source_type; synchronous parsing starts.
3. Each entry/row is normalized into draft DTO objects. Parsed amounts are treated as denominated in the target account's currency; no exchange rate conversion is applied.
4. Duplicate candidates are computed and attached to draft DTOs.
5. Response returns draft list, warnings, and summary.
6. Frontend renders review list from in-memory response data.
7. User can:
   - ignore draft,
   - check/view duplicates,
   - inspect related AI Document candidates in state ready_for_review,
   - open draft in existing transaction form modal.
   - when a high-confidence related AI Document exists, choose AI Document finalization flow instead of import draft finalization.

8. On save/finalize:
   - existing transaction endpoint validates and creates transaction,
   - frontend marks reviewed item as finalized in session state.

9. Session ends when user leaves page; no server-side import history is required.

## Draft Status Model

- Draft status (frontend session state):
  - pending_review
  - ignored
  - finalized
  - failed_validation

Notes:

- Parser warnings do not automatically block finalization.
- Invalid drafts should be visible and editable when possible.
- No persistent server-side status tracking is required in MVP.

## Matching and Enrichment

- Asset matching:
  - Reuse existing account/payee matching patterns where applicable.
  - Keep matching deterministic and warning-backed when confidence is low.

- Category/item behavior:
  - Import parser should not force line-item decomposition when source lacks that detail, which is the expected behavior.
  - User may add/edit items during finalization in existing form.

## Storage and Retention

- Import files:
  - Do not persist uploaded source files.
  - Use temporary upload stream/file for parsing within request lifecycle only.

- Retention:
  - Not applicable for source file retention in MVP because files are not stored.
  - No import history/log retention is required.

- Transaction linkage:
  - No persistent linkage is recorded between finalized transactions and their source import file or draft. Finalized transactions are recorded normally via the existing transaction creation UI and endpoint that is used by AI Document processing and finalization flow.
  - This is acceptable because import files are not persisted and import sessions are one-off (users do not expect to revisit or re-import the same file). However, during the review session, the frontend maintains in-memory linkage between drafts and their source file for display and troubleshooting purposes.

## Concurrency and Idempotency

- Multiple imports by same user are allowed as independent one-off sessions.
- Finalization must be idempotency-safe with a best effor approach. This means, that reasonable safeguards should be in place to prevent duplicate transaction creation if the user clicks finalize multiple times rapidly, but no complex locking or de-duplication logic is required. The frontend should disable the finalize button after the first click until a response is received. If the user is working in parallel in multiple tabs or windows, then this is not a common scenario to be handled.

## Testing Strategy

- Required factories:
  - CsvImportProfileFactory

- Backend unit tests:
  - QifParserServiceTest
  - CsvParserServiceTest
  - ImportNormalizationServiceTest

- Backend feature tests:
  - ImportApiParseTest
  - ImportTransactionCreateFromDraftTest (tests finalization via existing transaction endpoint from import context)
  - ImportDuplicateDetectionTest
  - ImportRelatedAiDocumentsTest
  - ImportAuthorizationTest

- Frontend component tests:
  - ImportUploadCard.spec.js
  - ImportDraftTable.spec.js
  - DuplicateCandidatesPanel.spec.js
  - RelatedAiDocumentsPanel.spec.js

- Regression tests:
  - Ensure current transaction create/finalize flow still works from import context.

## Edge Cases and Negative Scenarios

- QIF file missing terminators (^).
- QIF mixed date formats in one file.
- QIF amount contains locale-specific separators.
- CSV with missing header or duplicate columns.
- CSV with empty rows and partial malformed lines.
- CSV with multiline quoted fields, embedded delimiters, and escaped quotes inside quoted values.
- Duplicate check with insufficient fields.
- Multiple AI Documents in ready_for_review matching the same draft with close scores.
- AI Document exists but belongs to another user (must never be returned).
- Finalize called for already finalized/ignored draft should not be allowed by the UI
- Unauthorized access to import and profile endpoints should be denied.

## Open Questions for Post-MVP

1. Investment-specific QIF support:
   - Currently MVP covers bank/cash/credit card sections only.
   - Investment QIF extensions (e.g., `!Type:Invst` with position and price detail) deferred to later phase.

2. Advanced CSV profile editing UI:
   - Currently profiles are created/updated via form fields with minimal syntax validation.
   - A future visual rule builder or advanced DSL editor could provide more user control over complex matching logic.

3. User profile library and sharing:
   - Currently user CSV profiles are private to individual users.
   - A future enhancement could support workspace-level or community-shared profiles.

4. Reconciliation and import history:
   - No server-side import log or history in MVP.
   - Future versions might track which transactions came from imports, enabling statement reconciliation workflows.

## Acceptance Criteria

- Given a valid QIF file, when user uploads it, then draft transactions are generated without custom format coding.
- Given a valid CSV file and selected/default import profile, when user uploads it, then drafts are generated consistently via backend parser.
- Given parsed drafts, when user opens review, then duplicates can be checked using backend duplicate detection logic.
- Given duplicate candidates, when user chooses to ignore or proceed, then no transaction is created unless user explicitly finalizes.
- Given an import draft and related AI Documents in state ready_for_review, when parse completes, then related candidates are shown with confidence metadata and user can choose AI Document finalization path.
- Given a finalized draft, when save succeeds, then exactly one transaction is created and draft status becomes finalized.
- Given malformed entries, when parsing completes, then warnings and unmatched entries are visible without losing valid drafts.
- Given unauthorized access, when import and profile endpoints are requested without proper auth, then access is denied.
- Given existing import entry point, when feature is enabled, then users can import QIF and CSV through the unified review workflow.
- Given one-off import philosophy, when parsing completes, then uploaded source file is not stored on the server.

## Suggested Delivery Milestones

### Milestone 1: QIF Parser & Parse API Baseline

**Objective**: Establish end-to-end QIF parsing and response contract.

**Implementation Status (2026-03-29)**:

- Milestone 1 backend tasks below are implemented.
- Milestone 1 frontend tasks below are implemented at the page/UI level.
- Backend milestone tests listed below are implemented and passing.
- Frontend component tests listed below are not implemented yet because the repository does not currently have a working Vue component test harness configured.

**Backend Tasks**:

- [x] Implement `QifParserService`
  - [x] Line-based QIF parser supporting Bank/Cash/CCard types
  - [x] Marker parsing (D, T, P, M, L, N, ^)
  - [x] Non-blocking warnings for unsupported sections and split lines
  - [x] Date pattern matching with fallback to DD/MM/YYYY
  - [x] Amount normalization (sign, decimal)
- [x] Implement `ImportNormalizationService`
  - [x] Convert parsed QIF entries to draft transaction DTOs
  - [x] Populate mandatory fields (date, amount, account_id, transaction_type)
  - [x] Attach warnings to drafts
- [x] Create `CsvImportProfile` migration and model
- [x] Implement `ImportApiController::parse` endpoint
  - [x] Accepts multipart `file`, `source_type=qif`, `account_id`
  - [x] Returns Runtime Import Parse Result DTO
  - [x] Enforce file size limits from env variables
- [x] Implement `ImportPolicy` for auth/ownership checks
- [x] Create `CsvImportProfileFactory` for testing

**Frontend Tasks**:

- [x] Create `ImportSourceSelector` component (radio: QIF or CSV)
- [x] Create `ImportUploadCard` component
  - [x] File input + upload handler
  - [x] Display upload progress and errors
  - [x] Call `/api/v1/imports/parse`
- [x] Create `ImportDraftTable` component
  - [x] Display parsed drafts with draft_index, date, amount, payee columns
  - [x] Show draft status (pending_review, ignored, finalized, failed_validation)
  - [x] Display warnings per draft inline or in expandable section
  - [x] Show raw_entry preview on click
- [x] Basic page layout: ImportSourceSelector → ImportUploadCard → ImportDraftTable

**Testing Tasks** (Backend Agent):

- [x] Unit test: `QifParserServiceTest`
  - [x] Valid QIF with all markers, mixed date formats, localized amounts
  - [x] Missing terminators, unsupported sections, split lines
  - [x] EOF handling, malformed entries
- [x] Unit test: `ImportNormalizationServiceTest`
  - [x] Parser output → DTO conversion
  - [x] Warning accumulation
  - [x] Field mapping correctness
- [x] Feature test: `ImportApiParseTest::qif_parse_valid`
  - [x] Parse valid QIF file
  - [x] Validate response DTO shape
  - [x] Assert auth check blocks unauthorized access

**Testing Tasks** (Frontend Agent):

- [ ] Component test: `ImportUploadCard.spec.js`
  - [ ] File selection and upload
  - [ ] Error display
  - [ ] API call on submit
- [ ] Component test: `ImportDraftTable.spec.js`
  - [ ] Render drafts from parsed response
  - [ ] Display status indicators
  - [ ] Show warnings

**Important Notes For Milestone 1**:

- Changed: the implemented parse response currently exposes review-oriented draft fields directly on each draft object instead of nesting them under a `normalized_transaction` object. The frontend currently consumes this flatter draft shape.
- Missing: `ImportUnmatchedEntriesPanel` is not implemented in Milestone 1. The current QIF flow returns parsed drafts and warnings, but does not yet provide a separate unmatched-entry payload/UI.
- Missing: `DuplicateCandidatesPanel` is not implemented in Milestone 1. Duplicate enrichment is deferred to Milestone 2 together with the backend duplicate adapter.
- Changed: the frontend source selector displays both `QIF` and `CSV`, but `CSV` is intentionally disabled with a user-facing note because backend CSV parsing belongs to Milestone 2.
- Skipped for now: reuse of existing transaction display/create modal interactions is not wired into the Milestone 1 review table. This matches the milestone deliverable, which stops at upload, parse, and review-table display.
- Missing: frontend component tests were intentionally not added because the repository currently lacks a working Vue component test setup. Backend tests were added and validated instead.

**Deliverable**: QIF files can be uploaded, parsed, and displayed in review table. Finalization not yet wired.

---

### Milestone 2: CSV Parser, Profile Model, Duplicate Detection & Frontend Review Integration

**Objective**: CSV parsing with profile system and duplicate detection enrichment.

**Backend Tasks**:

- Create `SystemCsvImportProfileRegistry` with one system profile (hun_raiffeisen_v1 equivalent)
  - Define parser_settings (delimiter, has_header_row, date format)
  - Define mapping_json (source columns → canonical fields)
  - Define matching_rules[] with conditions/actions for transaction type classification
  - Define defaults and warnings
- Implement `SyncSystemCsvImportProfilesCommand`
  - Idempotent `updateOrCreate` keyed on `key`
  - Add to `docker/entrypoint.sh`
- Implement `CsvParserService`
  - Use `league/csv` for tokenization
  - Apply charset detection and encoding conversion (UTF-8 fallback)
  - Header canonicalization via mapping_json
  - Rule matching and action execution (for system profiles only)
  - Collect unmatched rows with warnings
- Implement `ImportDuplicateDetectionService` adapter
  - Map normalized draft fields to existing DuplicateDetectionService input
  - Execute duplicate check eagerly at parse completion
  - Attach duplicate_candidates[] to each draft
- Extend `ImportApiController::parse` to support CSV
  - Accept `source_type=csv` and optional `csv_import_profile_id`
  - Default resolution: account preferred → application default system profile
  - Return drafts with duplicate_candidates populated
- Implement `CsvImportProfile` CRUD endpoints (GET, POST for user profiles)
  - GET `/api/v1/imports/csv-profiles` returns system + user profiles
  - POST `/api/v1/imports/csv-profiles` creates user profile
  - Validate request: reject `options_json.matching_rules`, `options_json.actions`

**Frontend Tasks**:

- Enhance `ImportUploadCard` to support CSV profile selection
  - Dropdown: display system + user profiles
  - Remember last-used profile in localStorage
  - Apply account default on account selection
- Create `DuplicateCandidatesPanel` component
  - Display similar transaction list per draft
  - Show confidence score and matched_on summary
  - Link to view similar transaction details
- Enhance `ImportDraftTable` to show duplicate badge/warning
  - Highlight drafts with high-confidence duplicates
- Add profile management UI (basic form)
  - List user profiles
  - Create new profile (from scratch or clone from system)
  - Edit mapping_json and parser options
  - Delete user profile

**Testing Tasks** (Backend Agent):

- Component integration test: `SystemCsvImportProfileRegistry`
  - Registry contains expected system profile structure
  - `SyncSystemCsvImportProfilesCommand` loads profiles correctly
- Unit test: `CsvParserServiceTest`
  - Parse valid CSV with system profile
  - Header canonicalization via mapping_json
  - Rule matching and action execution
  - Multiline fields, embedded delimiters, localized amounts
  - Charset detection (UTF-8, Windows-1252, ISO-8859-1)
  - Parse errors and warnings emitted without failing full import
- Feature test: `ImportApiParseTest::csv_parse_valid`
  - Parse CSV with system profile
  - Parse CSV with user profile (mapping-only, no rules)
  - Validate profile selection precedence
  - Assert forbidden keys rejected on profile create/update
- Feature test: `ImportDuplicateDetectionTest`
  - Drafts enriched with duplicate_candidates
  - Similarity scores populated
  - Candidate search bounded (time window, count)

**Testing Tasks** (Frontend Agent):

- Component test: `ImportUploadCard.spec.js` (profile selection UX)
- Component test: `DuplicateCandidatesPanel.spec.js`
  - Display candidates with confidence and signals
  - Link interaction
- Component test: CSV profile management form

**Deliverable**: CSV files parsed via backend profiles. Duplicates detected and displayed. Profile CRUD available. Finalization still via existing modal flow.

---

### Milestone 3: Finalize/Ignore Actions, Profile Ownership, Full Coverage & Legacy Migration

**Objective**: Enable user finalization workflow and migrate from legacy CSV import.

**Backend Tasks**:

- Extend `CsvImportProfile` model
  - Add `user_id` for user profiles
  - Add `key` for system profiles
  - Add validation: type=system requires key, type=user requires user_id
  - Add relationship: User hasMany CsvImportProfile(type=user)
- Implement `CsvImportProfilePolicy`
  - Only user owner can read/edit/delete user profiles
  - Everyone can read system profiles
- Implement `AccountEntity` model update
  - Add `preferred_csv_import_profile_id` nullable foreign key
  - Add `PATCH /api/v1/accounts/{accountEntity}` endpoint
  - Whitelist `preferred_csv_import_profile_id` in update validation
- Implement profile clone endpoint
  - POST `/api/v1/imports/csv-profiles/{profile}/clone`
  - Strip DSL fields when cloning system → user
  - Validate user ownership of target user profile
- Document expected parse response error format for partial success
  - 200 with mixed valid/invalid drafts (same payload, warnings per draft)
  - 422 for structural errors (bad profile, missing required fields)
- Add comprehensive error handling tests
  - Parser recovers from malformed entries
  - Drafts with warnings still finalize
  - Failed finalization does not auto-retry

**Frontend Tasks**:

- Implement `ImportDraftTable` actions
  - Ignore button: mark draft status = ignored, hide row (or gray out)
  - Finalize button: open existing transaction form modal
    - Pre-populate draft fields into modal
    - On save, call existing `/api/v1/transactions` endpoint
    - Mark draft status = finalized
    - Disable button on click until response received
- Implement `RelatedAiDocumentsPanel` component (if AI docs exist in ready_for_review state)
  - Display candidate AI documents with merchant, amount, date, confidence
  - Link to open AI Document finalization modal instead of transaction form
- Update navigation: replace legacy CSV import page with unified import page
- Migrate existing CSV import rule file logic into first system profile
- Add profile preference UI to account settings
  - Select preferred import profile per account
  - Auto-select on import page based on account

**Testing Tasks** (Backend Agent):

- Feature test: `ImportAuthorizationTest`
  - User can CRUD own profiles
  - User cannot edit other user profiles
  - User cannot delete system profiles
  - Unauthorized requests denied
- Feature test: `ImportTransactionCreateFromDraftTest`
  - Finalize draft → creates exactly one transaction via existing endpoint
  - Transaction fields match normalized draft
  - Repeated finalize on same draft does not create duplicate (frontend protection)
- Unit test: `ImportNormalizationServiceTest` (expanded)
  - Related AI document candidate matching heuristics
  - Confidence scoring
  - Bounded search (time window, candidate count)
- Regression test: existing transaction create/update flow still works with import context
- Command test: `SyncSystemCsvImportProfilesCommand`
  - Idempotent: re-run produces same database state
  - Migration of hun_raiffeisen_v1 rule file into profile

**Testing Tasks** (Frontend Agent):

- Component test: `ImportDraftTable.spec.js` (finalize/ignore interaction)
- Component test: `RelatedAiDocumentsPanel.spec.js`
- E2E flow test: complete import workflow
  - Upload QIF/CSV
  - Review drafts with duplicates
  - Finalize selected drafts
  - Confirm transactions created
- Regression: legacy CSV import UI removed; all functionality in new page

**Deliverable**: End-to-end import workflow functional. Users can upload QIF/CSV, review drafts with duplicates, finalize transactions. Legacy import page removed. Full test coverage passing.

---

### Milestone 4: Documentation & Polish

**Objective**: Complete documentation and release preparation.

**Backend Tasks**:

- Document system CSV import profile format
  - Explain parser_settings, mapping_json, matching_rules[], defaults, warnings
  - Walkthrough of hun_raiffeisen_v1 profile
  - How to add new system profile (registry + test)
- Document API payloads (request/response schemas)
  - POST `/api/v1/imports/parse`
  - Profile CRUD endpoints
  - Error response format
- Add API documentation comments (PHPDoc) to controllers and services
- Generate OpenAPI/Swagger spec (if project uses it)

**Frontend Tasks**:

- Document Vue components (props, events)
- Update application user guide with import workflow
- Add tooltips/help text to profile management UI
- Write brief migration guide for users familiar with legacy import

**Testing/DevOps Tasks**:

- Verify `docker/entrypoint.sh` runs sync command correctly
- Test deployment with docker build
- Update deployment documentation (Deployer recipe if needed)
- Create example QIF and CSV test files for manual testing
- Verify all tests pass on CI

**Release Tasks**:

- Create release notes summarizing feature
- Update CHANGELOG.md
- Tag release version
- Deploy to staging and verify with user acceptance testing

**Deliverable**: Feature release-ready with complete documentation and passing all tests.
