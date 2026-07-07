# AI Document Processing (Technical Reference)

This file contains the implementation-oriented material extracted from the main feature specification. It covers architecture, data structures, technical flow, integrations, and operational constraints.

## Assumptions

- Laravel 12 + MySQL + Redis queues are available.
- AI providers require user-supplied API keys and billing setup.
- Storage limits are managed by self-hosted users.
- For MVP, AI providers are OpenAI and Gemini, configured via static list.
- Original uploaded and imported files are stored by the application; resized images are generated on-the-fly for Vision AI and not persisted.
- AI processing is always asynchronous; email notifications are used for failures and completion.
- PDF text extraction using smalot/pdfparser is always available (no OCR required for text-based PDFs).
- OCR for images is optional and requires either Tesseract (self-hosted) or Vision AI (cloud-based) to be enabled and available.
- If a document contains images but no OCR method is available/enabled, processing fails with clear user notification.
- Per-user AI behavior settings override global config values; null DB values fall back to global config, then safe hardcoded defaults. `ai_enabled = false` hard-blocks all new AI processing while letting in-flight jobs complete.

## Backend Scope (Laravel)

- Models and their most important fields:
  - `AiDocument`
    - `status` (enum: `ready_for_processing`, `processing`, `processing_failed`, `ready_for_review`, `finalized`)
      - `ready_for_processing` - Initial state after document submission/import, queued for AI processing, all minimum required data present
      - `processing` - Currently being processed by AI
      - `processing_failed` - AI processing failed after configured retries
      - `ready_for_review` - Processing complete, awaiting user review
      - `finalized` - Transaction created from draft
    - `source_type` (enum: `manual_upload`, `received_email`, `google_drive`)
    - `processed_transaction_data` (JSON, nullable)
    - `google_drive_file_id` (nullable)
    - `received_mail_id` (nullable, FK to existing received_mails table)
    - `custom_prompt` (nullable)
  - `AiDocumentFile`
    - `ai_document_id` (FK to AiDocument)
    - `file_path` (location in local storage)
    - `file_name`
    - `file_type`
  - `CategoryLearning`
    - `item_description`
    - `category_id`
    - `usage_count`
  - `AiProviderConfig`
    - `provider` (validated against static list from config)
    - `model` (validated against provider’s model list)
    - `api_key` (encrypted cast)
  - `GoogleDriveConfig`
    - `user_id` (foreign key, NO unique constraint - allows multiple configs in future, MVP enforces one per user at application level)
    - `service_account_email` (extracted from JSON, displayed in UI)
    - `service_account_json` (encrypted cast, full JSON credentials)
    - `folder_id` (Google Drive folder ID)
    - `folder_name` (varchar 255, nullable — display name for the import folder) (✅ added in Version 2)
    - `post_import_actions` (JSON array, nullable — replaces `delete_after_import`) (✅ added in Version 2)
    - `processed_folder_id` (varchar 255, nullable, indexed) (✅ added in Version 2)
    - `processed_folder_name` (varchar 255, nullable) (✅ added in Version 2)
    - ~~`delete_after_import` (boolean, default false)~~ (✅ removed in Version 2, migrated to `post_import_actions`)
    - `enabled` (boolean, default true)
    - `last_sync_at` (timestamp, nullable)
    - `last_error` (text, nullable - stores last error message)
    - `error_count` (integer, default 0 - future use for tracking failures)
    - `sync_interval_minutes` (integer) — per-config polling cadence; replaces global `AI_GOOGLE_DRIVE_SYNC_INTERVAL_MINUTES` env var
  - `AiUserSettings`
    - One row per user (`user_id` FK, unique); rows eagerly created for all users at migration time
    - `ai_enabled` (boolean, default false) — hard gate for all AI processing actions
    - `ocr_language` (string(64), default 'eng') — per-user Tesseract/OCR language
    - `image_max_width_vision` / `image_max_height_vision` (unsigned smallint, nullable) — optional Vision API resize cap
    - `image_quality_vision` (unsigned tinyint, default 85) — JPEG quality for Vision API
    - `image_max_width_tesseract` / `image_max_height_tesseract` (unsigned smallint, nullable) — optional Tesseract resize cap (`null` = no downscale; use only to cap very large images that cause resource or timeout issues)
    - `asset_similarity_threshold` (decimal(4,3), default 0.5) — minimum score for asset match candidates
    - `asset_max_suggestions` (unsigned tinyint, default 10) — max asset candidates sent to AI
    - `match_auto_accept_threshold` (decimal(4,3), default 0.95) — score above which an asset match is auto-accepted without AI confirmation
    - `duplicate_date_window_days` (unsigned tinyint, default 3)
    - `duplicate_amount_tolerance_percent` (decimal(5,2), default 10.0)
    - `duplicate_similarity_threshold` (decimal(4,3), default 0.5)
    - `category_matching_mode` (string(32), default 'child_preferred') — one of `best_match`, `parent_only`, `parent_preferred`, `child_only`, `child_preferred`
    - `warn_on_child_mode_without_children` (boolean, default true) — surface warning when child-oriented mode is active but no child categories exist
  - Update existing `ReceivedMail` model to reflect new app behavior. (✅ implemented)
    - Remove `transaction_data`, `processed`, and `handled` flags, as AIdocument processing supersedes them. (✅ implemented)
    - Remove `transaction_id` FK, as transactions are now linked via AiDocument. (✅ implemented)
    - `ReceivedMail` is no longer a standalone entity in the UI; it is linked to `AiDocument` only. (✅ implemented)
    - **Deviation:** ReceivedMail relationship to AiDocument changed from `belongsTo` to `hasOne` (one email can create one document, not the reverse)

