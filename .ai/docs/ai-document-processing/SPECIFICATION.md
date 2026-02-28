# AI Document Processing (MVP)

## Feature Summary

Introduce AI-powered document processing to convert user-submitted documents (text, PDF, images, email receipts, Google Drive uploads) into draft transaction data aligned with YAFFA’s transaction model. Processing is autonomous, asynchronous, and supports multi-item receipt categorization. Drafts are reviewed by the end-user in a modal transaction form and finalized into actual transactions, linking back to the original AI document.

**Current Implementation Status:** This feature is **near MVP completion**. Core functionality is complete including document upload, AI processing, transaction finalization, category learning, and Google Drive sync; the remaining MVP gap is scanned-PDF OCR fallback.

## Goals / Non-Goals

- Goals:
  - Single-transaction extraction per submission (first match only, warning returned if multiple).
  - Multi-item receipt parsing and category mapping.
  - Fuzzy asset matching (accounts, payees, investments) via similarity + AI.
  - Asynchronous processing with retry and failure email notification.
  - User-configurable AI provider/model (OpenAI, Gemini for MVP).
  - Google Drive monitoring with optional delete-after-import.
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

## Backend Scope (Laravel)

- Models:
  - `AiDocument` (✅ implemented)
    - `id`
    - `user_id`
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
    - `processed_at` (nullable)
    - `created_at`, `updated_at`
  - `AiDocumentFile` (✅ implemented)
    - `id`
    - `ai_document_id` (FK to AiDocument)
    - `file_path` (location in local storage)
    - `file_name`
    - `file_type`
    - `created_at`
  - `CategoryLearning` (✅ implemented)
    - `id`
    - `user_id`
    - `item_description`
    - `category_id`
    - `usage_count`
    - `created_at`, `updated_at`
  - `AiProviderConfig` (✅ implemented)
    - `id`
    - `user_id` (unique, enforces one config per user)
    - `provider` (`openai`, `gemini`)
    - `model` (validated against provider’s model list)
    - `api_key` (encrypted cast)
    - `created_at`, `updated_at`
  - `GoogleDriveConfig` (✅ implemented)
    - `id`
    - `user_id` (foreign key, NO unique constraint - allows multiple configs in future, MVP enforces one per user at application level)
    - `service_account_email` (extracted from JSON, displayed in UI)
    - `service_account_json` (encrypted cast, full JSON credentials)
    - `folder_id` (Google Drive folder ID)
    - `delete_after_import` (boolean, default false)
    - `enabled` (boolean, default true)
    - `last_sync_at` (timestamp, nullable)
    - `last_error` (text, nullable - stores last error message)
    - `error_count` (integer, default 0 - future use for tracking failures)
    - `created_at`, `updated_at`
  - Update existing `ReceivedMail` model to reflect new app behavior. (✅ implemented)
    - Remove `transaction_data`, `processed`, and `handled` flags, as AIdocument processing supersedes them. (✅ implemented)
    - Remove `transaction_id` FK, as transactions are now linked via AiDocument. (✅ implemented)
    - `ReceivedMail` is no longer a standalone entity in the UI; it is linked to `AiDocument` only. (✅ implemented)
    - **Deviation:** ReceivedMail relationship to AiDocument changed from `belongsTo` to `hasOne` (one email can create one document, not the reverse)

