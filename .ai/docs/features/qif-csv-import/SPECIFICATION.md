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
  - Enable guided user profile creation with browser-side CSV preview and column mapping.
  - Support AI-assisted profile suggestion using the user's own configured AI provider, consistent with the existing receipt processing privacy model.

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
  - FileImportProfile
    - Stores reusable CSV import profile definitions with `type = system|user`.

- Migrations:
  - No mandatory import batch/draft persistence migrations in MVP.
  - file_import_profiles.

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
  - SystemFileImportProfileRegistry (new)
    - PHP array definitions of all system profiles; single source of truth for profile content.
  - SyncSystemFileImportProfilesCommand (new Artisan command: `import:sync-system-profiles`)
    - Idempotent sync of registry entries to the database via `updateOrCreate` keyed on `key`.
  - AiImportProfileSuggestionService (new)
    - Sends a trimmed CSV sample to the user's configured AiProviderConfig via Prism structured output.
    - Returns a structured profile suggestion covering all editable user profile fields plus per-field confidence notes.
    - Does not depend on AiDocument; operates as a standalone AI call separate from AiStepGateway.
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
  - ProfileCreationWizard (multi-step wizard for guided user profile creation)
  - CsvPreviewTable (renders detected headers and sample rows; updates reactively on settings change)
  - ColumnMappingRow (per-column: header display, canonical field dropdown, parsed value preview) — component exists but is not used in the wizard; functionality is absorbed into the integrated mapping table in Step 3
  - DateFormatSelector (auto-detected and locale-based format candidates, custom PHP format string input, optional sample value display)
  - AmountFormatPreview (raw and parsed amount side-by-side) — component exists but is not used in the wizard; amount preview is rendered inline in the mapping table cells instead

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
      - source_category (optional string; bank-assigned category label when a `category` column is mapped in the profile; advisory only — shown in the review table as a hint, not forwarded to the transaction form)
      - warnings[]
      - duplicate_candidates[]
      - related_ai_documents[] (optional candidate list of AI Documents in state ready_for_review that likely represent the same purchase/receipt)
  - FileImportProfile (persisted entity)
    - id
    - type (`system` or `user`)
    - file_type (`csv` or `qif`)
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
    - preferred_file_import_profile_id nullable

- Relationships:
  - User has many FileImportProfiles of type `user`.
  - AccountEntity belongs to preferred FileImportProfile (nullable foreign key).

- Endpoints (draft):
  - POST /api/v1/imports/parse
    - Request: multipart/form-data; required fields: `file`, `source_type` (`qif` or `csv`), `account_id`; optional: `file_import_profile_id` (CSV only; omit to use account preferred profile; if no profile is available from either source, the request is rejected)
    - Response: Runtime Import Parse Result DTO
  - CSV import profile endpoints:
    - GET /api/v1/imports/file-profiles
    - POST /api/v1/imports/file-profiles
    - GET /api/v1/imports/file-profiles/{profile}/affected-accounts — returns accounts that have this profile set as their default; used to warn the user before deletion
    - PATCH /api/v1/imports/file-profiles/{profile}
    - DELETE /api/v1/imports/file-profiles/{profile}
  - AI-assisted profile suggestion endpoint:
    - POST /api/v1/imports/file-profiles/suggest
      - Requires a configured AiProviderConfig for the authenticated user; returns 422 if none exists.
      - Request: multipart/form-data; required: `file`; optional: `account_id` (used for contextual hints in the prompt only).
      - Server trims the uploaded file to the first 10 data rows before forwarding to the AI provider.
      - Response: structured profile suggestion payload (not persisted; returned for form pre-fill only).
  - Note: `preferred_file_import_profile_id` is managed through the account add/edit web form, not via a dedicated API endpoint. See "Account Default Import Profile Preference" section below.

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

### QIF Profiles (Optional Field Remapping)

The QIF format has no authoritative standard. Different banks assign different semantic meanings to the same markers (e.g., one bank puts transaction type in `P` and payee name in `M`, while the standard convention is the reverse).

To handle this without heuristic guessing, QIF imports support an optional `FileImportProfile` with `file_type = 'qif'`.

**When selected:**

- The profile's `options_json` provides a `field_map` and optional `amount_sign` override.
- The parser reads the marker specified by `field_map.payee` instead of always reading `P`, etc.
- If no profile is selected, standard QIF semantics apply (P=payee, M=memo, L=category, N=reference).

**Profile is always optional for QIF** — unlike CSV where a profile is required.

**Supported `options_json` keys for QIF profiles:**

```json
{
  "field_map": {
    "payee": "M",
    "comment": "P",
    "category": "L",
    "reference": "N"
  },
  "amount_sign": "normal"
}
```

- `field_map` keys: `payee`, `comment`, `category`, `reference` — each maps to a QIF marker letter.
- `amount_sign`: `"normal"` (default) or `"inverted"` (multiply parsed amount by -1).

**System QIF profile shipped with MVP:**

- Key: `qif_swap_p_m_v1` — for banks that put transaction type in `P` and payee in `M`.

**User-created QIF profiles:**