- Controllers / APIs:
  - `AiDocumentApiController`
    - Serves as the main API for managing AI documents and their processing lifecycle.
  - `AiProviderConfigApiController`
    - Serves as the main API for managing AI provider configurations.
  - `GoogleDriveConfigApiController`
    - Manages Google Drive configurations and sync operations.
    - `GET /api/v1/google-drive/config` → show current config
    - `POST /api/v1/google-drive/config` → create config
    - `PATCH /api/v1/google-drive/config/{id}` → update config (includes `folder_name`, `processed_folder_id`, `processed_folder_name`, `post_import_actions`)
    - `DELETE /api/v1/google-drive/config/{id}` → delete config
    - `POST /api/v1/google-drive/config/test` → test connection; returns `folder_name`, `file_count`, and `capabilities` map per disposition action
    - `POST /api/v1/google-drive/config/{id}/sync` → manually trigger a one-time sync
    - `GET /api/v1/google-drive/config/{id}/folder-name` → re-fetch display name for a given folder ID; accepts `?folder_id=` query param to support both import and processed folder
    - `GET /api/v1/google-drive/config/{id}/folders` → list child folders accessible to the service account (for folder browser UI); returns `[{ id, name }]`
  - `PayeeStatsApiController`
    - New API endpoint to fetch payee category stats for AI prompt optimization, and also used by PayeeApiController for default category suggestion on payee selection.
  - `AiUserSettingsApiController`
    - `GET /api/v1/ai/settings` → `api.v1.ai.settings.show` — returns fully resolved settings for the authenticated user; includes non-blocking warning payload when `warn_on_child_mode_without_children` is true and no active child categories exist
    - `PATCH /api/v1/ai/settings` → `api.v1.ai.settings.update` — partial update; returns updated resolved settings; PATCH-only, no dedicated reset-to-defaults endpoint
    - Validation via `AiUserSettingsRequest` (thresholds 0.0-1.0, amount tolerance 0.0-100.0, `category_matching_mode` in allowed enum; upload limits validated from global config only)
    - Serialization: `AiUserSettingsResource`; authorization: `AiUserSettingsPolicy`

- Services / Jobs:
  - `ProcessDocumentService`
    - Orchestrates full document processing pipeline
    - Validates files (type, size)
    - Extracts text/content via injected `TextExtractionService` for all file types:
      - **PDF files:** smalot/pdfparser (native) or Tesseract/Vision API (scanned)
      - **Images (JPG/PNG):** Tesseract binary/HTTP mode → Vision API fallback
      - **Text files (TXT):** Direct read
    - Automatically handles mode selection (binary/http/cloud) based on configuration
    - Prepares AI prompts with transaction type-specific schemas (standard vs investment)
    - Fetches payee category statistics to optimize item categorization
    - Calls AI provider via Prism (text or vision-enhanced completion)
    - Validates AI response against schema
    - Updates document status (centralized status management)
    - Auto-accept threshold, category matching mode, and `ai_enabled` gate sourced from user AI settings; blocks processing start if AI is disabled
  - `AiProcessingJob`
    - Wraps ProcessDocumentService for async execution
    - Implements retry logic (3 attempts, 30s delay)
    - Event-driven: dispatches success/failure events, no duplicate status updates
    - shouldNotRetry() for fail-fast on auth/quota errors
  - `AssetMatchingService`
    - Calculates similarity scores using package `edgaras/strsim`
    - Ignores similarity < threshold, to avoid polluting the AI prompt
    - Filters and ranks accounts/payees/investments
    - Returns top N matches if > N exist, else all
    - Formats asset list for AI prompt (ID: Name|Alias)
    - Similarity threshold and max suggestions sourced from user AI settings
  - `DuplicateDetectionService`
    - Queries transactions within date window and amount range
    - Calculates similarity scores for matches
    - Returns array of transaction IDs with scores > threshold
    - Checks: type, date, amount (within tolerance), account/payee/investment
    - Date window, amount tolerance, and similarity threshold sourced from user AI settings
  - `CategoryLearningService`
    - Normalizes item descriptions (lowercase, trim, punctuation)
    - Saves/updates learning records on transaction save
    - Retrieves learning data for AI prompt context
    - Increments usage_count on match
  - `PayeeCategoryStatsService`
    - Shared aggregation service used by `PayeeStatsApiController`, `PayeeApiController`, and `ProcessDocumentService`
    - Computes per-payee category usage for a 6-month window
    - Provides dominant-category data for default payee suggestion and AI document shortcut logic
  - `GoogleDriveService`
    - Handles Google Drive API interactions using service account credentials
    - Uses `google/apiclient` package
    - Key methods:
      - `getFolderName(string $folderId): string` — fetches the Drive folder `name` field for display purposes
      - `listFolders(GoogleDriveConfig $config, ?string $parentId = null): array` — lists direct child folders (queries both root-parented and `sharedWithMe` folders); returns `[{ id, name }]`; limited to 10 results per query (root-parented + `sharedWithMe`), with a `truncated` flag when more pages exist
      - `deleteFile(string $fileId): bool` — calls `files.delete`
      - `trashFile(string $fileId): bool` — calls `files.update` with `trashed = true`
      - `moveFile(string $fileId, string $targetFolderId, string $currentParentId): bool` — calls `files.update` to add new parent and remove old parent
      - `renameFile(string $fileId, string $newName): bool` — calls `files.update` with new name
      - `attemptDisposition(GoogleDriveConfig $config, string $fileId, string $originalName, string $currentParentId): DispositionResult` — tries each action in `post_import_actions` order; returns value object with `success: bool`, `action_used: ?string`, `failure_reasons: array`
    - Error handling:
      - Authentication/permission errors: Throw specific exception (triggers config disable)
      - Other errors: Log and continue (silent fail)
  - `GoogleDriveMonitorJob`
    - Handles scheduled monitoring of Google Drive folders for new files
    - Runs for all users with `enabled = true` in `google_drive_configs`
    - Eligibility check uses each config's `sync_interval_minutes` (scheduler trigger remains every minute)
    - For each eligible config:
      - Dispatches `ProcessGoogleDriveConfigJob($config->id)` to queue
  - `CreateAiDocumentFromSource`
    - Handles `EmailReceived` and `DocumentImported` events
    - Creates `AiDocument` records for received-email and Google Drive sources
    - For received email, stores cleaned email body content as a text file for downstream processing
    - Email attachments are ignored in the MVP
  - `MailHandler`
    - Persists incoming email content into `ReceivedMail`
    - Fires the `EmailReceived` event that starts AI document creation for email sources
  - `AiUserSettingsResolver`
    - Provides effective per-user AI settings with fallback precedence: user DB value → global config → safe hardcoded default
    - Row created on first access as fallback to the eager migration backfill
    - Used by all AI processing services to retrieve user-scoped configuration

