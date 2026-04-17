# AI Document Processing (MVP)

## Feature Summary

Introduce AI-powered document processing to convert user-submitted documents (text, PDF, images, email receipts, Google Drive uploads) into draft transaction data aligned with YAFFA’s transaction model. Processing is autonomous, asynchronous, and supports multi-item receipt categorization. Drafts are reviewed by the end-user in a modal transaction form and finalized into actual transactions, linking back to the original AI document.

**Current Implementation Status:** This feature is **MVP complete**. Core functionality is complete including document upload, AI processing, transaction finalization, category learning, Google Drive sync, and per-user AI behavior settings.

## Goals / Non-Goals

- Goals:
  - Single-transaction extraction per submission (first match only, warning returned if multiple).
  - Multi-item receipt parsing and category mapping.
  - Fuzzy asset matching (accounts, payees, investments) via similarity + AI.
  - Asynchronous processing with retry and failure email notification.
  - User-configurable AI provider/model (OpenAI, Gemini for MVP).
  - Google Drive monitoring with optional delete-after-import.
  - Per-user AI behavior settings (thresholds, OCR/image constraints, category matching strategy, AI-on/off toggle).
  - Draft storage in DB as JSON aligned with transaction config structure.
  - Link final transaction to the AI document.
  - Update the existing email processing feature to become an AI document source.

- Non-Goals:
  - Multi-transaction extraction (e.g., bank statements).
  - Setup guide or wizard for the various components of AI document processing.
  - CSV/QIF ingestion via AI pipeline.
  - Community-wide category learning.
  - On-site notifications for processing failures.
  - Token/cost tracking or limits for AI usage.
  - SPA architecture changes or global state on the frontend.
  - Iterative user-AI prompt refinement during document processing.
  - Email attachment extraction and storage in AiDocumentFile (MVP only processes email body text, attachments are ignored).
  - Remove files from local storage after given number of days (MVP does not implement any cleanup mechanism, but this can be added in the future if needed).

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
    - `delete_after_import` (boolean, default false)
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
  - `PayeeStatsApiController`
    - New API endpoint to fetch payee category stats for AI prompt optimization, and also used by PayeeApiController for default category suggestion on payee selection.
  - `AiUserSettingsApiController`
    - `GET /api/v1/ai/settings` → `api.v1.ai.settings.show` — returns fully resolved settings for the authenticated user; includes non-blocking warning payload when `warn_on_child_mode_without_children` is true and no active child categories exist
    - `PATCH /api/v1/ai/settings` → `api.v1.ai.settings.update` — partial update; returns updated resolved settings; PATCH-only, no dedicated reset-to-defaults endpoint
    - Validation via `AiUserSettingsRequest` (thresholds 0.0–1.0, amount tolerance 0.0–100.0, `category_matching_mode` in allowed enum; upload limits validated from global config only)
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
    - Error handling:
      - Authentication/permission errors: Throw specific exception (triggers config disable)
      - Other errors: Log and continue (silent fail for MVP)
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
      - Service account JSON input with show/hide toggle
      - Folder ID input with smart URL parsing
      - Delete-after-import and enabled toggles
      - Sync interval field (minutes; per-config cadence, replaces global env var)
      - Connection test and manual sync actions
      - Last sync timestamp display
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
  1. List files from folder (filter by modified date > last_sync_at)
  2. For each file:
     - Check if `google_drive_file_id` exists in `ai_documents` (skip if duplicate)
     - **Download file first** to `storage/app/ai_documents/{user_id}/{temp_id}/{filename}`
     - **Only if download succeeds**, create AiDocument record with `google_drive_file_id`
     - Fire `DocumentImported` event (existing listener handles rest)
     - If `delete_after_import = true`, delete file from Drive
  3. Update `last_sync_at` on config
  4. One file = one AiDocument (no grouping)
- **Delete After Import:**
  - User setting: `delete_after_import` (boolean, default false) in `google_drive_configs`
  - Requires service account to have delete permissions on folder
  - Test connection checks delete permission (without actually deleting)
  - Files deleted after successful import and AiDocument creation

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
    - Access specified folder (verify read permission)
    - List files (return count)
    - Check delete permission (without actually deleting - use Drive API permissions check)

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
  - Google Drive Picker dialog for folder selection
  - Multiple folder monitoring per user (database already supports it)
  - Track consecutive error count, auto-disable after threshold
  - Permanent skip list for user-deleted documents

## Email Content Cleanup

- To optimize AI token usage, implement email content cleanup before AI processing.
- Extend existing cleanup from `ProcessIncomingEmailByAi::cleanUpText()`:
  - Remove image references: `[image:.*?]`
  - Remove link references: `<http[^>]+>`
  - Remove inline styles (style attributes, `<style>` tags)
  - Remove inline SVG elements
  - Remove base64-encoded data URIs
  - Strip unnecessary HTML tags while preserving text structure
  - HTML cleanup now performed in CreateAiDocumentFromSource listener before storing email content

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

## Tech debts, future improvements

- Add an optional title field for AiDocument for user-friendly naming.
  - Verbose detail: Manually uploaded documents are currently identified by the first uploaded filename, which may not be user-friendly. Add a simple optional text field in DB + form so users can name documents explicitly.
- Add camera capture support in upload flow for mobile receipt capture.
  - Verbose detail: Add a camera-based upload option (same or similar modal as upload), likely using HTML5 `getUserMedia`, so mobile users can quickly capture receipts and submit as normal files.