- Same model as user CSV profiles but with `file_type = 'qif'`.
- No `matching_rules` or `actions` — field remapping only.
- No `mapping_json` required (QIF markers are fixed).

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
  - comment concatenation options (separator string used when multiple columns map to `comment` or `reference`)
  - must not include `matching_rules`, `actions`, or transform registry overrides
  - note: sign handling is expressed via the top-level `sign_handling` column, not in `options_json`
- active

User-owned profiles should support:

- creating from scratch,
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

- from scratch
- reject payload keys `options_json.matching_rules`, `options_json.actions`, and transform catalog overrides

3. Update user profile

- parser settings and mappings
- reject payload keys `options_json.matching_rules`, `options_json.actions`, and transform catalog overrides

4. Delete user profile

- when the profile is set as the default for one or more accounts, the user is shown a warning listing those accounts before confirming deletion
- upon confirmation, the profile is deleted; the database automatically clears `preferred_file_import_profile_id` on affected accounts (`nullOnDelete` FK behaviour)
- manual profile selection will be required for those accounts on future imports

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

### System Profile Definition and Maintenance

System profiles are defined in application code and written to the database automatically on deploy. They are not created or managed via API or UI.

#### Context: Production Deploy Path

The application does not run `artisan db:seed` in production. The Docker entrypoint and Deployer recipe both run only `artisan migrate --force`. Any mechanism for loading system profiles must fit into this constraint.

#### Recommended Approach: Artisan Sync Command

- Define each system profile as a PHP array in a dedicated registry class, for example `App\Services\Import\SystemFileImportProfileRegistry`.
- Implement a dedicated Artisan command, for example `artisan app:import:sync-system-profiles`, that calls `updateOrCreate` keyed on `key` for each entry in the registry.
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

### Account Default Import Profile Preference

Accounts carry a single import profile preference covering both QIF and CSV imports.

Account-level field:

- `preferred_file_import_profile_id` nullable — accepts any accessible `FileImportProfile` (CSV or QIF)

The preference is set and edited through the standard account add/edit web form (`AccountEntityController`), validated by `AccountEntityRequest`. No dedicated API endpoint exists for this field.

Expected behavior:

- user selects account on the import page,
- import UI auto-selects the preferred profile if it matches the current source type (CSV or QIF),
- if no matching preferred profile exists for the current source type, the CSV fallback reads `localStorage` (last-used profile); QIF falls back to no profile (QIF profiles are optional),
- when the user switches source type, the previously selected profile for the outgoing type is cleared and the preferred profile for the incoming type is applied (if one exists),
- user may override the auto-selected profile for the current import run.

### API Shape for CSV Import Profiles

Recommended endpoints:

- `GET /api/v1/imports/file-profiles`
  - returns system profiles plus current user's user profiles
- `POST /api/v1/imports/file-profiles`
  - create user profile
- `GET /api/v1/imports/file-profiles/{profile}/affected-accounts`
  - returns accounts that have this profile set as their preferred import profile
  - used by the frontend to warn the user before confirming deletion
- `PATCH /api/v1/imports/file-profiles/{profile}`
  - update user profile
- `DELETE /api/v1/imports/file-profiles/{profile}`
  - delete user profile; database automatically clears `preferred_file_import_profile_id` on affected accounts via `nullOnDelete` FK behaviour

Note: `preferred_file_import_profile_id` is not managed through this API. It is set via the account add/edit web form.

### Import Request Behavior for CSV

For CSV parse requests, request payload should allow:

- selected CSV import profile id, or
- no selection, in which case the account's preferred profile is used.

If no profile can be resolved from either source, the request is rejected with a 422 error.

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

## User Profile Creation Workflow

### Overview

Two complementary paths exist for creating user CSV import profiles:

1. **In-app profile creation wizard** — the primary UX path, usable without any AI configuration. Guides the user step-by-step through parser settings and column mapping with live browser-side previews. No financial data is sent to any server during the analysis phase.
2. **AI-assisted profile generation** — an optional accelerator available to users who have a configured `AiProviderConfig`. Sends a small CSV sample to the user's own AI provider and returns a structured draft profile for review. Follows the same privacy model as receipt processing: the user's own API key, the user's own provider.

Both paths produce a standard `type = user` profile saved via the existing `POST /api/v1/imports/file-profiles` endpoint. The paths are not mutually exclusive — a user may use AI suggestion to get a starting point and then adjust it using the wizard's live preview.

### Canonical Target Fields for User Profile Mapping

The following canonical field names are the closed, enumerated set of valid mapping targets for user profile `mapping_json`. These names are used in the wizard column mapping dropdowns, validated by the backend on save, and documented in the AI suggestion prompt.