- Events / Notifications:
  - **Ingestion Events:**
    - `EmailReceived` event fired when an email is captured (replaces IncomingEmailReceived)
    - `DocumentImported` event fired when a file is imported from Google Drive
    - `CreateAiDocumentFromSource` handles both events and creates or finalizes the corresponding `AiDocument` source records
    - MailHandler fires `EmailReceived` with a `ReceivedMail` instance
    - For email sources, the listener stores cleaned email body content as a text file for downstream processing
  - **Processing Events:**
    - `AiDocumentProcessedEvent` - Fired on successful processing (status → ready_for_review)
    - `AiDocumentProcessingFailedEvent` - Fired on processing failure and carries the document plus error details (`errorMessage`, `exceptionClass`, `errorCode`)
  - **Email Notifications:**
    - `SendAiDocumentProcessedNotification` - Queued listener for success emails
    - `SendAiDocumentProcessingFailedNotification` - Queued listener for failure emails with error details
    - Google Drive import success and failure notifications are sent directly from `ProcessGoogleDriveConfigJob` using user notifications, not AI-document events
  - **Notification Details:**
    - **Processing success (ready for review)**
      - Mailable: `App\Mail\AiDocumentProcessed`
      - Subject: "Document processed - Review your transaction"
      - Content: Document ID, source type, extracted amount/payee, link to review page
      - View: `resources/views/emails/ai-document-processed.blade.php`
    - **Processing failure (AI error, after depleting retries)**
      - Mailable: `App\Mail\AiDocumentProcessingFailed`
      - Subject: "Document processing failed"
      - Content: Document ID, error type (auth/quota/model/network/ocr_unavailable), suggested action, link to AI config settings
      - View: `resources/views/emails/ai-document-processing-failed.blade.php`
      - **OCR Unavailable Error:** If document contains images but neither Tesseract nor Vision AI is available/enabled:
        - Error type: `ocr_unavailable`
        - Suggested actions:
          - Enable Tesseract OCR (see Docker setup instructions)
          - Enable Vision AI in AI Provider settings (requires vision-capable model like gpt-4o or gemini-1.5-pro)
          - Upload text-based PDF instead of images
    - **Google Drive import notifications**
      - Sent from `ProcessGoogleDriveConfigJob` via user notifications
      - Success path uses `GoogleDriveImportSuccess`
      - Failure path uses `GoogleDriveImportFailed`
      - On authentication or permission failures, the configuration is disabled before the failure notification is sent

## Frontend Scope (Vue + Bootstrap)

- Pages / Routes:
  - `Documents Index` - `/ai-documents`
    - Features: DataTable with filters (status, source_type), pagination, search
  - `Document Review` - `/ai-documents/{id}`
    - Features: file preview, draft transaction display, finalize button, reprocess button
  - `AI Settings` - `/user/ai-settings`
    - Presents a dedicated AI settings page instead of reusing the general user settings page
    - Includes environment information for incoming email handling and local OCR availability
  - `AI Provider Settings` - `/user/ai-settings`
    - Features: provider/model selection, API key input, test connection, create/update/delete
  - `Google Drive Settings` - `/user/ai-settings`
    - Features:
      - Service account email display
      - Service account JSON upload (file input + existing paste textarea with show/hide toggle)
        - File input accepts `.json`; browser-side `FileReader` populates the textarea; validation rejects non-JSON and files over 100 KB
      - Folder name input group (editable, max 255 chars) with re-fetch button; folder ID displayed below in read-only style
        - Re-fetch overwrites an empty field automatically; prompts confirmation if the field already has content
      - Folder browser: "Browse" button opens a modal listing Drive folders accessible to the service account; selecting a folder populates the ID and triggers a name re-fetch
      - Post-import disposition: labelled checkbox group ("After successful import") replacing the old delete toggle
        - Actions in fixed priority order: Delete permanently → Move to Trash → Move to Processed folder → Rename with `processed_` prefix
        - Selecting "Move to Processed folder" reveals a sub-form with processed folder ID input (URL-parsing), name input, and re-fetch button
        - After test connection, each checkbox shows a capability badge (Verified / Warning / Not tested) based on the `capabilities` map returned by the test endpoint
        - A collapsible tip block explains the `yaffa.txt` real-file testing approach
      - Sync interval field (minutes; per-config cadence)
      - Enabled toggle and manual sync button
      - Last sync timestamp display
      - Connection test button; on success, auto-populates `folder_name` if empty
      - Per-user configuration management within the AI settings page
  - `AI Behavior Settings` - `/user/ai-settings`
    - Features: AI-on/off toggle, OCR language, Vision API and Tesseract image constraints, matching thresholds, duplicate-detection settings, category matching mode
    - UX warning: non-blocking alert when child-only/child-preferred mode is active but user has no active child categories; save remains allowed
  - **ReceivedMail UI:**
    - No user-facing pages or CRUD actions for `ReceivedMail`, as these are now exposed as AiDocument sources.

## Processing Flow

1. User submits document (web, email, or Google Drive).
   - When a submission contains multiple files, it is still considered to be one AiDocument.
   - E.g. a longer receipt could be attached using multiple photos
2. `AiDocument` record created with status `ready_for_processing` (initial state).
3. `AiProcessingJob` runs:
   - Uses `TextExtractionService` to extract text from all file types
   - Tesseract (binary or HTTP) or Vision API invoked automatically per configuration
   - Builds AI prompt with transaction type-specific schemas (standard vs investment)
   - Includes normalized assets and category learning data
   - Calls configured AI provider (e.g. OpenAI/Gemini) via Prism in multiple steps
     - Extract generic transaction details (date, amount, payee, accounts, investment, currency)
     - Identify account(s)/payee/investment matches from user's database using local exact match or AI-assisted matching
     - For withdrawals and deposits, if items are detected, try to identify line item level category mappings. Either use local exact match or AI-assisted matching based on item description.
   - Validates output schema
   - Stores JSON draft in `processed_transaction_data`
4. Status set to `ready_for_review` and email notification sent.
5. User opens document review (`/ai-documents/{id}`) and can view extracted details.
   - "Extracted details" tab displays all transaction data comprehensively
   - Finalize button triggers transaction form
6. Transaction Form Integration:
   - Frontend calls existing transaction form component (reuse from transactions feature)
   - The form receives prepopulated data from `processed_transaction_data` JSON
   - If there are less than 5 line items, the form should be displayed in the modal container; otherwise, redirect to full page transaction form
   - Field mapping should be automatic based on existing transaction form structure and JSON structure
   - User can edit any field before saving
   - The database is checked for duplicates, and warning is displayed above form (if present)