- Migrations:
  - `ai_documents` (✅ implemented)
    - `id` - bigint unsigned, primary key
    - `user_id` - bigint unsigned, foreign key to users, cascade on delete
    - `status` - varchar(50), not null, default 'ready_for_processing'
    - `source_type` - varchar(50), not null
    - `processed_transaction_data` - json, nullable
    - `google_drive_file_id` - varchar(255), nullable, unique
    - `received_mail_id` - bigint unsigned, nullable, foreign key to received_mails, cascade on delete
    - `custom_prompt` - text, nullable
    - `processed_at` - timestamp, nullable
    - `created_at`, `updated_at` - timestamps
    - Indexes: `user_id`, `status`, `source_type`, `google_drive_file_id`
  - `ai_document_files` (✅ implemented)
    - `id` - bigint unsigned, primary key
    - `ai_document_id` - bigint unsigned, foreign key to ai_documents, cascade on delete
    - `file_path` - varchar(500), not null
    - `file_name` - varchar(255), not null
    - `file_type` - varchar(10), not null (pdf, jpg, png, txt)
    - `created_at` - timestamp
    - Indexes: `ai_document_id`
  - `category_learning` (✅ implemented)
    - `id` - bigint unsigned, primary key
    - `user_id` - bigint unsigned, foreign key to users, cascade on delete
    - `item_description` - varchar(255), not null
    - `category_id` - bigint unsigned, foreign key to categories, cascade on delete
    - `usage_count` - integer unsigned, not null, default 0
    - `created_at`, `updated_at` - timestamps
    - Indexes: `user_id`, `category_id`
    - Unique constraint: (`user_id`, `item_description`)
  - `ai_provider_configs` (✅ implemented)
    - `id` - bigint unsigned, primary key
    - `user_id` - bigint unsigned, foreign key to users, cascade on delete, unique
    - `provider` - varchar(255), not null
    - `model` - varchar(255), not null
    - `api_key` - text, not null (encrypted)
    - `vision_enabled` - boolean, not null, default false (✅ implemented)
    - `created_at`, `updated_at` - timestamps
  - `google_drive_configs` (✅ implemented)
    - `id` - bigint unsigned, primary key
    - `user_id` - bigint unsigned, foreign key to users, cascade on delete (NO unique constraint)
    - `service_account_email` - varchar(255), not null
    - `service_account_json` - text, not null (encrypted)
    - `folder_id` - varchar(191), not null
    - `delete_after_import` - boolean, not null, default false
    - `enabled` - boolean, not null, default true
    - `last_sync_at` - timestamp, nullable
    - `last_error` - text, nullable
    - `error_count` - integer unsigned, not null, default 0
    - `created_at`, `updated_at` - timestamps
    - Indexes: `user_id`, `enabled`
  - Add `ai_document_id` (bigint unsigned, nullable FK to ai_documents) to `transactions` table (✅ implemented)
  - Remove `transaction_data`, `processed`, `handled`, `transaction_id` from `received_mails` (✅ implemented)
    - During the migration, create AiDocument records for existing processed received mails to preserve data integrity. (✅ implemented)
    - With a best effort, update linked transactions to reference the new AiDocument records. (Don't try to fix broken, inconsistent data.) (✅ implemented)
    - Status mapping: (✅ implemented)
      - If `processed` = true and `transaction_id` is set → `finalized`
      - If `processed` = true and no `transaction_id` → `ready_for_review`
      - If `processed` = false → not migrated to AiDocument (legacy unprocessed mails remain unmigrated)
    - The 'down' migration does not attempt to restore removed fields or data. It only drops the AiDocument records created during the 'up' migration.

- Controllers / APIs:
  - `AiDocumentApiController` (✅ implemented)
    - `POST /api/documents` - Upload document
      - Request: multipart/form-data with `files[]`, `text_input`, `custom_prompt`
      - Validation: at least one file OR text_input required; max files and size are config-based (default: 5 files, 20MB/file); allowed types are config-driven (`AI_DOCUMENT_ALLOWED_TYPES`, restrictive default is `txt`)
      - Response: `{"id": 1, "status": "ready_for_processing", "message": "..."}`
    - `PATCH /api/documents/{id}` - Update custom prompt or status
      - Request: `{"custom_prompt": "...", "status": "..."}`
      - Response: `{"id": 1, "status": "...", "custom_prompt": "..."}`
    - `GET /api/documents` - List user's documents
      - Query params: `status`, `source_type`, `page`, `per_page` (default 15)
      - Response: paginated list with `{"data": [...], "meta": {...}, "links": {...}}`
    - `GET /api/documents/{id}` - Get document details
      - Response: full document with files, processed_transaction_data
    - `POST /api/documents/{id}/reprocess` - Trigger reprocessing
      - Response: `{"message": "Reprocessing queued", "status": "ready_for_processing"}`
    - `DELETE /api/documents/{id}` - Delete document and files
      - Response: 204 No Content
  - `AiProviderConfigApiController` (✅ implemented)
    - `GET /api/v1/ai/config` - Get user's config (only one exists)
      - Response: `{"id": 1, "provider": "openai", "model": "gpt-4o-mini", "created_at": "...", "updated_at": "..."}` (API key never returned)
    - `POST /api/v1/ai/config` - Create config (enforced: one per user)
      - Request: `{"provider": "openai|gemini", "model": "...", "api_key": "..."}`
      - Validation: provider required, model required, api_key required; rejects if config exists
      - Response: `{"id": 1, "provider": "...", "model": "...", "message": "AI provider configured successfully"}`
    - `PATCH /api/v1/ai/config/{aiProviderConfig}` - Update config
      - Request: `{"provider": "...", "model": "...", "api_key": "..."}` (api_key can be omitted or `__existing__`)
      - Response: `{"id": 1, "provider": "...", "model": "...", "updated_at": "..."}`
    - `DELETE /api/v1/ai/config/{aiProviderConfig}` - Delete config
      - Response: 204 No Content
    - `POST /api/v1/ai/config/test` - Test connection
      - Request: `{"provider": "...", "model": "...", "api_key": "..."}` (api_key can be `__existing__`)
      - Response: `{"message": "Connection successful"}` OR `{"message": "..."}` (400)
  - `GoogleDriveConfigApiController` (✅ implemented)
    - `GET /api/v1/google-drive/config` - Get user's configs (MVP returns first only)
      - Response: `{"id": 1, "service_account_email": "...", "folder_id": "...", "delete_after_import": false, "enabled": true, "last_sync_at": "...", "created_at": "...", "updated_at": "..."}` (service_account_json never returned)
    - `POST /api/v1/google-drive/config` - Create config (MVP enforces one per user at app level)
      - Request: `{"service_account_json": "...", "folder_id": "...", "delete_after_import": false, "enabled": true}`
      - Validation: service_account_json required (valid JSON with required Google keys), folder_id required
      - Response: 201 with config details (no service_account_json)
    - `PATCH /api/v1/google-drive/config/{id}` - Update config
      - Request: `{"service_account_json": "...", "folder_id": "...", "delete_after_import": false, "enabled": true}` (service_account_json can be omitted or `__existing__`)
      - Response: 200 with updated config (no service_account_json)
    - `DELETE /api/v1/google-drive/config/{id}` - Delete config
      - Response: 204 No Content
    - `POST /api/v1/google-drive/test` - Test connection
      - Request: `{"service_account_json": "...", "folder_id": "..."}` (service_account_json can be `__existing__`)
      - Response: `{"success": true, "file_count": 5, "has_delete_permission": true, "message": "Connection successful"}` OR `{"message": "..."}` (400)
    - `POST /api/v1/google-drive/sync/{id}` - Manual one-time sync trigger, ignoring last_sync_at (for testing and user-triggered syncs)
      - Dispatches `ProcessGoogleDriveConfigJob::dispatch($googleDriveConfig->id)` to queue
      - Response: **202 ACCEPTED** with `{"message": "Google Drive sync has been queued"}`
      - Test coverage: GoogleDriveConfigApiControllerTest.php (31 tests including sync endpoint)
  - `PayeeStatsApiController` (✅ implemented)
    - `GET /api/ai/payees/{id}/category-stats` - Category usage stats for a payee
      - Route model binding uses `AccountEntity` (payee) with ownership validation (`config_type = payee`, `user_id` match)
      - Response: `{"payee_id": 123, "payee_name": "Coffee Shop", "categories": [{"category_id": 7, "usage_count": 14}], "period_months": 6}`
      - Stats basis: standard transaction items linked to the payee within a 6-month window; categories are returned sorted by usage_count desc.
  - **ReceivedMail controllers/services:** (✅ implemented)
    - `ReceivedMailController` is removed from user-facing routes (no direct view/edit/delete). (✅ implemented)
    - `ReceivedMailService` is removed becoming obsolete. (✅ implemented)
    - `ReceivedMailPolicy` is removed. (✅ implemented)
    - All received mail routes removed from web.php and breadcrumbs.php (✅ implemented)
    - Navigation menu entry for "Received emails" removed (✅ implemented)
    - Vue route loader for received-mail removed from app.js (✅ implemented)

- Services / Jobs:
  - `ProcessDocumentService` (✅ Completed)
    - Orchestrates full document processing pipeline
    - Validates files (type, size)
    - ✅ Extracts text/content via injected `TextExtractionService` for all file types:
      - **PDF files:** smalot/pdfparser (native) or Tesseract/Vision API (scanned)
      - **Images (JPG/PNG):** Tesseract binary/HTTP mode → Vision API fallback
      - **Text files (TXT):** Direct read
    - ✅ Automatically handles mode selection (binary/http/cloud) based on configuration
    - ✅ Prepares AI prompts with transaction type-specific schemas (standard vs investment)
    - ✅ Fetches payee category statistics to optimize item categorization
    - ✅ Calls AI provider via Prism (text or vision-enhanced completion)
    - ✅ Validates AI response against schema
    - ✅ Updates document status (centralized status management)
  - `AiProcessingJob` (✅ Completed - queued on 'default' queue)
    - Wraps ProcessDocumentService for async execution
    - Implements retry logic (3 attempts, 30s delay)
    - ✅ Event-driven: dispatches success/failure events, no duplicate status updates
    - ✅ shouldNotRetry() for fail-fast on auth/quota errors
  - `AssetMatchingService` (✅ Completed)
    - Calculates similarity scores using `similar_text()`
    - Ignores similarity < threshold (0.5, to be finalized), to avoid polluting the AI prompt
    - Filters and ranks accounts/payees/investments
    - Returns top 10 matches if >10 exist, else all
    - Formats asset list for AI prompt (ID: Name|Alias)
    - ✅ Type compatibility: accepts iterable for Collection support
  - `DuplicateDetectionService` (✅ Completed)
    - Queries transactions within date window (3 days)
    - Calculates similarity scores for matches
    - Returns array of transaction IDs with scores > threshold
    - Checks: type, date, amount (10%), account/payee/investment
  - `CategoryLearningService` (✅ Completed)
    - Normalizes item descriptions (lowercase, trim, punctuation)
    - Saves/updates learning records on transaction save
    - Retrieves learning data for AI prompt context
    - Increments usage_count on match
  - `PayeeCategoryStatsService` (✅ implemented)
    - Shared aggregation service used by `PayeeStatsApiController`, `PayeeApiController`, and `ProcessDocumentService`
    - Computes per-payee category usage for a 6-month window
    - Provides dominant-category data for default payee suggestion and AI document shortcut logic
  - `GoogleDriveService` (✅ implemented)
    - Methods:
      - `testConnection(array $credentials, string $folderId): array` - Tests connection, returns file count and delete permission status (✅ implemented)
      - `listNewFiles(GoogleDriveConfig $config): array` - Gets files since last_sync_at (or all if null) (✅ implemented)
      - `downloadFile(string $fileId, array $credentials, string $destination): void` - Downloads file (✅ implemented)
      - `deleteFile(string $fileId, array $credentials): void` - Deletes file from Drive (✅ implemented)
    - Uses `google/apiclient` package (already in composer.json)
    - Error handling:
      - Authentication/permission errors: Throw specific exception (triggers config disable)
      - Other errors: Log and continue (silent fail for MVP)
  - `GoogleDriveMonitorJob` (✅ implemented - Orchestrator)
    - Simplified orchestrator (28 lines) that queries enabled configs and dispatches per-config jobs (✅ implemented)
    - Scheduled frequency via .env `AI_GOOGLE_DRIVE_SYNC_INTERVAL_MINUTES` (default 15)
    - Runs for all users with `enabled = true` in `google_drive_configs`
    - For each enabled config:
      - Dispatches `ProcessGoogleDriveConfigJob($config->id)` to queue (✅ implemented)
    - Traits: Dispatchable, InteractsWithQueue, Queueable, SerializesModels
    - Properties: $tries = 1, $timeout = 60
  - `ProcessGoogleDriveConfigJob` (✅ implemented - Worker)
    - Processes file import for a single config (138 lines) (✅ implemented)
    - Constructor: `public function __construct(public int $configId)` (✅ implemented)
    - For each enabled config:
      - Call `GoogleDriveService::listNewFiles()`
      - For each new file (one file = one AiDocument):
        - **Step 1:** Download file to `storage/app/ai_documents/{user_id}/{document_id}/{filename}` (create folder structure)
        - **Step 2:** Only if file copy successful, create AiDocument record with `google_drive_file_id`
        - **Step 3:** Fire `DocumentImported` event (listener creates final structure)
        - **Step 4:** If `delete_after_import = true`, delete from Drive
        - **Step 5:** Update `last_sync_at` timestamp on config
      - On authentication/permission error:
        - Set `enabled = false` on config
        - Store error in `last_error` field
        - Send `GoogleDriveConfigDisabled` email notification to user (✅ implemented)
      - On other errors: Log and continue (silent fail)
    - Duplicate prevention: Check `google_drive_file_id` exists in `ai_documents` before processing (✅ implemented)
    - Deleted AiDocument handling: If AiDocument with same `google_drive_file_id` was deleted, re-import the file (✅ implemented)
    - Traits: Dispatchable, InteractsWithQueue, Queueable, SerializesModels
    - Properties: $tries = 3, $timeout = 300
    - Test coverage: ProcessGoogleDriveConfigJobTest.php (14 tests, 35+ assertions) (✅ implemented)
  - `EmailProcessingService` (extend existing, ✅ implemented)
    - Parses incoming email (text/HTML cleanup)
    - Extracts attachments
    - Creates AiDocument with source_type=received_email
    - Triggers AiProcessingJob
    - **Deviation:** Email processing refactored to use event-driven architecture instead of a dedicated service. MailHandler now fires EmailReceived event, CreateAiDocumentFromSource listener creates AiDocument. (✅ implemented)

- Policies / Auth: (✅ Fully Implemented)
  - `AiDocumentPolicy` (✅ implemented - view, create, delete, reprocess)
  - `AiProviderConfigPolicy` (✅ implemented - view, create, update, delete)
  - `GoogleDriveConfigPolicy` (✅ implemented - view, create, update, delete, sync)
    - Simple ownership check: `$user->id === $config->user_id`
    - MVP: Only one config per user enforced at application level (not database)

- Events / Notifications:
  - **Ingestion Events:** (✅ implemented)
    - `EmailReceived` event fired when an email is captured (✅ implemented - replaces IncomingEmailReceived)
    - `DocumentImported` event fired when a file is imported from Google Drive (✅ implemented)
    - Listener `CreateAiDocumentFromSource` creates the AiDocument and related files (✅ implemented)
    - Rationale: keeps ingestion concerns separate from AI processing pipeline
    - **Implementation details:**
      - MailHandler fires EmailReceived event with ReceivedMail instance
      - CreateAiDocumentFromSource is auto-discovered by Laravel (not manually subscribed)
      - Listener handles both EmailReceived and DocumentImported events
      - Creates AiDocument with status 'ready_for_processing' and stores email content as text file
      - Legacy ProcessIncomingEmail listener and IncomingEmailReceived event removed
  - **Processing Events:** (✅ implemented)
    - `AiDocumentProcessedEvent` - Fired on successful processing (status → ready_for_review)
    - `AiDocumentProcessingFailedEvent` - Fired on processing failure (status → processing_failed)
  - **Email Notifications:** (✅ implemented)
    - `SendAiDocumentProcessedNotification` - Queued listener for success emails
    - `SendAiDocumentProcessingFailedNotification` - Queued listener for failure emails with error details
  - **Notification Details:**
    - **Processing success (ready for review)** ✅
      - Mailable: `App\Mail\AiDocumentProcessed`
      - Subject: "Document processed - Review your transaction"
      - Content: Document ID, source type, extracted amount/payee, link to review page
      - View: `resources/views/emails/ai-document-processed.blade.php`
    - **Processing failure (AI error, after depleting retries)** ✅
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
    - **Google Drive config disabled (auth/permission error)** ✅
      - Mailable: `App\Mail\GoogleDriveConfigDisabled`
      - Subject: "Google Drive monitoring disabled"
      - Content: Config ID, error message, instructions to fix (re-share folder, update service account), link to settings
      - View: `resources/views/emails/google-drive-config-disabled.blade.php`

## Frontend Scope (Vue + Bootstrap)

- Pages / Routes:
  - `Documents Index` - `/ai-documents` (✅ implemented)
    - Blade view: `resources/views/ai-documents/index.blade.php`
    - Layout: extends `layouts.app`
    - Vue component: `AiDocumentList.vue`
    - Features: DataTable with filters (status, source_type), pagination, search
  - `Document Review` - `/ai-documents/{id}` (✅ implemented)
    - Blade view: `resources/views/ai-documents/show.blade.php`
    - Layout: extends `layouts.app`
    - Vue components: `AiDocumentViewer.vue`, `TransactionPreview.vue` (existing)
    - Features: file preview, draft transaction display, finalize button, reprocess button
  - `AI Provider Settings` - `/user/settings` (✅ integrated into existing settings page)
    - Blade view: `resources/views/user/settings.blade.php`
    - Layout: extends `layouts.app`
    - Vue component: `AiProviderSettings.vue`
      - Features: provider/model selection, API key input, test connection button, add/update/delete
  - `Google Drive Settings` - `/user/settings` (✅ integrated into existing settings page)
    - Vue component: `GoogleDriveSettings.vue` (new)
      - Loaded in `MyProfile.vue` component (same parent as AiProviderSettings)
      - Features:
        - Service account email display (extracted from JSON)
        - Service account JSON input (password type with show/hide toggle button - Vue best practice)
        - Folder ID input with smart URL parsing (extracts ID from full Drive URL)
        - Tooltip helper text (example: `https://drive.google.com/drive/folders/{FOLDER_ID}` - copy the part after `/folders/`)
        - Delete after import checkbox
        - Enabled/disabled toggle
        - Test connection button (shows file count and delete permission status)
        - Manual sync trigger button
        - Last sync timestamp display
        - Add/update/delete configuration
        - MVP: Only one config per user enforced at UI level (hide "Add" button if config exists)
    - Blade view: `resources/views/ai-documents/create.blade.php`
    - Layout: extends `layouts.app`
    - Vue component: `DocumentUploadForm.vue` (✅ implemented)
      - Features: drag-drop file upload, text input, custom prompt textarea
  - **ReceivedMail UI:** (✅ implemented)
    - No user-facing pages or CRUD actions for `ReceivedMail`
    - Any existing ReceivedMail views/routes should be removed or hidden

- Components:
  - `AiDocumentViewer.vue` (✅ implemented)
    - Enhanced with "Extracted details" tab for comprehensive transaction data review
    - Features: file preview, draft transaction display, finalize button, reprocess button
    - Line items table with description, amount, category badges
    - "More details" button for programmatic tab switching
    - Integrated duplicate detection warning component
  - `AiDocumentUploadForm.vue` (✅ implemented)
    - Reusable component for uploading documents (used on index page and can be triggered from other pages)
    - Features: drag-and-drop file upload, text input, custom prompt textarea, submit button
  - `GoogleDriveSettings.vue` (✅ implemented)
    - Service account JSON field: password type with show/hide toggle (use Vue best practice pattern)
    - Folder ID field: Smart parsing to extract ID from full Drive URL
    - Test connection: Display file count and delete permission check results
    - Manual sync: Trigger one-time import
  - `AiDocumentExtractedDetails.vue` (✅ implemented)
    - Comprehensive display of extracted transaction data including line items with category mappings
  - `AiDocumentDuplicates.vue` (✅ implemented)
    - Shows potential duplicate transactions with similarity scores
    - Click-through to view duplicate transaction details
  - ✅ Transaction form integration via event-driven architecture
    - Finalize button dispatches 'initiateCreateFromDraft' CustomEvent
    - Existing transaction modal receives and processes prepopulated draft data
    - Supports both standard and investment transaction types

- State management:
  - No global store. Page-level component state only.

- API interactions:
  - All validations enforced by backend; UI shows backend validation messages.
  - **Error Response Format (all endpoints):**
    - Validation errors (422): `{"message": "...", "errors": {"field": ["error1", "error2"]}}`
    - Authorization errors (403): `{"message": "Unauthorized"}`
    - Not found (404): `{"message": "Document not found"}`
    - Server errors (500): `{"message": "An error occurred", "error": "..."}`
  - Notifications use existing `toast.js` helpers:
    - Success: `toast.success(message)`
    - Error: `toast.error(message)` with validation details
    - Info: `toast.info(message)` for warnings

## Data & API Design

- AiDocument (draft JSON aligned with transaction model)

Standard transaction example:

```
{
  "raw": {
    "date": "2023-09-05",
    "payee": "Google Commerce Limited",
    "amount": "4.00",
    "account": "Mastercard-1111",
    "currency": "USD",
    "transaction_type": "withdrawal"
  },
  "date": "2023-09-05",
  "config_type": "standard",
  "transaction_type": "withdrawal",
  "config": {
    "amount_to": 4,
    "amount_from": 4,
    "account_to_id": 583,
    "account_from_id": 9
  },
  "transaction_items": [
    { "amount": 2.50, "recommended_category_id": 12 },
    { "amount": 1.50, "recommended_category_id": 7 }
  ]
}
```

Investment transaction example:

```
{
  "raw": { ... },
  "date": "2023-09-05",
  "config_type": "investment",
  "transaction_type": "sell",
  "config": {
    "account_id": 9,
    "investment_id": 144,
    "price": 125.34,
    "quantity": 2.0,
    "commission": 1.25,
    "tax": 0.75,
    "dividend": 0.0
  }
}
```

The following strict schema is expected for the JSON response. All fields must be returned.
If not determined or used, it must be set to NULL, but never omitted.

```
{
  "raw": {
    "payee": "string|null: the string identified as payee",
    "account": "string|null: the string identified as the primary account related to the transaction",
    "target_account": "string|null: the string identified as the secondary account (only for transfers)",
    "investment": "string|null: the string identified as the investment (only for investment transactions)",
    "currency": "string|null: the currency code identified for the transaction; ISO 4217 format, even if not used in YAFFA, or not in this format in the document",
    "transaction_type": "string|null: one of 'deposit', 'withdrawal', 'transfer'; 'buy', 'sell', 'dividend', 'interest', 'add_shares', 'remove_shares'; or null if not determined",
  },
  "date": "YYYY-MM-DD|null: the identified date of the transaction, when it occurred",
  "config_type": "standard|investment: based on the transaction type",
  "transaction_type": "string|null: one of 'deposit', 'withdrawal', 'transfer'; 'buy', 'sell', 'dividend', 'interest', 'add_shares', 'remove_shares'; or null if not determined",
  "config": "object, different structure based on config_type",
    // For standard transactions:
    {
      "amount_from": "number|null: the total amount of the transaction, in the identified currency",
      "amount_to": "number|null: the total amount of the transaction, in the identified currency; except for transfers, where it may differ due to different currencies",
      "account_from_id": "number|null: the ID of the primary account in YAFFA; account for withdrawals, payee for deposits, source account for transfers, or null if not determined",
      "account_to_id": "number|null: the ID of the secondary account or payee in YAFFA; payee for withdrawals, account for deposits, target account for transfers, or null if not determined"
    }
    // For investment transactions:
    {
      "account_id": "number|null: the ID of the account in YAFFA, or null if not determined",
      "investment_id": "number|null: the ID of the investment in YAFFA, or null if not determined",
      "price": "number|null: the price per unit identified",
      "quantity": "number|null: the quantity of units involved",
      "commission": "number|null: any commission fees identified",
      "tax": "number|null: any tax amounts identified",
      "dividend": "number|null: dividend amount (for dividend transactions)"
    }
  "transaction_items": "array of objects|null: line items for multi-item receipts; empty array if none",
    [
      {
        "amount": "number|null: the amount for this item",
        "recommended_category_id": "number|null: the ID of the category in YAFFA, or null if not determined"
      }
    ]
}
```

## Processing Flow

1. User submits document (web, email, or Google Drive). ✅
   - When a submission contains multiple files, it is still considered to be one AiDocument.
   - E.g. a longer receipt could be attached using multiple photos
2. `AiDocument` record created with status `ready_for_processing` (initial state). ✅
3. `AiProcessingJob` runs: ✅
   - Uses `TextExtractionService` to extract text from all file types
   - Tesseract (binary or HTTP) or Vision API invoked automatically per configuration
   - Builds AI prompt with transaction type-specific schemas (standard vs investment)
   - Includes normalized assets and category learning data
   - Calls AI provider (OpenAI/Gemini) via Prism in multiple steps
     - Extract generic transaction details (date, amount, payee, accounts, investment, currency)
     - Identify account(s)/payee/investment matches from user's database using local exact match or AI-assisted matching
     - For withdrawals and deposits, if items are detected, try to identify line item level category mappings. Either use local exact match or AI-assisted matching based on item description.
   - Validates output schema
   - Stores JSON draft in `processed_transaction_data`
4. Status set to `ready_for_review` and email notification sent. ✅
5. User opens document review (`/ai-documents/{id}`) and can view extracted details. ✅
   - "Extracted details" tab displays all transaction data comprehensively
   - Finalize button triggers transaction form
6. Transaction Form Integration: ✅ (Completed)
   - Frontend calls existing transaction form component (reuse from transactions feature)
   - The form receives prepopulated data from `processed_transaction_data` JSON
   - If there are less than 5 line items, the form should be displayed in the modal container; otherwise, redirect to full page transaction form
   - Field mapping should be automatic based on existing transaction form structure and JSON structure
   - User can edit any field before saving
   - The database is checked for duplicates, and warning is displayed above form (if present)
7. Details for the item-level category mapping: ✅
   - Each item in the `items` array is rendered as a separate line item in the transaction form
   - The `amount` field maps to the line item amount
   - If `recommended_category_id` is provided, and the `match_type` is `exact`, or if the `confidence_score` is above a certain threshold, preselect that category in the dropdown
   - User can change category selection freely, and buttons are added to allow or deny AI suggestions
8. User saves transaction form: ✅
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
  - Use PHP's `similar_text()` function to calculate similarity against `name` + `import_alias` (existing pattern from email processing).
  - Send all matches if < 10; otherwise send top 10 sorted by similarity score descending.
  - AI returns best single match only, or N/A if no match.
  - For investments, match against `name`, `code`, and `isin` fields.

- Category learning (✅ AI-Enhanced - Implemented):
  - **Exact Local Matching:**
    - Flat table with normalized item descriptions (`item_description` field in `category_learning` table).
    - Normalization: lowercase, trim, remove punctuation.
    - If exact normalized match found with active category, use immediately (no AI call).
    - Match type: `'exact'`, confidence score: `1.0`.
  - **AI-Assisted Matching (Batch Processing):**
    - When no exact match exists, batch all unmatched items in single AI call for efficiency.
    - Gather similar learning records per item using `similar_text()` with 0.5 threshold (top 10).
    - Provide AI with: item descriptions, similar learning patterns, and full active category list.
    - AI returns: `recommended_category_id`, `confidence_score` (0.0-1.0) per item.
    - Validate category exists and is active before accepting AI suggestion.
    - Match type: `'ai'`, confidence score from AI response.
  - **Data Structure:**
    - Items stored with additional metadata: `{amount, description, recommended_category_id, match_type, confidence_score}`.
    - `match_type`: `'exact'` | `'ai'` | `null` (no match).
    - `confidence_score`: `1.0` (exact), `0.0-1.0` (AI), `null` (no match).
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
- This is not part of the AI processing, but performed on transaction finalization.

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
  - **Only for Vision AI:** Resize to max 2048x2048 pixels before sending to AI provider
  - **For Tesseract OCR:** Use original resolution (better OCR accuracy)
  - Resized images not persisted (memory only, temporary for Vision API call)
  - Original files always retained
- **Retention: (non-MVP)**
  - Environment variable: `AI_DOCUMENT_FILE_RETENTION_DAYS=90` (default)
  - Empty or `0` disables auto-deletion
  - Cleanup job: `php artisan ai-documents:cleanup-old-files`
  - Scheduled daily via Laravel scheduler
  - Only deletes files, not database records
- **File upload limits:**
  - Max files per submission: 5
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
  - Frequency: Configurable via `.env` variable `AI_GOOGLE_DRIVE_SYNC_INTERVAL_MINUTES` (default: 15)
  - NOT a user-configurable setting (global system setting)
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
  - Endpoint: `POST /api/google-drive/test`
  - Tests performed:
    - Authenticate with service account JSON
    - Access specified folder (verify read permission)
    - List files (return count)
    - Check delete permission (without actually deleting - use Drive API permissions check)
  - Response on success: `{"success": true, "file_count": 5, "has_delete_permission": true}`
  - Response on failure: `{"success": false, "message": "Folder not accessible. Ensure service account email is shared the folder."}`

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
  - **New for MVP:** Remove inline styles (style attributes, `<style>` tags) (✅ implemented in CreateAiDocumentFromSource::cleanHtmlContent)
  - **New for MVP:** Remove inline SVG elements (✅ implemented)
  - **New for MVP:** Remove base64-encoded data URIs (✅ implemented)
  - **New for MVP:** Strip unnecessary HTML tags while preserving text structure (✅ implemented)
  - HTML cleanup now performed in CreateAiDocumentFromSource listener before storing email content

## Testing & Development Tools

- **Email Simulation Command:** `php artisan app:simulate-incoming-email` (✅ implemented)
  - Purpose: Test email reception and AI document creation locally without actual SMTP/mailbox setup
  - Options:
    - `--from=EMAIL` - Sender email address (creates user if --create-user enabled)
    - `--subject=TEXT` - Email subject line
    - `--text=TEXT` - Plain text email body
    - `--html=HTML` - HTML email body
    - `--sync` - Process synchronously (skip queue, for debugging)
    - `--create-user` - Auto-create user if sender doesn't exist
    - `--use-demo` - Shortcut: sets --from=demo@yaffa.cc and enables --create-user (✅ implemented)
  - Implementation:
    - Builds proper MIME multipart/alternative messages compatible with beyondcode/laravel-mailbox
    - Uses InboundEmail::fromMessage() to create mailbox-compatible objects
    - Invokes MailHandler directly
    - Supports text-only, HTML-only, or multipart messages
    - Event::fake() used when --sync to prevent duplicate processing
  - Test coverage: SimulateIncomingEmailCommandTest.php (2 tests, 11 assertions)
  - Usage examples:

    ```bash
    # Quick test with demo user
    sail artisan app:simulate-incoming-email --use-demo

    # Custom email with text and HTML
    sail artisan app:simulate-incoming-email \
      --from=user@example.com \
      --subject="Receipt from Coffee Shop" \
      --text="Coffee: $4.50" \
      --html="<p>Coffee: <strong>$4.50</strong></p>" \
      --create-user

    # Synchronous processing for debugging
    sail artisan app:simulate-incoming-email --use-demo --sync

    ```

## Testing Strategy

- **Required Factories:**
  - `AiDocumentFactory` - All statuses, source types
  - `AiDocumentFileFactory` - All file types
  - `CategoryLearningFactory` - Various item descriptions
  - `AiProviderConfigFactory` (✅ implemented) - Providers/models derived from config

- **Backend Unit Tests:**
  - `AssetMatchingServiceTest`
    - Similarity calculation accuracy
    - Top 10 filtering when >10 matches
    - Return all when <10 matches
    - Alias matching priority
    - Empty asset list handling
  - `DuplicateDetectionServiceTest`
    - Date within 3 days boundary
    - Amount within 10% boundary
    - Multiple match return
    - Similarity score calculation
    - No match scenario
  - `CategoryLearningServiceTest`
    - Normalization (lowercase, trim, punctuation)
    - Save new learning record
    - Update existing record (usage_count increment)
    - Unique constraint enforcement
  - `ProcessDocumentServiceTest`
    - File validation (type, size)
    - Text extraction from PDF
    - Image resizing logic
    - AI response schema validation
    - Invalid AI response handling

- **Backend Feature Tests:**
  - `AiDocumentUploadTest`
    - Single file upload (PDF, JPG, PNG, TXT)
    - Multiple file upload
    - Text-only submission
    - File + text submission
    - File size validation (too large)
    - File type validation (invalid)
    - Max file count validation
    - Authenticated users only
  - `AiDocumentProcessingTest`
    - Job queuing
    - Successful processing (mock AI)
    - AI response parsing
    - Status transitions (ready → processing → ready_for_review)
    - Failure after 3 retries
    - Email notification on success
    - Email notification on failure
  - `AiDocumentFinalizationTest`
    - Transaction creation from draft
    - Link document to transaction (ai_document_id)
    - Status change to finalized
    - Authorization (own documents only)
  - `AiProviderConfigTest` (✅ implemented)
    - Create config (one per user enforcement)
    - Update config (supports `__existing__` placeholder)
    - Delete config
    - Test connection
    - API key encryption
  - `GoogleDriveConfigApiControllerTest` (new)
    - Authorization tests (own configs only)
    - Show endpoint (no service_account_json in response)
    - Store endpoint (one per user enforcement at app level)
    - Update endpoint (supports `__existing__` placeholder)
    - Destroy endpoint
    - Test connection endpoint (file count, delete permission check)
    - Manual sync trigger
  - `GoogleDriveConfigRequestTest` (new)
    - Validation: service_account_json required (create), optional (update)
    - Validation: valid JSON structure
    - Validation: required Google service account keys present
    - Validation: folder_id required
    - `__existing__` placeholder handling
  - `GoogleDriveConfigTest` (Unit)
    - service_account_json encryption at rest
    - service_account_json decryption on access
    - service_account_email extraction from JSON
  - `GoogleDriveServiceTest` (new)
    - Mock Google Drive API client
    - Test connection (file listing)
    - Delete permission check (without deleting)
    - File download
    - File deletion
    - Authentication error handling
  - `GoogleDriveMonitorJobTest` (✅ implemented)
    - Only processes enabled configs
    - Dispatches ProcessGoogleDriveConfigJob for each enabled config
    - Skips disabled feature flag
    - Test coverage: 3 tests, 9 assertions
  - `ProcessGoogleDriveConfigJobTest` (✅ implemented)
    - File download → database record creation order
    - Duplicate prevention (google_drive_file_id check)
    - Deleted AiDocument re-import
    - Delete after import logic
    - Individual file download failure handling (continues processing)
    - Authentication error disables config
    - Error notification email sent
    - last_sync_at update
    - Config status filtering (enabled/disabled)
    - Test coverage: 14 tests, 35+ assertions
  - `GoogleDriveConfigApiControllerTest` (✅ implemented)
    - Authorization tests (own configs only)
    - CRUD endpoint tests (show, store, update, destroy)
    - Test connection with file count and delete permission
    - Manual sync trigger (202 ACCEPTED response)
    - Test coverage: 31 tests, 79+ assertions
  - `DuplicateDetectionIntegrationTest`
    - Multiple duplicate warnings
    - Soft warning display
    - User can proceed despite warning
  - `PayeeStatsApiControllerTest` (✅ implemented)
    - Returns aggregated categories for a payee within last 6 months
    - Returns not found for non-owned payee
  - `PayeeApiControllerTest` (✅ updated)
    - Default payee suggestion path uses shared stats data and dominant category selection
  - `ProcessDocumentServiceTest` (✅ updated)
    - Single-category payee shortcut assigns full amount to one recommended category item

- **Frontend Component Tests:**
  - `DocumentUploadForm.spec.js`
    - File input rendering
    - Drag-drop zone
    - Text input
    - Validation: file OR text required
    - File size warning display
    - Form reset
  - `AiDocumentViewer.spec.js`
    - File preview rendering
    - Draft transaction display (using TransactionPreview)
    - Duplicate warning display (multiple)
    - Finalize button behavior
    - Reprocess button behavior
  - `AiProviderConfigForm.spec.js`
    - Provider selector
    - Model selector (dynamic based on provider)
    - API key masking
    - Test connection button
    - Success/error toast display
  - `GoogleDriveSettingsTest.php` (Browser/Dusk - ✅ implemented)
    - Add configuration workflow
    - Service account JSON show/hide toggle
    - Folder ID extraction from full URL
    - Update configuration (preserve existing credentials)
    - Test connection (file count display)
    - Manual sync trigger
    - Delete configuration
    - One config per user enforcement (UI level)

## OCR & Vision Processing Strategy (✅ Implemented with one known MVP gap)

### Overview

YAFFA supports three methods for extracting text from documents, with automatic fallback based on file type and configuration:

1. **PDF Text Extraction** (✅ always available)
   - Library: `smalot/pdfparser`
   - Use case: Text-based PDFs (invoices, statements, emails saved as PDF)
   - No OCR required, fastest method
   - Automatically used for all PDF files

2. **Tesseract OCR** (✅ optional, self-hosted)
   - Library: Command-line `tesseract` via Symfony Process
   - Use case: Scanned documents, photo receipts, images
   - Requires: System installation OR Docker container
   - Configuration: `TESSERACT_ENABLED=true` + `TESSERACT_MODE=binary|http`
   - Cost: Free (compute only)
   - Accuracy: Good for high-resolution images
   - ✅ Binary mode: Local execution via PHP Process
   - ✅ HTTP mode: Docker sidecar via `franky1/tesseract-ocr` image (optional service in docker-compose.yml)

3. **Vision AI** (✅ optional, cloud-based)
   - Library: Prism integration with OpenAI/Gemini vision models
   - Use case: Complex receipts, handwritten notes, low-quality scans
   - Requires: User's AiProviderConfig with vision-capable model selected
   - Cost: API token usage (~$0.01-0.05 per image depending on model)
   - Accuracy: Excellent, especially for complex layouts

### Processing Logic (✅ Implemented in TextExtractionService)

**For PDF files:**

- ✅ Always attempt text extraction with smalot/pdfparser first
- ✅ If successful (text extracted), proceed with AI text completion
- ⏳ If extraction is empty (scanned PDF), no OCR fallback is currently applied; processing fails with "No text could be extracted" (tracked as pending work)

**For image files (JPG, PNG):**

- ✅ Check if Tesseract is enabled AND available:
  - ✅ If yes: Run Tesseract OCR on original resolution image (binary or HTTP mode)
  - ✅ If no: Check if Vision AI is enabled AND selected model supports vision
    - ✅ If yes: Resize image to 2048px max, send to Vision AI via Prism
    - ✅ If no: **Fail processing** with `OcrUnavailableException` error, notify user

**For text files (TXT):**

- ✅ Direct `file_get_contents()`, no OCR needed

### Configuration (✅ Implemented)

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

1. ✅ User configures AI provider (OpenAI/Gemini) in settings
2. ✅ User selects model (gpt-4o, gemini-1.5-pro for vision support)
3. ✅ User chooses deployment mode: binary (local), http (Docker), or cloud-only
4. ✅ If images uploaded without OCR, clear error message with remediation steps

### Docker Deployment (✅ Implemented)

**Optional Tesseract Service (docker-compose.yml):**

✅ Added to `docker/docker-compose.yml` with smart profile-based activation

## AI Provider Configuration

### Supported Providers & Models (MVP)

- For MVP, it's OK to work with a static list of supported models and providers.
- Future improvements can introduce further providers and dynamic model fetching.

**OpenAI:**

- `gpt-4o` - GPT-4 Omni (**vision support**, structured outputs, best for complex receipts)
- `gpt-4o-mini` - GPT-4 Omni Mini (**vision support**, cheaper, faster, recommended for MVP testing)
- `gpt-5-mini` - GPT-5 Mini (text-only, no vision)

**Gemini:**

- `gemini-1.5-pro` - Gemini 1.5 Pro (**vision support**, long context, best for complex receipts)
- `gemini-1.5-flash` - Gemini 1.5 Flash (**vision support**, faster, cheaper, recommended for MVP testing)

**Model Capabilities (config/ai-documents.php):**

```php
'providers' => [
    'openai' => [
        'name' => 'OpenAI',
        'models' => [
            'gpt-4o' => ['vision' => true],
            'gpt-4o-mini' => ['vision' => true],
            'gpt-5-mini' => ['vision' => false],
        ],
    ],
    'gemini' => [
        'name' => 'Google Gemini',
        'models' => [
            'gemini-1.5-pro' => ['vision' => true],
            'gemini-1.5-flash' => ['vision' => true],
        ],
    ],
],
```

### Default Configuration

- OpenAI default: `gpt-4o-mini`
- Gemini default: `gemini-1.5-flash`

## Prompt Engineering (based on existing patterns)

The system will use multi-step prompting similar to existing email processing:

1. **Main extraction prompt:** Extract transaction type, account, payee, date, amount, currency, and line items from document
2. **Account matching prompt:** Match extracted account name against user's account list (ID: Name|Alias format)
3. **Payee matching prompt:** Match extracted payee against user's payee list (ID: Name format)
4. **Investment matching prompt:** (if investment type) Match against user's investments (ID: Name|Code|ISIN format)
5. **Category matching prompt (✅ AI-Enhanced - Implemented):** Batch match all line item descriptions against:
   - User's category learning data (past item descriptions with usage counts)
   - User's active categories (full list with parent/child hierarchy)
   - Returns: `[{item_index, recommended_category_id, confidence_score}]` for all items in single AI call

**IMPORTANT:** line item matching is only needed for withdrawals and deposits, not for transfers or investment transactions.
After payee matching, backend processing resolves category usage from shared payee-stats logic (the same logic powering `GET /api/ai/payees/{id}/category-stats`). If only one category is present in the 6-month stats, category matching is skipped and that category is assigned to the entire amount as one line item.

All prompts require JSON responses with strict schemas to ensure validation.

## Edge Cases & Negative Test Scenarios

- **Upload Validation:**
  - No files and no text → reject with 422
  - File size 0 bytes → reject
  - File size > configured max (`AI_DOCUMENT_MAX_FILE_SIZE_MB`, default 20MB) → reject
  - Files uploaded beyond configured limit (`AI_DOCUMENT_MAX_FILES_PER_SUBMISSION`, default 5) → reject
  - Unsupported file type (.exe, .zip) → reject
  - Corrupted PDF → graceful failure with error message

- **OCR & Text Extraction:**
  - **Image uploaded, Tesseract disabled, Vision AI disabled** → mark processing_failed, email user with `ocr_unavailable` error
  - **Image uploaded, Tesseract enabled but binary not found** → mark processing_failed, email user (check TESSERACT_PATH)
  - **Image uploaded, Vision AI enabled but model doesn't support vision** → mark processing_failed, email user (suggest vision-capable model)
  - **Scanned PDF (no extractable text)** → mark processing_failed (current behavior; PDF OCR fallback is pending)
  - **Text PDF with smalot/pdfparser** → extract successfully, no OCR needed
  - **Mixed document (text + images), partial OCR failure** → extract what's possible, continue processing
  - **Tesseract returns empty string** → mark processing_failed (unreadable image)
  - **Vision API image too large (>20MB after resize)** → reject during validation
  - **Multi-page scanned PDF** → attempt text extraction for all pages; if no extractable text, processing fails (OCR fallback for scanned PDFs is pending)

- **AI Processing:**
  - AI response missing required fields → mark processing_failed
  - AI response with invalid JSON → mark processing_failed, retry
  - AI returns "N/A" for all assets → store as null, allow user to select manually
  - AI timeout (>60s) → retry
  - Rate limit hit → retry after 30s delay (fixed)
  - Invalid API key → fail fast, email user, do not retry
  - Quota exhausted → fail fast, email user, do not retry

- **Asset Matching:**
  - Zero accounts in user's list → AI receives empty list, returns N/A
  - Payee name with special characters → normalize before matching
  - Investment with no code/ISIN → match on name only

- **Duplicate Detection:**
  - Exact match on all fields → similarity score 1.0
  - Amount differs by 9.9% → included in matches
  - Amount differs by 10.1% → excluded from matches
  - Date differs by 3 days → included
  - Date differs by 4 days → excluded
  - Same amount/date but different account → excluded

- **Category Learning:**
  - Item description with emoji → normalize (strip emoji)
  - Duplicate item with same category → update usage_count
  - Duplicate item with different category → update category_id and usage_count
  - Very long item description (>255 chars) → truncate

- **Google Drive:**
  - Invalid service account JSON → fail fast, show validation error
  - Service account not shared folder → test connection fails with clear message
  - Service account lacks delete permission → test shows `has_delete_permission: false`, warn user if delete_after_import enabled
  - Folder deleted → disable config, notify user via email
  - File deleted before download → skip, log warning, continue processing
  - Network timeout → log error, continue (silent fail for MVP)
  - Duplicate file (same google_drive_file_id) → skip silently
  - User deletes AiDocument, file still in Drive → re-import on next sync
  - File download fails → log error, do NOT create database record, continue processing
  - Multiple configs per user → MVP prevents in UI and controller, database allows for future

- **Transaction Finalization:**
  - User clicks Finalize before processing complete → disable button, show error
  - User tries to finalize someone else's document → 403 error
  - Transaction creation fails (validation) → show error, keep document in ready_for_review
  - User navigates away without saving → document remains in ready_for_review

- **Concurrency:**
  - User uploads 5 documents simultaneously → all queued, processed sequentially
  - User clicks Reprocess while already processing → reject with error message
  - Two users with same Google Drive folder → each imports separately (no conflict)

---

### Completed Components

**Email Processing Migration (✅ Completed):**

- ReceivedMail model updated: removed transaction_data, processed, handled, transaction_id columns
- Database migrations created (2026_01_31_180343)
- Existing ReceivedMails migrated to AiDocuments with proper status mapping
- ReceivedMail now has `hasOne` relationship to AiDocument (not `belongsTo`)
- MailHandler refactored to fire EmailReceived event
- CreateAiDocumentFromSource listener handles EmailReceived and DocumentImported events
- Email content cleaned via cleanHtmlContent() method (removes styles, scripts, SVGs, base64 images)
- Email content stored as text file in ai_documents/{user_id}/{document_id}/
- ProcessIncomingEmailByAi job kept as compatibility layer (short-circuits to EmailReceived event)

**UI Cleanup (✅ Completed):**

- ReceivedMailController removed from routes
- ReceivedMailService deleted
- ReceivedMailPolicy deleted
- All ReceivedMail views deleted (index, show, etc.)
- Navigation menu entry removed
- Vue route loader removed from app.js
- Breadcrumbs removed
- Previous emails and related templates removed (TransactionCreatedFromEmail, TransactionErrorFromEmail)

**Event Architecture (✅ Completed):**

- EmailReceived event created (replaces IncomingEmailReceived)
- IncomingEmailReceived event deleted
- ProcessIncomingEmail listener deleted
- CreateAiDocumentFromSource auto-discovered by Laravel (no manual Event::subscribe needed)
- Fixed duplicate AiDocument creation bug by removing manual subscription in AppServiceProvider

**Testing Infrastructure (✅ Completed):**

- IncomingEmailTest updated for new flow (7 tests passing)
- SimulateIncomingEmailCommandTest created (2 tests, 11 assertions)
- ProcessIncomingEmailByAiTest renamed to test HTML cleanup in CreateAiDocumentFromSource
- app:simulate-incoming-email command created with full MIME message support
- --use-demo flag added for simplified testing

**AI Provider Configuration (✅ Completed):**

- AiProviderConfig model implemented
- AiProviderConfigApiController with full CRUD + test endpoint
- API key encryption via encrypted cast
- One config per user enforcement
- Integration into user settings page

**Google Drive Settings (✅ Completed):**

- GoogleDriveConfig model implemented with encrypted service_account_json cast (✅ implemented)
- GoogleDriveConfigApiController with full CRUD endpoints (GET, POST, PATCH, DELETE) (✅ implemented)
- GoogleDriveService with listNewFiles(), downloadFile(), deleteFile() methods (✅ implemented)
- GoogleDriveMonitorJob (orchestrator) dispatches ProcessGoogleDriveConfigJob per config (✅ implemented)
- ProcessGoogleDriveConfigJob (worker) processes single config independently with retry logic (✅ implemented)
- Manual sync endpoint returns HTTP 202 ACCEPTED with "Google Drive sync has been queued" message (✅ implemented)
- Dusk test updated to verify queued message in info toast (✅ implemented)
- Total test coverage: GoogleDriveMonitorJobTest (3) + ProcessGoogleDriveConfigJobTest (14) + GoogleDriveConfigApiControllerTest (31) = 48 tests, 124+ assertions (✅ implemented)

### Key Deviations from Original Plan

1. **Event-Driven Architecture:** Original plan mentioned "EmailProcessingService (extend existing)" but implementation uses event-driven architecture instead. MailHandler fires EmailReceived event, CreateAiDocumentFromSource listener creates AiDocument. This is cleaner and more maintainable.

2. **ReceivedMail Relationship Direction:** Changed from `belongsTo` to `hasOne` relationship to AiDocument. One ReceivedMail creates one AiDocument, not the reverse. This aligns with the flow: email → document → transaction.

3. **Listener Registration:** Original plan didn't specify how CreateAiDocumentFromSource would be registered. Implementation relies on Laravel 12's auto-discovery instead of manual Event::subscribe(). This prevents duplicate registrations.

4. **Legacy Compatibility:** ProcessIncomingEmailByAi job kept as short-circuit to EmailReceived event for backward compatibility, though it could be fully removed in future.

5. **Testing Command:** Created app:simulate-incoming-email command (not in original plan) to facilitate local testing without SMTP setup. Includes --use-demo flag for quick testing.

6. **Manual Sync Response & Two-Tier Job Architecture (✅ Completed):** The manual sync endpoint now returns HTTP 202 ACCEPTED with `"Google Drive sync has been queued"`. Backend refactored from monolithic GoogleDriveMonitorJob into two-tier architecture: GoogleDriveMonitorJob (orchestrator) queries enabled configs and dispatches ProcessGoogleDriveConfigJob (worker) for each, enabling parallel processing and fault isolation. Each worker processes single config independently with 3 retries, 5-minute timeout. Orchestrator has 1 retry, 1-minute timeout (fast, just dispatches).

7. **AI Documents Date Filter Behavior (✅ Completed):** DateRangeFilterCard component for AI documents configured with `show-update-button="false"` per AiDocumentManager implementation. Filters update automatically as user types (modern refresh-on-input UX pattern) rather than requiring explicit button click. This differs from the Account Show feature which uses a button-based workflow, but both use the same reusable DateRangeFilterCard component with different configurations. Spec did not mandate specific update interaction pattern, so this is an implementation choice rather than deviation.

8. **Status Management Centralization (✅ Completed):** Original plan suggested both job and service would handle status updates. Implementation centralizes all status management in ProcessDocumentService only. AiProcessingJob simplified to just call service and dispatch events. This eliminates circular dependencies and ensures single source of truth for document status.

9. **Vision API Integration Pattern (✅ Completed):** Prism PHP integration uses `Image::fromLocalPath()` value objects instead of base64 strings or withImage() methods. Pattern: `Prism::text()->withPrompt($prompt, [Image::fromLocalPath($path)])`. This aligns with Prism PHP v0.99.19 API design.

10. **Enhanced Document Review UI (✅ Completed):** Added "Extracted details" tab to AiDocumentViewer.vue (not in original spec). Provides comprehensive view of extracted transaction data, line items table, and category mappings before finalization. Improves UX by allowing users to review all extracted data in-context without immediately opening transaction form.

11. **Transaction Finalization Architecture (✅ Completed):** Original spec mentioned reusing existing transaction form but was vague on integration details. Implementation uses CustomEvent-based architecture: AiDocumentViewer dispatches 'initiateCreateFromDraft' event, existing transaction modal listens and opens with prepopulated data. This approach avoids tight coupling between AI documents feature and transaction modal. Status update to 'finalized' and category learning handled in TransactionApiController's `handleAiDocumentFinalization()` method after successful transaction save. Category learning only increments usage_count for accepted AI suggestions (not user overrides), preserving learning accuracy.

### Required Composer Dependencies

**PDF Processing:**

- `"smalot/pdfparser": "^2.0"` - Extract text from PDF files (✅ already installed)

**Image Processing:**

- `"intervention/image": "^3.11"` - Image resizing for Vision AI (✅ already installed)

**AI Integration:**

- `"prism-php/prism": "^0.99.19"` - Unified AI provider abstraction (✅ already installed)
- Note: Verify Prism supports vision/multimodal in this version for `->attachMedia()` or similar

**OCR (Optional):**

- Tesseract OCR: No Composer package needed, uses command-line binary via Symfony Process

### Completed in Feb 2026 Session

**OCR & Text Extraction (✅ Fully Implemented):**

- ✅ TextExtractionService created: Orchestrates PDF, OCR, and text extraction with intelligent routing
- ✅ OcrService with triple-fallback: Tesseract binary → Tesseract HTTP → Vision API
- ✅ PdfExtractionService: Extracts text from PDFs with native/extractable text
- ✅ ImagePreprocessingService: Resizes images for Vision API (max 2048px, preserves aspect ratio)
- ✅ OcrUnavailableException: Clear error handling when images require OCR but none available
- ✅ Helper functions: tesseract_is_available(), tesseract_http_available(), tesseract_binary_available()
- ✅ Comprehensive test coverage: OcrServiceHttpModeTest (3 tests), PdfExtractionServiceTest (3 tests), TextExtractionServiceTest (7 tests), plus helper tests
- ✅ Documentation consolidated: 4 concise guides (README, SETUP, USAGE, REFERENCE) in .ai/docs/ replacing 9 verbose files
- ✅ Configuration: Flexible mode selection (binary/http) via environment variables
- ✅ ProcessDocumentService: Refactored to use TextExtractionService, removed duplicate detection from processing
- ✅ Docker Compose: Optional Tesseract sidecar configuration is documented in compose file comments
- ✅ Total: 24 OCR-related tests passing, 0 breaking changes, proper ServiceProvider registration

**AI Processing Pipeline (✅ Fully Implemented):**

- ✅ ProcessDocumentService: Complete refactoring with transaction type-specific processing
  - ✅ buildMainExtractionPrompt() - Separate JSON schemas for standard (withdrawal/deposit/transfer) vs investment (buy/sell/dividend)
  - ✅ Asset matching branches by transaction type: transfer (2 accounts), withdrawal (account+payee), deposit (payee+account), investment (account+investment)
  - ✅ matchInvestment() method for investment matching via similarity + AI
  - ✅ buildTransactionData() accepts accountFromId/accountToId for proper transfer handling
  - ✅ **checkExactCategoryMatch()** - Local exact matching for category learning with active category validation (✅ Implemented)
  - ✅ **matchCategoriesForItems()** - Batch category matching orchestrator combining exact + AI matches (✅ Implemented)
  - ✅ **matchCategoriesBatch()** - AI-powered batch category matching with confidence scoring (✅ Implemented)
  - ✅ **buildCategoryMatchingPrompt()** - Batch prompt with learning patterns + active categories (✅ Implemented)
  - ✅ **resolvePayeeCategoryShortcutItem()** - Uses 6-month payee category stats; if exactly one category exists, skips item-level AI matching and assigns full amount as one item (✅ Implemented)
  - ✅ Status management: processing → ready_for_review (success) or processing_failed (error)
- ✅ Payee category stats architecture updated (✅ Implemented)
  - ✅ Shared `PayeeCategoryStatsService` introduced and reused by API + processing flows
  - ✅ `PayeeStatsApiController` ownership/binding corrected to `AccountEntity` payees
  - ✅ `PayeeApiController::getPayeeDefaultSuggestion()` now uses the same shared aggregation logic
- ✅ AssetMatchingService: Type compatibility fixes + category learning support (✅ Enhanced)
  - ✅ calculateMatches() signature changed from array to iterable for Collection compatibility
  - ✅ matchInvestments() method working properly
  - ✅ **matchCategoryLearning()** - Similarity-based learning record matching (✅ Implemented)
  - ✅ **formatCategoryLearningForPrompt()** - Format top 50 learning records for AI (✅ Implemented)
  - ✅ **formatCategoriesForPrompt()** - Format active categories with hierarchy for AI (✅ Implemented)
- ✅ AiProcessingJob: Event-driven architecture
  - ✅ Simplified job dispatches events, no duplicate status setting
  - ✅ Removed failed() method - service handles all status updates
  - ✅ shouldNotRetry() for fail-fast on auth/quota errors
- ✅ Events & Listeners:
  - ✅ AiDocumentProcessedEvent - Success event with AiDocument instance
  - ✅ AiDocumentProcessingFailedEvent - Failure event with AiDocument + Exception
  - ✅ SendAiDocumentProcessedNotification - Queued listener sending success emails
  - ✅ SendAiDocumentProcessingFailedNotification - Queued listener sending failure emails
- ✅ Email Templates:
  - ✅ ai-document-processed.blade.php - Success notification using <x-mail::message> components
  - ✅ ai-document-processing-failed.blade.php - Failure notification with error details
  - ✅ Proper Blade component syntax (fixed "No hint path defined for [mail]" errors)

**Frontend Enhancements (✅ Implemented):**

- ✅ AiDocumentViewer.vue: Enhanced with extracted details tab
  - ✅ "Extracted details" tab visible when status='ready_for_review' (canFinalize)
  - ✅ Comprehensive transaction data display: type, date, currency, accounts, payees, investments
  - ✅ **Line items table with match type badges and confidence scores** (✅ Enhanced):
    - ✅ "Match Type" column with color-coded badges (Exact Match = green, AI Suggested = blue, No Match = gray)
    - ✅ "Confidence" column showing AI confidence percentage (≥80% green, 50-79% yellow, <50% red)
    - ✅ Helper methods: `getMatchTypeBadgeClass()`, `getMatchTypeLabel()`, `formatConfidence()`, `getConfidenceClass()`
    - ✅ Category display with full hierarchy (parent > child)
  - ✅ Responsive layout distinguishing standard vs investment transaction fields
  - ✅ showExtractedDetailsTab() method for programmatic tab switching via coreui.Tab API
  - ✅ "More details" button in sidebar card linking to full details tab

**Documentation Consolidation (✅ Completed):**

- ✅ Consolidated 9 verbose files into 4 focused documents (.ai/docs/)
- ✅ Removed redundancy: 3,620 → 1,213 lines
- ✅ Removed all Kubernetes references (0 K8s mentions remaining)
- ✅ Streamlined README.md (138 lines) - Quick overview and feature comparison
- ✅ Created SETUP.md (323 lines) - Installation for all 3 deployment modes
- ✅ Created USAGE.md (369 lines) - Code examples and error handling patterns
- ✅ Created REFERENCE.md (383 lines) - Architecture, API reference, performance tips

**Google Drive Integration (✅ Fully Implemented):**

- GoogleDriveMonitorJob: Simplified orchestrator (28 lines) that dispatches ProcessGoogleDriveConfigJob per enabled config (✅ implemented)
- ProcessGoogleDriveConfigJob: Full worker (138 lines) with file download, AiDocument creation, event firing, delete-after-import, error handling (✅ implemented)
- GoogleDriveConfigApiController: Complete CRUD + test + sync endpoints (31 API tests passing) (✅ implemented)
- GoogleDriveService: Extended with listNewFiles, downloadFile, deleteFile methods (✅ implemented)
- GoogleDriveConfigRequestTest: Validation for JSON structure and required keys (✅ implemented)
- GoogleDriveMonitorJobTest: 3 orchestrator tests validating dispatch logic (✅ implemented)
- ProcessGoogleDriveConfigJobTest: 14 comprehensive worker tests (✅ implemented)
- GoogleDriveConfigApiControllerTest: 31 API endpoint tests (✅ implemented)
- GoogleDriveSettingsTest.php (Dusk): Manual sync test updated for 202 response and "queued" message (✅ implemented)
- Total: 48 backend tests passing, 124+ assertions (✅ implemented)

**Additional Backend Components Completed (✅ Completed):**

- AiDocument model and migrations (✅ implemented)
- AiDocumentFile model and migrations (✅ implemented)
- CategoryLearning model and migrations (✅ implemented)
- AiDocumentApiController and all API endpoints (✅ implemented)
- ProcessDocumentService with full AI orchestration (✅ implemented)
- AiProcessingJob with retry logic (✅ implemented)
- AssetMatchingService with similarity scoring (✅ implemented)
- DuplicateDetectionService with threshold logic (✅ implemented)
- CategoryLearningService for item normalization (✅ implemented)
- PayeeStatsApiController for category stats (✅ implemented)
- PayeeCategoryStatsService shared by PayeeStatsApiController, PayeeApiController, and ProcessDocumentService (✅ implemented)
- AiDocumentPolicy with authorization checks (✅ implemented)
- Comprehensive test coverage for all services and controllers (✅ 50+ tests passing)

**Test Coverage (✅ Completed):**

- [AiDocumentsIndexTest.php](tests/Browser/Pages/AiDocuments/AiDocumentsIndexTest.php) (Dusk Browser Tests): 4 comprehensive tests for AI documents date filtering UI workflows (✅ implemented)
  - `test_ai_documents_applies_date_preset_on_load` - Verifies date preset parameter from URL applies to form
  - `test_ai_documents_applies_date_range_on_load` - Verifies explicit date_from/date_to parameters populate fields
  - `test_ai_documents_date_filter_updates_table` - Verifies manual date entry updates filter state
  - `test_ai_documents_preset_dropdown_populated_correctly` - Verifies preset dropdown shows selected value
  - Status: ✅ All 4 tests passing

- [AiDocumentFilterTest.php](tests/Feature/AiDocumentFilterTest.php) (Feature Tests): 6 comprehensive endpoint tests for filter robustness (✅ implemented)
  - `test_ai_document_status_filter_endpoint_loads` - Verifies status filter parameter accepted (HTTP 200)
  - `test_ai_document_source_filter_endpoint_loads` - Verifies source_type filter parameter accepted
  - `test_ai_document_filters_handle_special_characters_safely` - Regex special chars like `()[]|` don't crash endpoint
  - `test_ai_document_combined_filters_endpoint_loads` - Multiple filter parameters work together
  - `test_guest_cannot_access_ai_documents_index` - Authorization enforcement (guests rejected)
  - `test_user_sees_only_own_documents` - Data isolation verified (users see only own documents)
  - Status: ✅ All 6 tests passing

**Transaction Finalization & Category Learning (✅ Completed):**

- **Frontend Integration:**
  - ✅ AiDocumentViewer.vue `finalizeDocument()` method dispatches 'initiateCreateFromDraft' CustomEvent
  - ✅ Event payload includes transaction type and complete draft data from `processed_transaction_data`
  - ✅ `buildDraftTransaction()` constructs transaction object matching expected format for transaction modal
  - ✅ Existing transaction modal receives event and opens with prepopulated data
  - ✅ Supports both standard and investment transaction types

- **Backend Integration (TransactionApiController):**
  - ✅ TransactionRequest validation includes `ai_document_id` field (line 25, 62)
  - ✅ After transaction save, `handleAiDocumentFinalization()` method called (line ~780-810)
  - ✅ AI document status updated to 'finalized' (line 795)
  - ✅ `processed_at` timestamp set if not already present
  - ✅ Transaction's `ai_document_id` foreign key updated via `saveQuietly()` to avoid triggering events
  - ✅ Category learning updates via `updateCategoryLearning()` method (line ~815-872)

- **Category Learning Integration:**
  - ✅ `updateCategoryLearning()` method compares submitted items with draft recommendations
  - ✅ Only updates CategoryLearning records where user accepted AI suggestion (not overridden)
  - ✅ Normalizes item descriptions via CategoryLearningService before matching
  - ✅ Increments `usage_count` for accepted recommendations
  - ✅ Creates new CategoryLearning records for novel item/category pairs
  - ✅ Validates category exists and is active before creating learning record

- **UI Components:**
  - ✅ AiDocumentDuplicates.vue shows potential duplicate transactions with similarity scores
  - ✅ Duplicate detection runs when canFinalize=true and displays matches above finalize button
  - ✅ Click-through to view transaction details in modal
  - ✅ Warning badge indicates match percentage

- **Status Management:**
  - ✅ Document status transitions: ready_for_review → finalized
  - ✅ No UI refresh issues - status updated in backend and reflected after modal close
  - ✅ Finalize button disabled for non-ready_for_review statuses

### Pending/Not Yet Implemented

**Status reconciliation (Feb 2026):**

- ✅ Multi-file submissions are processed as one AiDocument and one processing flow using all files.
- ✅ `vision_enabled` in `ai_provider_configs` is implemented and wired to OCR/Vision behavior.
- ✅ Google Drive monitor/worker split and manual sync queueing are implemented.
- ⏳ File retention cleanup command/job remains not implemented.

**Notifications & Jobs:**

- ✅ Email notifications wired (AiDocumentProcessedEvent/FailedEvent with queued listeners)
- ✅ GoogleDriveMonitorJob scheduled (configurable interval via `AI_GOOGLE_DRIVE_SYNC_INTERVAL_MINUTES`)
- ⏳ File retention and cleanup job (`ai-documents:cleanup-old-files` command and scheduled task) - NOT YET IMPLEMENTED

**Tech debts, future improvements:**

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
- Consider storing AI conversation history per document for observability.
  - Verbose detail: Storing full AI message exchange can help debugging, transparency, and user-driven prompt tuning; possible as JSON + collapsible viewer section.
- Add learning flow for account/payee/investment overrides during finalization.
  - Verbose detail: If user overrides AI-selected account/payee/investment, provide quick path to learn/prefer that choice for future similar documents.
- [Tech debt] Revisit AI documents DataTable column width behavior after async refresh.
  - Verbose detail: Current width recalculation in the AI documents list can behave inconsistently depending on rendered content and timing. Keep current implementation for MVP; later evaluate a more deterministic layout strategy (for example fixed column sizing/colgroup, stronger redraw hooks, or table-specific CSS constraints) so the title column remains dominant while date and linked-transaction columns stay compact.
- Email notifications are added to the end of the queue when processing AI documents. It might make sense to introduce various queues for different types of jobs, and parallel workers for these.
- Normalize quantities out of item descriptions to improve matching/learning.
  - Verbose detail: Example: convert `3x Coca-Cola` to description `Coca-Cola` (+ quantity if needed) so category learning remains stable across variable quantities.
- Add overlap hint for multi-image receipts in extraction prompt.
  - Verbose detail: In multi-image uploads, instruct AI to detect overlapping content/pages so repeated lines are not double-counted.
- Improve failed-processing UX and allow prompt editing directly from AiDocument view.
  - Verbose detail: On processing failure, UI should clearly show error context and let user adjust custom prompt before reprocessing. This could be implemented together with the verbose processing history and AI conversation logging features for better transparency and control.
  - Agent prompt: In `AiDocumentViewer`, show actionable failure reason when status is `processing_failed`, allow inline edit/save of `custom_prompt`, and support reprocess flow without full page refresh.
- Add scanned-PDF fallback to OCR when extracted PDF text is empty.
  - Verbose detail: Scanned PDFs currently fail due to empty text extraction; add fallback to OCR path (PDF/image extraction strategy) to reduce user friction.
  - Agent prompt: Enhance `TextExtractionService` so PDF extraction falls back to OCR when parsed text is empty (or below threshold), while preserving existing image OCR paths and adding tests.