| Canonical name | Destination in normalized output         | Description                                                                                                                                                                                                                 |
| -------------- | ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `date`         | `normalized_transaction.date`            | Transaction date                                                                                                                                                                                                            |
| `amount`       | `normalized_transaction.amount`          | Single amount column; sign is applied according to `sign_handling`                                                                                                                                                          |
| `payee`        | `normalized_transaction.payee`           | Counterparty or merchant name                                                                                                                                                                                               |
| `comment`      | `normalized_transaction.comment`         | Free-text memo or description; multiple columns concatenated in mapping order                                                                                                                                               |
| `reference`    | `normalized_transaction.comment`         | Bank reference number or transaction ID; treated as a secondary comment and appended to `comment` with a separator if `comment` is also mapped                                                                              |
| `category`     | draft `source_category` field (advisory) | Bank-assigned category label; **not** placed in `normalized_transaction` and not auto-matched to a YAFFA category; shown as a hint column in the draft review table to assist manual category selection during finalization |
| `ignore`       | —                                        | Column present in the source file but not imported                                                                                                                                                                          |

**`sign_handling` valid values for user profiles:**

- `as_is` — use the parsed signed value directly as-is (positive values are treated as the import direction implied by the account type and transaction context).
- `inverted` — negate the parsed value; for banks that export debits as positive numbers using a sign convention opposite to YAFFA's expectation.

**Banks with separate credit and debit columns** (two positive-value columns representing income and expense) cannot be handled by a user profile. That pattern requires conditional column logic (`if credit > 0 → deposit; if debit > 0 → withdrawal`), which is a `matching_rules` capability belonging to system profiles only. Users with such a bank export should request a system profile for their institution via the community contribution path.

**Validation rules:**

- `date` must be mapped.
- `amount` must be mapped.
- `reference` is treated as a comment-type field; if both `comment` and `reference` are mapped, their values are concatenated in the order they appear in `mapping_json`.
- Multiple columns may map to `comment` and/or `reference`; all values are concatenated in mapping order.
- Mapping two columns to any other single canonical target (e.g., two columns to `payee`) must produce a validation warning on save.

### In-App Profile Creation Wizard

#### Overview

The wizard is browser-driven. CSV analysis runs entirely in JavaScript using the `File API` — the file is read locally and never uploaded during the analysis phase. The resulting profile is saved via the existing profile CRUD endpoint when the user explicitly submits.

Auto-detection is designed to provide useful defaults, not to be authoritative. All detected values are displayed as editable fields so the user can correct any mis-detection before saving.

The same four-step wizard is used for both creating a new profile and editing an existing one. When editing, the wizard pre-populates all fields from the saved profile (parser settings, column mappings, date format, etc.) but still requires a CSV sample file in Step 1. The sample file is needed for live previews in Steps 2-3 and for the AI suggestion feature; without it the column mapping table would be static and the auto-detection indicators would be absent.

#### Step 1: File Selection and Auto-Detection

- User selects a CSV sample file from their local filesystem (the same export they intend to import, or any representative sample). **This step is required for both new profiles and edits.**
- JavaScript reads the first 20 lines of the file using the `File API`.
- Auto-detection logic runs client-side:
  - **Delimiter**: test candidate delimiters (`;`, `,`, tab, `|`) by counting consistent column-count splits across all sampled rows; highest-scoring delimiter wins; ties default to `,`.
  - **Header row**: heuristic — if the first row contains no numeric values and subsequent rows do, treat as header. Shown as a toggle the user can override.
  - **Encoding**: attempt BOM detection (UTF-8, UTF-16 LE/BE); fall back to UTF-8 for browser-side reading. Encoding conversion for non-UTF-8 files happens server-side at import time (consistent with `CsvParserService` behavior).
- A preview table is rendered immediately showing the detected headers and the first 5 data rows.
- User can override any auto-detected setting; the preview re-renders on each change.
- When editing, existing column mappings from the saved profile are preserved for any headers that match the uploaded file's columns; unmatched columns default to `ignore`.

#### Step 2: Parser Settings Confirmation

Fields editable in this step (all shown with auto-detected values as defaults; pre-populated from the saved profile when editing):

- **Delimiter** — dropdown: `;`, `,`, tab, `|`, or a custom single-character entry.
- **Has header row** — toggle.
- **Decimal separator** — radio: `.` or `,`.
- **Thousand separator** — radio: space, `.`, `,`, or none.
- **Sign handling** — radio:
  - `as_is` — the amount column is already correctly signed; use as-is.
  - `inverted` — negate the parsed value; for banks that export amounts with a reversed sign convention.
- **Profile name** — text field; pre-filled with the file name (new) or the existing profile name (edit).

The preview table updates on every settings change without a server round-trip.

#### Step 3: Column Mapping

- The mapping UI is an **integrated table**: source header names occupy the first header row; canonical field dropdowns occupy a second header row directly below; sample data rows fill the table body. This keeps the source data visible while the user decides how to map each column.
- The dropdown options are the canonical target fields listed above, plus `ignore` as the default. Mapped columns are visually highlighted; ignored columns are dimmed.
- **Mapping requirements**: a persistent status panel at the top of this step shows the mapping state at a glance. `date` and `amount` are required; `payee` is recommended. The panel uses neutral indicators until each requirement is met, then turns green. A date format must also be selected once `date` is mapped — the panel highlights this with a warning indicator until satisfied. Warnings (amber, with icon) are reserved for blocking issues such as duplicate mappings.
- **Validation gate**: the step cannot be completed unless both `date` and `amount` are mapped, a date format is selected, and no duplicate mappings exist. Columns that allow multiple mappings (`comment` and `reference`) are exempt from the duplicate check.
- **Date field UX**: when a column is mapped to `date`, a date format panel appears above the table:
  - Lists auto-detected PHP format string candidates inferred from the column's sample values (marked ✓), locale-based suggestions, and a base set of generic common formats always shown.
  - User selects from candidates or types a custom PHP date format string. The format is a single profile-level setting (matching `FileImportProfile.date_format`), not stored per-column.
  - Parsed date values are shown inline beneath each raw value in the date-mapped table cells for immediate confirmation. Trailing content after the date (e.g. a weekday name such as `, péntek` in `2026.03.27., péntek`) is tolerated and ignored during parsing, consistent with PHP's `DateTime::createFromFormat` behaviour.