7. Details for the item-level category mapping:
   - Each item in the `items` array is rendered as a separate line item in the transaction form
   - The `amount` field maps to the line item amount
   - If `recommended_category_id` is provided, and the `match_type` is `exact`, or if the `confidence_score` is above a certain threshold, preselect that category in the dropdown
   - User can change category selection freely, and buttons are added to allow or deny AI suggestions
8. User saves transaction form:
   - Transaction created via existing transaction creation endpoint
   - `ai_document_id` added to transaction record
   - `AiDocument` status updated to `finalized`
   - Category learning records created/updated based on final item-category mappings
   - The modal is closed or the user is redirected to the transaction details page (optionally, they can select to return to the document list)
   - The success message (toast or bootstrap alert) is based on the container used, and includes a link to view the created transaction
   - When the transaction is saved successfully, then increment the usage_count for each CategoryLearning record used in the transaction items, which was not overridden by the user.

A few notes on the statuses

- The user cannot directly change the status, except some activities performed by them, triggering status changes. E.g. finalize a transaction.
- This means, that `processing_failed` status cannot be forced into transaction creation, but a reprocessing can be requested. This means, that the general processing transaction should be restarted by the responsible jobs.

## Matching & Learning

- Asset matching (accounts, payees, investments):
  - Use `edgaras/strsim` package to calculate similarity scores
  - Send all matches if < N; otherwise send top N sorted by similarity score descending.
  - AI returns best single match only, or N/A if no match.
  - For investments, match against `name`, `code`, and `isin` fields.

- Category learning
  - **Exact Local Matching:**
    - Flat table with normalized item descriptions (`item_description` field in `category_learning` table).
    - Normalization: lowercase, trim, remove punctuation.
    - If exact normalized match found with active category, use immediately (no AI call).
    - Match type: `'exact'`, confidence score: `1.0`.
  - **AI-Assisted Matching (Batch Processing):**
    - When no exact match exists, batch all unmatched items in single AI call for efficiency.
    - Gather similar learning records per item using `edgaras/strsim` package with 0.5 threshold (configurable).
    - Provide AI with: item descriptions, similar learning patterns, and full active category list.
    - AI returns: `recommended_category_id`, `confidence_score` (0.0-1.0) per item.
    - Validate category exists and is active before accepting AI suggestion.
    - Match type: `'ai'`, confidence score from AI response.
  - **Learning Storage:**
    - Category learning records created/updated only on transaction save (not during AI processing).
    - Usage count incremented when user accepts suggested category without modification.

## Duplicate Transaction Detection

- Returns multiple matches above threshold.
- Threshold rules:
  - Date within 3 days.
  - Amount difference within 10%.
  - Asset match (account + payee OR account + investment OR both accounts for transfers).
- Matches sorted by similarity score.
- This is not part of the AI processing, but performed before transaction finalization.

## Retry Strategy (Hybrid)

- Max retries: 3
- Delay: 30 seconds
- Fail-fast for auth/quota errors.
- Retry for transient/LLM non-deterministic responses.
- No user action required for retries, and there's no user notification until final outcome (success or failure).

## File Storage & Retention

- **Storage structure:**
  - Original files: `storage/app/ai_documents/{user_id}/{document_id}/{filename}`
  - Use Laravel's `Storage` facade with `local` disk
  - File naming: preserve original filename, prepend timestamp if duplicate
- **Image resizing:**
  - Performed on-the-fly using **intervention/image** package
  - Optionally resize to max pixels defined by the user. This can be different for Tesseract vs Vision API based on user preference and performance considerations.
  - Resized images not persisted (memory only, temporary for Vision API call)
  - Original files always retained
- **Retention: (non-MVP, not implemented yet)**
  - Environment variable: `AI_DOCUMENT_FILE_RETENTION_DAYS=90` (default)
  - Empty or `0` disables auto-deletion
  - Cleanup job: `php artisan ai-documents:cleanup-old-files`
  - Scheduled daily via Laravel scheduler
  - Only deletes files, not database records
- **File upload limits:**
  - Max files per submission: 3 (configurable via `AI_DOCUMENT_MAX_FILES_PER_SUBMISSION`)
  - Max file size: 20MB per file (configurable via `AI_DOCUMENT_MAX_FILE_SIZE_MB`)
  - Allowed types (configurable via `AI_DOCUMENT_ALLOWED_TYPES`): pdf, jpg, jpeg, png, txt

## Google Drive Monitoring

- **Service Account Configuration (MVP):**
  - Uses Google Cloud Service Account for authentication (no OAuth flow required)
  - Credentials stored in `google_drive_configs` table (one per user for MVP, database allows multiple for future)
  - Service account JSON key uploaded by user and stored encrypted
  - Service account email extracted from JSON and displayed in UI (non-sensitive identifier)
  - User must manually share target Google Drive folder with service account email
  - **MVP Enforcement:** Application-level check prevents multiple configs per user (database allows it for future expansion)
- **Folder Selection:**
  - Manual folder ID input in settings UI
  - Smart URL parsing: UI extracts folder ID from full Drive URL automatically
    - Example: `https://drive.google.com/drive/folders/1a2b3c4d5e6f` → extracts `1a2b3c4d5e6f`
  - Tooltip with example URL format and extraction instructions
  - Validation during test connection
  - Each folder ID field is paired with a `folder_name` display-name field (varchar 255, nullable):
    - Editable text input (max 255 chars) stored in `google_drive_configs.folder_name`
    - Re-fetch button calls `GET /api/v1/google-drive/config/{id}/folder-name`; overwrites the field only if it is empty, or the user confirms an overwrite
    - On successful test connection, if the response includes `folder_name` and the local field is empty, auto-populated
    - Folder ID remains visible below the name field in a read-only style
    - Folder name is cosmetic only and must never be used as a lookup key; nullable — clearing it is valid
  - Folder browser button opens a modal listing Drive folders visible to the service account:
    - Queries both root-parented and `sharedWithMe` folders; limit 10 per request
    - Selecting a folder populates the ID and triggers a name re-fetch
    - Button disabled with tooltip when config is not yet saved
    - UI note: "Only folders shared with the service account are shown"
  - The same display-name and browser pattern applies to the optional Processed folder (see Post-Import Disposition)