- Store and display Google Drive folder name in addition to folder ID.
  - Verbose detail: Folder ID is sufficient for API calls but not user-friendly; store/display folder name too (optionally editable), fetched automatically when creating/updating config.
- Add payee/account-level custom prompt support and optional prompt-learning UX.
  - Verbose detail: Consider optional per-payee (and maybe per-account) custom prompts to improve extraction on recurring receipt formats, plus optional “save as custom prompt” suggestion after successful extractions.
- Consider side-by-side receipt vs extracted-values review UI.
  - Verbose detail: Current tab layout works, but side-by-side could improve review speed; requires careful design for multi-file documents and dense extracted data.
- [Research] Tune Tesseract parameters for OCR quality.
  - Verbose detail: Current default OCR results may be weak; investigate concrete parameter combinations that improve practical extraction accuracy.
- [Research] Define and enforce minimum Tesseract binary version for local mode.
  - Verbose detail: Add version checks and enforcement strategy so binary mode runs only on supported/minimum versions for compatibility and OCR quality.
- Add richer live status completion feedback for reprocessing (polling or realtime).
  - Verbose detail: Reprocessing currently updates status minimally; evaluate if simple polling is enough or if Echo/Reverb realtime would be justified.
- Allow creating payee from unidentified payee result in AiDocument review flow.
  - Verbose detail: Add “create payee” action when extraction returns unidentified payee, likely aligned with the modal-based payee management direction in PR 371.
- Add category-learning management UI (list, delete/archive).
  - Verbose detail: Once mappings are learned, users currently cannot manage them; add list + cleanup controls in category management.
- Handle duplicate item descriptions with contextual learning (amount/position aware).
  - Verbose detail: Current learning key is mostly description-only, which can fail when same item text appears with different categories; consider amount/position/context-sensitive learning.
- Improve receipt discount handling and optional warning heuristics.
  - Verbose detail: Test and improve handling of item-level discounts and mixed pricing cases; MVP fallback could warn when same normalized item appears with conflicting category-learning intent.
- Add payee-level toggle to disable item-level breakdown when desired.
  - Verbose detail: For some payees (e.g., restaurants), item-level split may be unnecessary; add per-payee preference to collapse to total-category behavior.
- Improve preferred/non-preferred category workflows per payee and bulk assignment UX.
  - Verbose detail: System already holds preference data but management is cumbersome; add both per-payee and mass-assignment tooling for preferred/non-preferred categories.
- Add optional category description and category type constraints for better AI guidance.
  - Verbose detail: Category descriptions could enrich prompt semantics (“Utilities = electricity/water/gas”). Also consider category type flags (any/expense-preferred/expense-only/income-preferred/income-only) to improve AI and manual validation.
- Make item-level editing foldable in finalization UI.
  - Verbose detail: Allow collapsing each item editor to show just category + amount summary for faster navigation on long receipts.
- Explore vector search for AI cost/performance optimization.
  - Verbose detail: Evaluate replacing or reducing local similarity passes with vector retrieval to lower prompt/API cost and improve context quality.
- Add learning flow for account/payee/investment overrides during finalization.
  - Verbose detail: If user overrides AI-selected account/payee/investment, provide quick path to learn/prefer that choice for future similar documents.
- [Tech debt] Revisit AI documents DataTable column width behavior after async refresh.
  - Verbose detail: Current width recalculation in the AI documents list can behave inconsistently depending on rendered content and timing. Keep current implementation for MVP; later evaluate a more deterministic layout strategy (for example fixed column sizing/colgroup, stronger redraw hooks, or table-specific CSS constraints) so the title column remains dominant while date and linked-transaction columns stay compact.
- Email notifications are added to the end of the queue when processing AI documents. It might make sense to introduce various queues for different types of jobs, and parallel workers for these.
- Add overlap hint for multi-image receipts in extraction prompt.
  - Verbose detail: In multi-image uploads, instruct AI to detect overlapping content/pages so repeated lines are not double-counted.
- Improve failed-processing UX and allow prompt editing directly from AiDocument view.
  - Verbose detail: On processing failure, UI should clearly show error context and let user adjust custom prompt before reprocessing. This could be implemented together with the verbose processing history and AI conversation logging features for better transparency and control.
  - Agent prompt: In `AiDocumentViewer`, show actionable failure reason when status is `processing_failed`, allow inline edit/save of `custom_prompt`, and support reprocess flow without full page refresh.
- Add scanned-PDF fallback to OCR when extracted PDF text is empty.
  - Verbose detail: Scanned PDFs currently fail due to empty text extraction; add fallback to OCR path (PDF/image extraction strategy) to reduce user friction.
  - Agent prompt: Enhance `TextExtractionService` so PDF extraction falls back to OCR when parsed text is empty (or below threshold), while preserving existing image OCR paths and adding tests.
- When extracting the data of a transfer, it can be among accounts with different currencies. In this case, the importance of the currency can be relevant, and we need to extract both amounts.
- The standalone transaction form should have an option callback option, which leads back to the list of AI documents, instead of the transaction list. This is relevant for the user experience, as after finalizing a transaction, the user might want to review the next AI document, instead of going back to the transaction list.
- File retention and cleanup job (`ai-documents:cleanup-old-files` command and scheduled task)