- **Amount field UX**: parsed numeric values are shown inline beneath each raw value in amount-mapped table cells, using the decimal and thousand separator settings confirmed in Step 2. Updates live when separator settings change.
- Multiple columns may be mapped to `comment`; they are concatenated in mapping order at parse time.

#### Step 4: Save

- User reviews the profile summary and clicks Save.
- Frontend assembles the profile payload from wizard state and calls `POST /api/v1/imports/file-profiles` (new profile) or `PATCH /api/v1/imports/file-profiles/{id}` (edit).
- On success: the wizard closes and the profile list refreshes.
- On validation error: field-level errors shown inline; wizard remains on the step with the failing field.

### AI-Assisted Profile Generation

#### Overview

Users who have a configured `AiProviderConfig` can request an AI-generated profile suggestion based on a small CSV sample. This uses the existing Prism-based AI provider infrastructure with the user's own API key — identical to the receipt processing model. The suggestion pre-fills the profile creation form; no profile is saved automatically.

Vision capability is not required; text-only models are sufficient. The feature is available for any provider that supports structured output via Prism.

#### Trigger and Availability

- A "Suggest with AI" button is displayed on the profile creation form only when the user has at least one `AiProviderConfig` record.
- If no AI provider is configured, the button is not shown. A contextual note linking to the AI settings page may be shown in its place.
- The button is also available when editing an existing user profile. Because file upload is required in Step 1 for both new and edit flows, the uploaded sample is available for the AI suggestion in either case.

#### User Flow

1. User clicks "Suggest with AI".
2. A file picker appears for a CSV sample file (separate from the main import file field on the import page).
3. User selects a CSV export from their bank. It may be the same file they intend to import, or any representative sample.
4. A brief privacy notice is shown: _"The first 10 rows of your sample will be sent to [provider name] using your configured AI API key."_
5. User confirms.
6. Browser uploads the file to `POST /api/v1/imports/file-profiles/suggest`.
7. Backend reads and trims the file to the first 10 data rows; remaining rows are discarded before the AI call.
8. `AiImportProfileSuggestionService` sends the trimmed sample to the AI provider and requests structured output.
9. On success: form fields are pre-filled from the suggestion; confidence notes appear as helper text per field.
10. User reviews, adjusts if needed, and saves via the normal profile save flow.

#### Backend: `AiImportProfileSuggestionService`

This service is distinct from `AiStepGateway` because it has no dependency on `AiDocument`. It is a standalone, single-call service.

Implementation:

- Uses `Prism::structured()` with the user's `AiProviderConfig` (`provider`, `model`, `api_key`).
- Applies provider-specific options as needed (e.g., OpenAI strict mode for structured output).
- Prompt content:
  - Description of the YAFFA user profile schema and all canonical field names with their meanings.
  - The CSV headers line and up to 10 data rows verbatim.
  - If `account_id` was provided: the account's name and currency as contextual hints.
  - Instruction to produce confidence notes explaining the reasoning behind each non-obvious field.
- Returns the structured Prism response payload; does not persist anything.

Structured output schema (Prism `ObjectSchema`):

- `delimiter` (enum: `,`, `;`, `\t`, `|`)
- `has_header_row` (boolean)
- `date_format` (string — PHP date format string, e.g., `d/m/Y`)
- `decimal_separator` (enum: `.`, `,`)
- `thousand_separator` (enum: space, `.`, `,`, empty string)
- `sign_handling` (enum: `as_is`, `inverted`)
- `mapping_json` (object: source header string → canonical field name)
- `confidence_notes` (array of objects: `field` string, `note` string)

#### Frontend Behavior After Suggestion

- All suggested field values are written into the wizard form.
- `confidence_notes` are rendered as helper text beneath the corresponding form field (e.g., _"Date column detected as `Értéknap` with format `Y.m.d.` based on sample value `2024.01.15.`"_).
- Fields where the AI returned a low-confidence note are visually highlighted to prompt the user to verify.
- The live preview table updates from the pre-filled delimiter and header settings so the user can immediately see whether the suggestion is correct.
- All pre-filled values remain editable.
- Save is always a user-initiated action.

#### Error Handling