- **Service Account JSON Validation:**
  - Must be valid JSON format
  - Required Google service account keys validated:
    - `type` (must be "service_account")
    - `project_id`
    - `private_key_id`
    - `private_key`
    - `client_email`
    - `client_id`
    - `auth_uri`
    - `token_uri`
  - Extract `client_email` and store separately for UI display
  - Full JSON stored encrypted (never exposed in API responses)

- **UI Security:**
  - Service account JSON field: password type with show/hide toggle button
  - Follow Vue best practice for password visibility toggle
  - Placeholder text changes based on create/update mode:
    - Create: "Paste your Google Cloud Service Account JSON key"
    - Update: "Leave blank to keep existing credentials"
  - Never populate field from backend (always empty on load)
  - Support `__existing__` placeholder for test connection
- **File Tracking:**
  - Store `google_drive_file_id` in `ai_documents` table (varchar 255, nullable, indexed)
  - Duplicate prevention: Check if `google_drive_file_id` exists before importing
  - Deleted AiDocument handling: If user deletes AiDocument but file still exists in Drive, re-import on next sync (no permanent skip list)
  - Track `last_sync_at` timestamp in `google_drive_configs` table
- **Monitoring Schedule:**
  - Job: `App\Jobs\GoogleDriveMonitorJob`
  - Current behavior: configurable via user settings
  - Only processes configs with `enabled = true`
  - Manual trigger: User can request one-time sync via UI button (queues job immediately)

- **File Import Flow:**
  1. List files from folder (filter by modified date > last_sync_at; skip files named exactly `yaffa.txt`; skip files whose name starts with `processed*`)
  2. For each file:
     - Check if `google_drive_file_id` exists in `ai_documents` (skip if duplicate)
     - **Download file first** to `storage/app/ai_documents/{user_id}/{temp_id}/{filename}`
     - **Only if download succeeds**, create AiDocument record with `google_drive_file_id`
     - Fire `DocumentImported` event (existing listener handles rest)
     - Call `GoogleDriveService::attemptDisposition()` — tries each action in `post_import_actions` order, stops at first success
     - Attach `DispositionResult` to the import notification payload; a failed disposition does not fail or roll back the completed import
  3. Update `last_sync_at` on config
  4. One file = one AiDocument (no grouping)

- **Post-Import File Disposition:**
  - Replaces the former `delete_after_import` boolean flag
  - Stored in `google_drive_configs.post_import_actions` (JSON array of action keys); null/empty means no action — file is left in place
  - Disposition actions (fixed priority order, cannot be reordered by user):

    | Priority | Key                 | What it does                                        | Required permission                      |
    | -------- | ------------------- | --------------------------------------------------- | ---------------------------------------- |
    | 1        | `delete`            | Permanently removes the file from the folder        | `drive` scope + file owner               |
    | 2        | `trash`             | Moves the file to the owner's Trash                 | Same constraints as delete               |
    | 3        | `move_to_processed` | Moves the file into a configured "Processed" folder | Write/editor access to the target folder |
    | 4        | `rename_processed`  | Renames file to `processed_<original_name>`         | Write/editor access to the file itself   |

  - In the common Gmail-owner + service-account-editor model, `delete` and `trash` will fail because the Drive API only lets the file owner permanently delete or trash files they own. `move_to_processed` and `rename_processed` require only editor access and succeed in this model.
  - `processed_folder_id` (varchar 255, nullable, indexed) and `processed_folder_name` (varchar 255, nullable) stored in `google_drive_configs`; required when `post_import_actions` contains `move_to_processed`; must differ from the import `folder_id`
  - If all enabled actions fail, the import notification includes a non-blocking warning
  - Migration note: existing rows with `delete_after_import = true` were converted to `post_import_actions = ["delete", "trash"]`; the column was then dropped

- **Error Handling:**
  - **Authentication/Permission Errors** (fail-fast):
    - Invalid service account JSON
    - Service account not shared the folder
    - Insufficient permissions
    - **Action:** Set `enabled = false`, store error in `last_error`, send email notification to user
  - **Other Errors** (silent fail for MVP):
    - Network timeouts
    - API rate limits
    - Individual file download failures
    - **Action:** Log error, continue processing remaining files
  - **Future Enhancement:** Track `error_count`, notify user after N consecutive failures

- **Connection Testing:**
  - Endpoint: `POST /api/v1/google-drive/config/test`
  - Tests performed:
    - Authenticate with service account JSON
    - Access specified folder (verify read permission); extract folder `name` for display
    - List files (return count)
    - Capability check for all disposition actions (see below)
  - **Disposition capability check:**
    - Primary strategy: uses a file named exactly `yaffa.txt` placed by the user in the import folder
      - Probes actions in priority order; the first success stops the cascade; remaining actions are `null`
      - If `delete` succeeds, the file is gone; other capabilities cannot be tested in that run
      - If `move_to_processed` succeeds, `rename_processed` is inferred as `true` (both require editor write access)
      - If `processed_folder_id` is not yet configured, `move_to_processed` is returned as `null` (unknown), not `false`
    - Fallback strategy (no `yaffa.txt` found): uses a service-account-owned temp file; results marked `capabilities_source: "estimated"` and a UI notice is shown — these approximate the service account's own permissions, not its ability to modify user-owned files
    - Response includes `recommended_actions` based on the highest-priority action that returned `true`
    - `yaffa.txt` is excluded from regular import scans (silently skipped) regardless of other filter conditions
  - Response example (typical Gmail-owner + editor scenario):
    ```json
    {
      "folder_accessible": true,
      "folder_name": "YAFFA Import",
      "file_count": 3,
      "capabilities_source": "real_file",
      "capabilities": {
        "delete": false,
        "trash": false,
        "move_to_processed": true,
        "rename_processed": true
      },
      "recommended_actions": ["move_to_processed", "rename_processed"]
    }
    ```

- **Service Account Setup Instructions (for users):**
  1. Create Google Cloud Project
  2. Enable Google Drive API
  3. Create Service Account
  4. Download JSON key file
  5. Share target Google Drive folder with service account email (visible in key file as `client_email`)
  6. Paste folder ID (or full URL) and JSON contents into YAFFA settings
  7. Test connection before saving

- **Non-MVP (Future Improvements):**
  - OAuth2 flow for end-user authentication (easier UX, no service account sharing needed)
  - Multiple folder monitoring per user (database already supports it)
  - Track consecutive error count, auto-disable after threshold
  - Permanent skip list for user-deleted documents
  - Pagination for folder browser (currently limited to 10 results per request)

## Email Content Cleanup

- To optimize AI token usage, implement email content cleanup before AI processing.
- Extend existing cleanup from `ProcessIncomingEmailByAi::cleanUpText()`:
  - Remove image references: `[image:.*?]`
  - Remove link references: `<http[^>]+>`
  - Remove inline styles (style attributes, `<style>` tags)
  - Remove inline SVG elements
  - Remove base64-encoded data URIs
  - Strip unnecessary HTML tags while preserving text structure
  - This lossy text-oriented cleanup (`CreateAiDocumentFromSource::cleanHtmlContent()`) is only applied to the copy used for the AI prompt, never to `ReceivedMail.html` itself
  - `ReceivedMail.html` (the column rendered to the user via `v-html` in `AiDocumentEmailViewer.vue`) is sanitized separately: `MailHandler` runs the raw inbound HTML through `EmailHtmlSanitizerService` (HTMLPurifier, allowlist-based) before it is ever persisted, and the frontend re-sanitizes with DOMPurify before rendering, as defense in depth

## Testing & Development Tools

- **Email Simulation Command:** `php artisan app:simulate-incoming-email`
  - Purpose: Test email reception and AI document creation locally without actual SMTP/mailbox setup
  - Options:
    - `--from=EMAIL` - Sender email address (creates user if --create-user enabled)
    - `--subject=TEXT` - Email subject line
    - `--text=TEXT` - Plain text email body
    - `--html=HTML` - HTML email body
    - `--sync` - Process synchronously (skip queue, for debugging)
    - `--create-user` - Auto-create user if sender doesn't exist
  - Implementation:
    - Builds proper MIME multipart/alternative messages compatible with beyondcode/laravel-mailbox
    - Uses InboundEmail::fromMessage() to create mailbox-compatible objects
    - Invokes MailHandler directly
    - Supports text-only, HTML-only, or multipart messages
    - Event::fake() used when --sync to prevent duplicate processing

## OCR & Vision Processing Strategy

### Overview

YAFFA supports three methods for extracting text from documents, with automatic fallback based on file type and configuration:

1. **PDF Text Extraction** (always available)
   - Library: `smalot/pdfparser`
   - Use case: Text-based PDFs (invoices, statements, emails saved as PDF)
   - No OCR required, fastest method
   - Automatically used for all PDF files

2. **Tesseract OCR** (optional, self-hosted)
   - Library: Command-line `tesseract` via Symfony Process
   - Use case: Scanned documents, photo receipts, images
   - Requires: System installation OR Docker container
   - Configuration: `TESSERACT_ENABLED=true` + `TESSERACT_MODE=binary|http`
   - Cost: Free (compute only)
   - Accuracy: Good for high-resolution images
   - Binary mode: Local execution via PHP Process
   - HTTP mode: Docker sidecar via `franky1/tesseract-ocr` image (optional service in docker-compose.yml)

3. **Vision AI** (optional, cloud-based)
   - Library: Prism integration with OpenAI/Gemini vision models
   - Use case: Complex receipts, handwritten notes, low-quality scans
   - Requires: User's AiProviderConfig with vision-capable model selected
   - Cost: API token usage (~$0.01-0.05 per image depending on model)
   - Accuracy: Excellent, especially for complex layouts

### Processing Logic

**For PDF files:**

- Always attempt text extraction with smalot/pdfparser first
- If successful (text extracted), proceed with AI text completion
- If extraction is empty (scanned PDF), no OCR fallback is currently applied; processing fails with "No text could be extracted"

**For image files (JPG, PNG):**

- Check if Tesseract is enabled AND available:
  - If yes: Run Tesseract OCR on image (optionally downscaled if user has configured `image_max_width/height_tesseract`; default is no downscale)
  - If no: Check if Vision AI is enabled AND selected model supports vision
    - If yes: Resize image to 2048px max, send to Vision AI via Prism
    - If no: **Fail processing** with `OcrUnavailableException` error, notify user

**For text files (TXT):**

- Direct `file_get_contents()`, no OCR needed

### Configuration

**Environment Variables:**

```env
# Tesseract OCR Configuration
TESSERACT_ENABLED=true              # Enable Tesseract OCR for image processing
TESSERACT_MODE=binary               # 'binary' (local) or 'http' (Docker sidecar)
TESSERACT_PATH=/usr/bin/tesseract   # Path to tesseract binary (binary mode only)
TESSERACT_HTTP_HOST=tesseract       # Docker service name (http mode only)
TESSERACT_HTTP_PORT=8888            # Docker service port (http mode only)
TESSERACT_HTTP_TIMEOUT=30           # Request timeout in seconds
TESSERACT_LANGUAGE=eng              # OCR language (eng, fra, deu, etc.)
```

**User Workflow:**

1. User configures AI provider (OpenAI/Gemini) in settings
2. User selects model (gpt-4o, gemini-1.5-pro for vision support)
3. User chooses deployment mode: binary (local), http (Docker), or cloud-only
4. If images uploaded without OCR, clear error message with remediation steps

### Docker Deployment

**Optional Tesseract Service (docker-compose.yml):**

Added to `docker/docker-compose.yml` with smart profile-based activation

## AI Provider Configuration

### Supported Providers & Models (MVP)

- For MVP a static list of supported models and providers is used via app config
- Future improvements can introduce further providers and dynamic model fetching.

**OpenAI:**

- `gpt-4o` - GPT-4 Omni
- `gpt-4o-mini` - GPT-4 Omni Mini

**Gemini:**

- `gemini-2.5-pro` - Gemini 2.5 Pro
- `gemini-2.5-flash` - Gemini 2.5 Flash

## Prompt Engineering (based on existing patterns)

The system will use multi-step prompting similar to existing email processing:

1. **Main extraction prompt:** Extract transaction type, account, payee, date, amount, currency, and line items from document
2. **Account matching prompt:** Match extracted account name against user's account list (ID: Name|Alias format)
3. **Payee matching prompt:** Match extracted payee against user's payee list (ID: Name format)
4. **Investment matching prompt:** (if investment type) Match against user's investments (ID: Name|Code|ISIN format)
5. **Category matching prompt:** Batch match all line item descriptions against:
   - User's category learning data (past item descriptions with usage counts)
   - User's active categories (full list with parent/child hierarchy)