| Condition                                                         | Response                                                                                                      |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| No `AiProviderConfig` for user                                    | 422 with message linking to AI settings                                                                       |
| Uploaded file is not parseable as text CSV                        | 422 with clear user-facing message                                                                            |
| AI provider call fails or times out                               | 502 with user-facing message; form not pre-filled                                                             |
| AI returns an unrecognised canonical field name in `mapping_json` | Backend strips unknown keys from the suggestion before returning; a warning is included in `confidence_notes` |
| AI returns a structurally invalid response                        | 502; Prism structured output failure surface                                                                  |

### Profile Export and Community Contribution

User profiles can be exported as JSON from the profile management UI. The export format matches the `FileImportProfile` persisted structure minus `id`, `user_id`, and timestamps, and is suitable for manual inspection and editing.

**Community contribution path**

If a user has created a high-quality profile for a specific bank or institution and is willing to share it publicly, they may submit it for promotion to a system profile. Promotion is a development-side process, not an in-app workflow:

1. User exports their profile JSON from the profile management UI.
2. User submits it (e.g., via a GitHub issue or a designated community channel) with notes on the source institution, country, and any known limitations.
3. A developer reviews the submission, migrates it into the system profile format (adding `matching_rules` and `actions` as needed, and covering edge cases the user's mapping-only profile could not express), adds it to `SystemFileImportProfileRegistry`, and covers it with automated tests.
4. The promoted profile ships as a system profile in a subsequent release and becomes available to all users.

This keeps system profiles application-managed, version-controlled, and test-covered while allowing community knowledge to grow the built-in profile library over time. In-app profile sharing between users is not in scope.

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
  - FileImportProfileFactory

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

- Backend unit tests (profile creation wizard and AI suggestion):
  - AiImportProfileSuggestionServiceTest
    - Structured output schema matches expected user profile fields
    - File trimming: only first 10 data rows forwarded to AI provider
    - Confidence notes returned per field
    - Unknown canonical field names in AI response are stripped with a warning note
    - AI provider failure surfaces as 502 response
    - Missing AiProviderConfig surfaces as 422 response

- Backend feature tests (profile creation):
  - ImportProfileSuggestTest
    - Authenticated user with AiProviderConfig receives structured suggestion
    - File is trimmed to 10 data rows before AI call (verify via mock)
    - Optional account_id context is passed to prompt when provided
    - Returns 422 when no AiProviderConfig exists
    - Returns 422 when uploaded file is not a parseable CSV
    - Returns 502 when AI provider call fails

- Frontend component tests (profile wizard):
  - ProfileCreationWizard.spec.js
    - Step navigation: forward and back, validation gate prevents advancing without required mappings
    - Auto-detection output pre-fills settings as editable defaults
    - Preview table re-renders on delimiter or header toggle change
    - Save assembles correct profile payload matching the user profile schema
  - CsvPreviewTable.spec.js
    - Renders detected headers and sample rows correctly
    - Updates reactively when delimiter or header-row settings change
  - ColumnMappingRow.spec.js
    - Dropdown options match the canonical field name list
    - Date format sub-control appears only when date is selected
    - Amount preview appears only when an amount field is selected
  - DateFormatSelector.spec.js
    - Sample values from the mapped column are displayed
    - Auto-detected format candidates are shown and selectable
    - Custom format string input is accepted and validated

- Regression tests:
  - Ensure current transaction create/finalize flow still works from import context.
  - Ensure existing profile CRUD endpoints are unaffected by the addition of the suggest endpoint.

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
- Profile wizard: CSV file where all delimiter candidates score equally (tie-breaking must be deterministic).
- Profile wizard: CSV file with no detectable header row (wizard defaults to no-header and shows raw column indices as names).
- Profile wizard: user maps two columns to the same canonical field other than `comment` or `reference` (must be shown as a validation warning).
- Profile wizard: user uploads a CSV with split credit/debit columns and no `amount` column; wizard cannot produce a valid mapping and should show a clear message explaining that split-column exports require a system profile.
- AI suggestion: AI provider returns an unrecognised canonical field name in `mapping_json` (backend strips it and adds a confidence note warning).
- AI suggestion: AI provider call times out or returns a malformed structured response (502 with user-facing message; form not pre-filled).
- AI suggestion: user submits a non-CSV file (e.g., a QIF or PDF) as the AI sample (422 with a clear message).
- AI suggestion: no AiProviderConfig exists for the user (422; button not shown in UI, but endpoint still enforces this for direct API calls).

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
- Given a valid CSV file and a selected or account-preferred import profile, when user uploads it, then drafts are generated consistently via backend parser.
- Given parsed drafts, when user opens review, then duplicates can be checked using backend duplicate detection logic.
- Given duplicate candidates, when user chooses to ignore or proceed, then no transaction is created unless user explicitly finalizes.
- Given an import draft and related AI Documents in state ready_for_review, when parse completes, then related candidates are shown with confidence metadata and user can choose AI Document finalization path.
- Given a finalized draft, when save succeeds, then exactly one transaction is created and draft status becomes finalized.
- Given malformed entries, when parsing completes, then warnings and unmatched entries are visible without losing valid drafts.
- Given unauthorized access, when import and profile endpoints are requested without proper auth, then access is denied.
- Given existing import entry point, when feature is enabled, then users can import QIF and CSV through the unified review workflow.
- Given one-off import philosophy, when parsing completes, then uploaded source file is not stored on the server.
- Given a CSV file and the profile creation wizard, when the user completes all steps, then a valid user profile is created using browser-side analysis only; no data is uploaded to any server during the analysis phase.
- Given an incomplete column mapping (missing date or amount), when the user attempts to advance past the mapping step, then a validation error is shown and navigation is blocked.
- Given no AI provider configured, when the user opens the profile creation form, then the AI suggestion option is not shown.
- Given an AI provider configured, when the user requests an AI suggestion and confirms the privacy notice, then form fields are pre-filled with the structured suggestion and the user must explicitly save.
- Given a user profile, when the user exports it as JSON, then the exported format is suitable for manual inspection and community submission for potential promotion to a system profile.

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
- [x] Create `FileImportProfile` migration and model
- [x] Implement `ImportApiController::parse` endpoint
  - [x] Accepts multipart `file`, `source_type=qif`, `account_id`
  - [x] Returns Runtime Import Parse Result DTO
  - [x] Enforce file size limits from env variables
- [x] Implement `ImportPolicy` for auth/ownership checks
- [x] Create `FileImportProfileFactory` for testing

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

- [x] Create `SystemFileImportProfileRegistry` with one system profile (hun_raiffeisen_v1 equivalent)
  - [x] Define parser_settings (delimiter, has_header_row, date format)
  - [x] Define mapping_json (source columns → canonical fields)
  - [x] Define matching_rules[] with conditions/actions for transaction type classification
  - [x] Define defaults and warnings
- [x] Implement `SyncSystemFileImportProfilesCommand`
  - [x] Idempotent `updateOrCreate` keyed on `key`
  - [x] Add to `docker/entrypoint.sh`
- [x] Implement `CsvParserService`
  - [x] Use `league/csv` for tokenization
  - [x] Apply charset detection and encoding conversion (UTF-8 fallback)
  - [x] Header canonicalization via mapping_json
  - [x] Rule matching and action execution (for system profiles only)
  - [x] Collect unmatched rows with warnings
- [x] Implement `ImportDuplicateDetectionService` adapter
  - [x] Map normalized draft fields to existing DuplicateDetectionService input
  - [x] Execute duplicate check eagerly at parse completion
  - [x] Attach duplicate_candidates[] to each draft
- [x] Extend `ImportApiController::parse` to support CSV
  - [x] Accept `source_type=csv` and optional `file_import_profile_id`
  - [x] Default resolution: account preferred profile; reject with 422 if neither explicit nor account-preferred profile is available
  - [x] Return drafts with duplicate_candidates populated
- [x] Implement `FileImportProfile` CRUD endpoints (GET, POST for user profiles)
  - [x] GET `/api/v1/imports/file-profiles` returns system + user profiles
  - [x] POST `/api/v1/imports/file-profiles` creates user profile
  - [x] Validate request: reject `options_json.matching_rules`, `options_json.actions`

**Frontend Tasks**:

- [x] Enhance `ImportUploadCard` to support CSV profile selection
  - [x] Dropdown: display system + user profiles
  - [x] Remember last-used profile in localStorage
  - [x] Apply account default on account selection
- [x] Create `DuplicateCandidatesPanel` component
  - [x] Display similar transaction list per draft
  - [x] Show confidence score and matched_on summary
  - [x] Link to view similar transaction details
- [x] Enhance `ImportDraftTable` to show duplicate badge/warning
  - [x] Highlight drafts with high-confidence duplicates
- [x] Add profile management UI (basic form)
  - [x] List user profiles
  - [x] Create new profile from scratch
  - [x] Edit mapping_json and parser options
  - [x] Delete user profile

**Testing Tasks** (Backend Agent):

- Component integration test: `SystemFileImportProfileRegistry`
  - Registry contains expected system profile structure
  - `SyncSystemFileImportProfilesCommand` loads profiles correctly
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

**Implementation Status (2026-04-14, updated 2026-06-21)**:

- Milestone 3 backend tasks below are implemented.
- Milestone 3 frontend tasks below are implemented.
- Backend milestone tests listed below are implemented.
- Frontend component tests listed below are not implemented yet because the repository does not currently have a working Vue component test harness configured.

**Changes from original design (2026-06-21)**:

- Account import profile preference is managed via the account add/edit web form (`accounts/form.blade.php` + `AccountEntityController` + `AccountEntityRequest`) instead of a dedicated sidebar Vue component and API endpoint.
- The `PATCH /api/v1/accounts/{accountEntity}` endpoint was removed; `preferred_file_import_profile_id` is now handled as part of the standard account update web form flow.
- Both CSV and QIF profiles are accepted as the account's preferred profile (single field, type-aware at selection time); original design implied CSV-only.
- Import page auto-selection (`ImportPage.vue`) is type-aware: switches source type clears the outgoing profile selection and applies the preferred profile for the incoming type if one exists.

**Backend Tasks**:

- [x] Extend `FileImportProfile` model
  - [x] Add `user_id` for user profiles
  - [x] Add `key` for system profiles
  - [x] Add validation: type=system requires key, type=user requires user_id
  - [x] Add relationship: User hasMany FileImportProfile(type=user)
- [x] Implement `FileImportProfilePolicy`
  - [x] Only user owner can read/edit/delete user profiles
  - [x] Everyone can read system profiles
- [x] Implement `AccountEntity` model update
  - [x] Add `preferred_file_import_profile_id` nullable foreign key
  - [x] Add `preferred_file_import_profile_id` to account add/edit web form (`accounts/form.blade.php`)
  - [x] Validate `preferred_file_import_profile_id` in `AccountEntityRequest` (both CSV and QIF profiles accepted; ownership enforced via `selectableForUser` scope)
  - Changed: no dedicated `PATCH /api/v1/accounts/{accountEntity}` API endpoint; preference is managed through the standard web form flow
- [x] Document expected parse response error format for partial success
  - [x] 200 with mixed valid/invalid drafts (same payload, warnings per draft)
  - [x] 422 for structural errors (bad profile, missing required fields)
- [x] Add comprehensive error handling tests
  - [x] Parser recovers from malformed entries
  - [x] Drafts with warnings still finalize
  - [x] Failed finalization does not auto-retry

**Frontend Tasks**:

- [x] Implement `ImportDraftTable` actions
  - [x] Ignore button: mark draft status = ignored, hide row (or gray out)
  - [x] Finalize button: open existing transaction form modal
    - [x] Pre-populate draft fields into modal
    - [x] On save, call existing `/api/v1/transactions` endpoint
    - [x] Mark draft status = finalized
    - [x] Disable button on click until response received
- [x] Implement `RelatedAiDocumentsPanel` component (if AI docs exist in ready_for_review state)
  - [x] Display candidate AI documents with merchant, amount, date, confidence
  - [x] Link to open AI Document finalization modal instead of transaction form
- [x] Update navigation: replace legacy CSV import page with unified import page
- [x] Migrate existing CSV import rule file logic into first system profile
- [x] Add profile preference to account add/edit form
  - [x] Select field in `accounts/form.blade.php`, grouped by file type (CSV / QIF) using `<optgroup>`
  - [x] Info tooltip on the field (CoreUI tooltip, initialized via `account/form.js`)
  - [x] Both CSV and QIF profiles selectable; single preference per account
  - Changed: preference is not a Vue component on the account show page; it is part of the standard account form
- [x] Auto-select preferred profile on import page (`ImportPage.vue`)
  - [x] On account selection: auto-select preferred profile if it matches the current source type
  - [x] On source type switch: clear the outgoing type's profile selection; apply the preferred profile for the incoming type if available
  - [x] CSV fallback: last-used profile from `localStorage` when no account preference matches
  - [x] QIF fallback: no profile selected (QIF profiles are optional)

**Testing Tasks** (Backend Agent):

- [x] Feature test: `ImportAuthorizationTest`
  - [x] User can CRUD own profiles
  - [x] User cannot edit other user profiles
  - [x] User cannot delete system profiles
  - [x] Unauthorized requests denied
  - [x] User can set accessible profile as account preference via web form; inaccessible profile is rejected (tested via `AccountEntityRequest` validation, not via removed API endpoint)
- [x] Feature test: `ImportTransactionCreateFromDraftTest`
  - [x] Finalize draft → creates exactly one transaction via existing endpoint
  - [x] Transaction fields match normalized draft
  - [x] Repeated finalize on same draft does not create duplicate (frontend protection)
- [x] Unit test: `ImportNormalizationServiceTest` (expanded)
  - [x] Related AI document candidate matching heuristics
  - [x] Confidence scoring
  - [x] Bounded search (time window, candidate count)
- [x] Regression test: existing transaction create/update flow still works with import context
- [x] Command test: `SyncSystemFileImportProfilesCommand`
  - [x] Idempotent: re-run produces same database state
  - [x] Migration of hun_raiffeisen_v1 rule file into profile

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

### Milestone 4: Profile Creation Wizard & AI Assistance

**Objective**: Guided user profile creation with browser-side preview and optional AI-assisted suggestion.

**Implementation Status (2026-06-22)**:

- Milestone 4 backend tasks listed below are implemented.
- Backend milestone tests listed below are implemented and passing.
- Frontend tasks listed below are not yet implemented.

**Backend Tasks**:

- [x] Implement `AiImportProfileSuggestionService`
  - [x] Prism `structured()` call using user's `AiProviderConfig` (provider, model, api_key)
  - [x] Prompt construction: canonical field list, trimmed CSV rows, optional account context
  - [x] Structured output schema: all user profile fields plus `confidence_notes`
  - [x] Strip unrecognised canonical field names from AI response; add confidence note warning
  - [x] Provider-specific options (e.g., OpenAI strict mode) applied as in existing AI services
- [x] Implement `POST /api/v1/imports/file-profiles/suggest` endpoint
  - [x] Auth: authenticated user; 422 if no `AiProviderConfig` exists
  - [x] File trimming: read first 10 data rows, discard remainder before AI call
  - [x] 422 for non-parseable CSV input
  - [x] 502 for AI provider failure, with user-facing message
- [x] Validate canonical field names on `POST`/`PATCH` profile save (closed enum enforcement against the defined list)
- [x] Validate `sign_handling` accepts only `as_is` and `inverted` for user profiles
- [x] Validate `amount` and `date` are present in `mapping_json` on save
- [x] Emit validation warning when two columns map to the same target other than `comment` or `reference`
- [x] Populate `source_category` on draft payload when a `category` column is mapped; do not forward to `normalized_transaction`
- [x] Concatenate `reference` column values into `normalized_transaction.comment` (appended after `comment` values using the configured separator)
- [x] Document canonical field name list in code (single source of truth, shared with frontend via API or constant)
  - Implemented as `App\Enums\ImportCanonicalField` enum; values exposed in validation and prompt construction

**Implementation Status (2026-06-23)**:

- Milestone 4 frontend tasks listed below are implemented.
- Frontend component tests are not implemented yet because the repository does not currently have a working Vue component test harness configured.

**Changes from original design (2026-06-23)**:

- `ProfileCreationWizard` is integrated directly into `FileImportProfileManager`: clicking "New profile" for CSV profiles opens the wizard in-place (no separate route/page).
- The wizard also handles the AI suggestion panel internally; no separate wizard entry-point component was needed.
- The "Suggest with AI" button is also available in the existing CSV edit form (as a re-suggestion panel), satisfying the spec requirement that it be available during editing.
- `hasAiProvider` flag is passed from `ImportController` via `JavaScriptFacade::put()` and accessed as `window.hasAiProvider` in the Vue layer.
- Privacy notice is rendered inline in the AI panel rather than as a modal — functionally equivalent and simpler.

**Changes from original design (2026-06-23, Step 3 UX refinement)**:

- Step 3 uses an **integrated mapping table** rather than a list of `ColumnMappingRow` components. Source header names (row 1 of `<thead>`), canonical field dropdowns (row 2 of `<thead>`), and sample data rows (`<tbody>`) are shown in a single scrollable table. This keeps the source data visible while mapping columns.
- `ColumnMappingRow` and `AmountFormatPreview` components are implemented but not used in the wizard; their functionality is absorbed into the integrated table.
- The date format selector appears as a compact panel **above** the integrated table (not inline per-column). Sample values are not repeated in the panel; instead, parsed date values are shown inline beneath each raw value in the date-mapped table cells.
- `dateFormat` is a single profile-level string matching `FileImportProfile.date_format`, not a per-column array.
- Duplicate mapping validation **blocks step 3 → step 4 navigation** rather than surfacing only at save time.
- Date pattern matching tolerates trailing text after the date value (e.g. `, péntek` in Hungarian bank exports), consistent with PHP's `DateTime::createFromFormat` behaviour. The shared client-side logic lives in `resources/js/import/utils/dateFormatUtils.js` and is used by both `DateFormatSelector` and the wizard's inline table preview.
- `DateFormatSelector` always shows a base set of generic formats (`Y-m-d`, `d/m/Y`, `d.m.Y`, `Y.m.d.`) in addition to auto-detected and locale-specific candidates, so the picker is never empty even without sample values.

**Frontend Tasks**:

- [x] Implement `ProfileCreationWizard` component
  - [x] Step 1: file selection, client-side auto-detection (delimiter, header row heuristic), preview table render
  - [x] Step 2: parser settings form (delimiter, header row, decimal/thousand separator, sign handling, profile name) with live preview re-render
  - [x] Step 3: column mapping — integrated table (header name row + dropdown row + sample data rows); `DateFormatSelector` shown as a panel above the table when a date column is mapped; parsed amount and date values shown inline in table cells; validation gate enforcing required mappings and rejecting duplicate mappings
  - [x] Step 4: save via `POST /api/v1/imports/file-profiles`; field-level error display on failure
- [x] Implement `CsvPreviewTable` component (reactive, updates on settings change)
- [x] Implement `ColumnMappingRow` component (canonical field dropdown, conditional sub-controls)
- [x] Implement `DateFormatSelector` component (sample values, auto-detected candidates, custom input)
- [x] Implement `AmountFormatPreview` component (raw and parsed value side-by-side)
- [x] Add "Suggest with AI" button to profile creation form
  - [x] Visible only when user has `AiProviderConfig`; contextual note linking to AI settings otherwise
  - [x] Separate file picker for sample CSV
  - [x] Privacy notice before submission: provider name, API key usage
  - [x] Pre-fill wizard fields from suggestion response
  - [x] Render `confidence_notes` as per-field helper text; visually highlight low-confidence fields
- [x] Add profile export action to profile management UI (JSON download)

**Testing Tasks**:

- [x] Unit test: `AiImportProfileSuggestionServiceTest` (see Testing Strategy section)
- [x] Feature test: `ImportProfileSuggestTest` (see Testing Strategy section)
- [ ] Frontend component tests: `ProfileCreationWizard.spec.js`, `CsvPreviewTable.spec.js`, `ColumnMappingRow.spec.js`, `DateFormatSelector.spec.js`

**Deliverable**: Users can create user profiles through the guided wizard without writing JSON. Users with a configured AI provider can generate a profile draft from a CSV sample with one click.

---

### Milestone 5: Documentation & Polish

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