**IMPORTANT:** line item matching is only needed for withdrawals and deposits, not for transfers or investment transactions.
After payee matching, backend processing resolves category usage from shared payee-stats logic (the same logic powering `GET /api/v1/payees/{accountEntity}/category-stats`). If only one category is present in the 6-month stats, category matching is skipped and that category is assigned to the entire amount as one line item.

All prompts require JSON responses with strict schemas to ensure validation.

### Required Composer Dependencies

**PDF Processing:**

- `"smalot/pdfparser": "^2.0"` - Extract text from PDF files

**Image Processing:**

- `"intervention/image": "^3.11"` - Image resizing for Vision AI

**AI Integration:**

- `"prism-php/prism": "^0.99.19"` - Unified AI provider abstraction

**Text Similarity:**

- `"edgaras/strsim": "^1.1"` - Calculate similarity scores for asset and category matching

**OCR:**

- Tesseract OCR: No Composer package needed, uses command-line binary via Symfony Process

## Category Learning Management UI

### Feature Summary

Add a user-facing management UI for learned item-to-category mappings so users can review, clean up, and safely disable stale mappings. This extends the existing category-learning system from write-only behavior (learn on finalize) to full lifecycle management.

### Goals / Non-Goals

- Goals:
  - Show the authenticated user's category-learning mappings in a searchable, paginated list.
  - Support safe cleanup actions: soft archive/unarchive and hard delete.
  - Support controlled maintenance actions: edit, manual add, merge.
  - Integrate management entry points into existing category-management UX.
  - Keep AI processing resilient by ignoring archived/deleted mappings immediately.
- Non-Goals:
  - No global/admin cross-user management.
  - No AI prompt redesign beyond excluding archived mappings.
  - No bulk import/export in this phase.

### Assumptions

- Category learning rows are user-scoped (directly or by resolvable ownership path via category).
- Existing `CategoryLearningService` remains the source of normalization and upsert behavior.
- Soft archive is preferred for reversible cleanup; hard delete remains available for permanent removal.
- `usage_count` is system-managed and not directly editable by users.
- This management feature belongs to category management, not the AI document list or review screen.

### Explicit Behavioral Decisions

- Archive semantics:
  - Yes. Archived learnings are completely ignored during AI processing.
  - This includes exact local matching and AI prompt context payload generation.
  - Archive/unarchive takes effect immediately for new processing work.
- Re-learning an archived mapping:
  - If finalization tries to store a learning where an archived row already exists for the same normalized description and same category (same user scope), the system revives that row instead of creating a duplicate.
  - Revive means `active = true`; usage handling follows normal learning rules.
- Editing:
  - UI allows editing of `item_description` and `category_id` only.
  - `usage_count`, timestamps, and lifecycle metadata are read-only.
  - Server re-normalizes on save and enforces uniqueness constraints.
- Manual add:
  - UI allows manually adding learnings.
  - New manual rows are created as active with `usage_count = 0`.
  - If manual add matches an archived row key, backend should revive that row instead of creating a duplicate.
- Merge:
  - UI supports merge for cleanup/deduplication.
  - Benefit: reduce duplicate rows and consolidate evidence by summing `usage_count` into one surviving row.
  - Merge is constrained to safe cases (same normalized key and same category) to avoid semantic data loss.

### Placement in Product / Navigation

- Add a new section under category management, reachable from the categories area:
  - Primary entry: Category management page adds a `Learned Mappings` tab or sub-section.
  - Secondary entry: optional quick link from `/user/ai-settings` to category management `Learned Mappings` anchor/tab.
- Keep this feature out of `/ai-documents` list and `/ai-documents/{id}` to avoid mixing review flow with data-maintenance flow.

### Backend Scope (Laravel)

- Models:
  - Extend `CategoryLearning` with lifecycle field:
    - `active` (boolean, default true, indexed)
  - Keep existing fields (`item_description`, `category_id`, `usage_count`) unchanged.
- Migrations:
  - Add `active` column to `category_learning` table.
  - Add composite index to support UI filters and prompt fetches efficiently:
    - `(category_id, active)` and/or ownership + `active` depending on current schema.
  - Optional: backfill `active = true` for existing rows (implicit default behavior).
- Controllers / APIs:
  - New `CategoryLearningApiController` endpoints (under `/api/v1`):
    - `POST /api/v1/category-learning`
      - manually create mapping (`item_description`, `category_id`)
    - `GET /api/v1/category-learning`
      - list with filters: `search`, `category_id`, `status=active|archived|all`, `min_usage_count`, `sort`, `page`, `per_page`
    - `PATCH /api/v1/category-learning/{id}`
      - edit mapping (`item_description`, `category_id`)
    - `POST /api/v1/category-learning/{id}/archive`
      - archives a mapping (idempotent)
    - `POST /api/v1/category-learning/{id}/unarchive`
      - restores archived mapping (idempotent)
    - `DELETE /api/v1/category-learning/{id}`
      - permanently deletes one mapping
    - `POST /api/v1/category-learning/merge`
      - merge selected mappings into one target mapping
    - `POST /api/v1/category-learning/bulk-archive`
      - archives selected IDs
    - `POST /api/v1/category-learning/bulk-delete`
      - deletes selected IDs with confirmation payload
  - Optional UX helper endpoint:
    - `GET /api/v1/category-learning/stats`
      - returns counts by status and top categories for filter chips.
- Services / Jobs:
  - Introduce `CategoryLearningManagementService`:
    - Applies create/edit/archive/unarchive/delete/merge operations with ownership checks.
    - Performs conflict-safe normalization checks (prevent duplicate active mapping collisions for same normalized description + category rule, if required).
    - Implements revive-on-relearn behavior for archived duplicates.
    - Implements merge behavior with deterministic target selection and `usage_count` summation.
    - Emits lightweight domain events for audit/telemetry hooks.
  - Update `CategoryLearningService` read paths:
    - Ensure AI prompt context queries include only active (`active = true`) mappings.
    - Ensure lookup for exact local matching ignores inactive rows.
    - Ensure write path revives inactive exact matches before any insert.
- Policies / Auth:
  - Add `CategoryLearningPolicy`:
    - User can list/manage only own mappings.
    - No cross-user access via guessed IDs.
  - Use policy checks in all controller methods.
- Events / Notifications:
  - Optional internal events (no user email required):
    - `CategoryLearningArchived`, `CategoryLearningUnarchived`, `CategoryLearningDeleted`.
  - No queue requirement for MVP scale; synchronous controller actions are acceptable.

### Frontend Scope (Vue + Bootstrap)

- Pages / Routes:
  - Reuse existing category management route/page; add `Learned Mappings` tab/panel.
  - Optional deep-link query param for direct opening (e.g. `?tab=learned-mappings`).
- Components:
  - `CategoryLearningTable`:
    - columns: item description, category, usage count, status, last updated, actions
    - server-driven pagination/sorting/filtering
  - `CategoryLearningFilters`:
    - search input, category dropdown, status dropdown, usage-count threshold
  - `CategoryLearningRowActions`:
    - edit, archive/unarchive, delete with confirm modal
  - `CategoryLearningBulkActionsBar`:
    - multi-select + bulk archive/delete/merge
  - `CategoryLearningUpsertModal`:
    - create/edit form (`item_description`, `category_id`)
  - `CategoryLearningMergeModal`:
    - choose target row, show merge preview (`source_count`, `usage_count_sum`), require confirmation
  - `CategoryLearningDeleteConfirmModal`:
    - explicit irreversible warning for hard delete
- State management:
  - Local page state (filters, pagination, selected rows, loading/error states).
  - Keep implementation aligned with existing table/data-fetch conventions used in YAFFA (DataTables pattern where applicable).
- API interactions:
  - Initial fetch on tab load.
  - Refetch after create/edit/archive/unarchive/delete/merge actions.
  - Optimistic UI optional for archive/unarchive; delete should prefer confirmed refresh.
- UX / validation rules:
  - Default view shows active mappings only.
  - Archived rows visually muted and read-only for edit actions except unarchive/delete.
  - Manual add is available via `Add learning` action.
  - Edit allows only learning text and category selection; usage count is read-only.
  - Merge action is available only for merge-eligible selections.
  - Deleting requires explicit confirmation dialog text.
  - Empty-state guidance should explain how mappings are learned (through AI document finalization).

### Data & API Design

- Entity:
  - CategoryLearning (managed lifecycle)
- Relationship:
  - belongs to Category; user ownership enforced through category ownership and/or explicit user scope.
- List response shape (high-level):
  - `data[]`: `{ id, item_description, category: { id, name }, usage_count, active, updated_at }`
  - `meta`: pagination + applied filters
- Mutation response shape:
  - Return updated row resource for create/edit/archive/unarchive.
  - Return success summary for delete/bulk/merge operations (including affected IDs and resulting target ID for merge).

### Processing Behavior Changes

- Archive semantics are strict: archived mappings are fully excluded from processing reads.
- Exact local category matching must query active mappings only.
- AI-assisted context generation must exclude archived mappings.
- If a mapping is deleted/archived, subsequent document processing must stop using it immediately (no cache staleness beyond normal request lifecycle).
- If a matching archived row exists during learning write-back, revive and reuse it.

### Corner Cases

- Archived duplicate exists and user triggers manual add with same key:
  - Expected behavior: revive archived row, do not create duplicate.
- Edit changes text to collide with another active row key:
  - Expected behavior: reject with validation error (422), prompt user to merge/archive instead.
- Merge selection contains rows with different normalized keys or different categories:
  - Expected behavior: reject merge (422) and show reason.
- Merge contains active and archived rows (same key/category):
  - Expected behavior: valid; resulting row is active, usage counts summed.
- Category becomes inactive or deleted:
  - Expected behavior: learning remains visible for audit/history but excluded from matching until category is valid again (or row is cleaned up).

### Test Strategy

- Backend tests:
  - Feature tests for list endpoint filters, pagination, and ownership isolation.
  - Feature tests for create/edit validation and ownership constraints.
  - Feature tests for archive/unarchive/delete (single + bulk).
  - Feature tests for merge success/failure and `usage_count` summation.
  - Policy tests for unauthorized access and cross-user ID attempts.
  - Service tests proving archived mappings are excluded from exact-match and AI-context retrieval.
  - Service tests for revive-on-relearn behavior.
- Frontend tests:
  - Component tests for filters, row actions, upsert modal, merge modal, and confirmation dialogs.
  - Integration test for tab load, server fetch, and post-action refresh.
- Edge cases:
  - Archive already archived row (idempotent success).
  - Unarchive active row (idempotent success).
  - Re-learn archived mapping during finalization (revive instead of duplicate).
  - Manual add matching archived mapping (revive path).
  - Delete non-existent row (404) and unauthorized row (403).
  - Category deleted/inactive while mapping exists.
- Negative paths:
  - Bulk operation with mixed owned/unowned IDs must be atomic and fail as a whole.
  - Merge attempt for incompatible rows must return 422.

### Risks / Open Questions

- Ownership model must be explicit in schema if currently only implicit through category relation.
- Hard-delete vs archive default action may affect user trust; UI copy must be clear.
- Large mapping tables may need query tuning and indexes validated on production-like datasets.
- Optional future enhancement: broader semantic merge tooling (fuzzy merge) may be valuable but is intentionally out of scope due higher risk.

### Acceptance Criteria

- Given a user with learned mappings, when they open category management and switch to `Learned Mappings`, then they see a paginated list with search and status filters.
- Given an active mapping, when the user archives it, then it is hidden from default active view and no longer used by processing.
- Given an archived mapping, when the user unarchives it, then it becomes active and eligible for future matching.
- Given a mapping, when the user deletes it and confirms, then it is permanently removed and cannot be returned by list API.
- Given an archived mapping and a new identical learning event, when finalization stores learning, then the archived row is revived and reused instead of creating a duplicate row.
- Given the user edits a learning, when they save, then only learning text and category can change and uniqueness validation is enforced.
- Given the user manually adds a learning, when save succeeds, then it is active and available for subsequent matching.
- Given merge-eligible learnings, when user confirms merge, then one target row remains and its usage count equals the sum of merged rows.
- Given user A and user B, when user A queries or mutates user B's mapping ID, then access is denied.
- Given archived mappings exist, when AI processing builds category-learning context, then archived mappings are excluded.
