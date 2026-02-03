# AI Document Processing (MVP)

## Feature Summary

Introduce AI-powered document processing to convert user-submitted documents (text, PDF, images, email receipts, Google Drive uploads) into draft transaction data aligned with YAFFA’s transaction model. Processing is autonomous, asynchronous, and supports multi-item receipt categorization. Drafts are reviewed by the end-user in a modal transaction form and finalized into actual transactions, linking back to the original AI document.

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

## Assumptions

- Laravel 12 + MySQL + Redis queues are available.
- AI providers require user-supplied API keys and billing setup.
- Storage limits are managed by self-hosted users.
- For MVP, AI providers are OpenAI and Gemini, configured via static list.
- Original uploaded and imported files are stored by the application; resized images are generated on-the-fly for AI input and not persisted.
- AI processing is always asynchronous; email notifications are used for failures and completion.

## Backend Scope (Laravel)

- Models:
  - `AiDocument` (new)
    - `id`
    - `user_id`
    - `status` (enum: `draft`, `ready_for_processing`, `processing`, `processing_failed`, `ready_for_review`, `finalized`)
      - `draft` - Initial state, created or imported, not yet submitted for processing
      - `ready_for_processing` - Queued for AI processing, all minimum required data present
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
  - `AiDocumentFile` (new)
    - `id`
    - `ai_document_id` (FK to AiDocument)
    - `file_path` (location in local storage)
    - `file_name`
    - `file_type`
    - `created_at`
  - `CategoryLearning` (new)
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
  - Update existing `ReceivedMail` model to reflect new app behavior. (✅ implemented)
    - Remove `transaction_data`, `processed`, and `handled` flags, as AIdocument processing supersedes them. (✅ implemented)
    - Remove `transaction_id` FK, as transactions are now linked via AiDocument. (✅ implemented)
    - `ReceivedMail` is no longer a standalone entity in the UI; it is linked to `AiDocument` only. (✅ implemented)
    - **Deviation:** ReceivedMail relationship to AiDocument changed from `belongsTo` to `hasOne` (one email can create one document, not the reverse)

- Migrations:
  - `ai_documents`
    - `id` - bigint unsigned, primary key
    - `user_id` - bigint unsigned, foreign key to users, cascade on delete
    - `status` - varchar(50), not null, default 'draft'
    - `source_type` - varchar(50), not null
    - `processed_transaction_data` - json, nullable
    - `google_drive_file_id` - varchar(255), nullable, unique
    - `received_mail_id` - bigint unsigned, nullable, foreign key to received_mails, cascade on delete
    - `custom_prompt` - text, nullable
    - `processed_at` - timestamp, nullable
    - `created_at`, `updated_at` - timestamps
    - Indexes: `user_id`, `status`, `source_type`, `google_drive_file_id`
  - `ai_document_files`
    - `id` - bigint unsigned, primary key
    - `ai_document_id` - bigint unsigned, foreign key to ai_documents, cascade on delete
    - `file_path` - varchar(500), not null
    - `file_name` - varchar(255), not null
    - `file_type` - varchar(10), not null (pdf, jpg, png, txt)
    - `created_at` - timestamp
    - Indexes: `ai_document_id`
  - `category_learning`
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
    - `created_at`, `updated_at` - timestamps
  - Add `ai_document_id` (bigint unsigned, nullable FK to ai_documents) to `transactions`
  - Remove `transaction_data`, `processed`, `handled`, `transaction_id` from `received_mails` (✅ implemented - Migration 2026_01_31_180343 and 2026_02_03_185934)
    - During the migration, create AiDocument records for existing processed received mails to preserve data integrity. (✅ implemented)
    - With a best effort, update linked transactions to reference the new AiDocument records. (Don't try to fix broken, inconsistent data.) (✅ implemented)
    - Status mapping: (✅ implemented)
      - If `processed` = true and `transaction_id` is set → `finalized`
      - If `processed` = true and no `transaction_id` → `ready_for_review`
      - If `processed` = false → `draft`
    - The 'down' migration does not attempt to restore removed fields or data. It only drops the AiDocument records created during the 'up' migration. (✅ implemented)
    - **Note:** Migration 2026_02_03_185934 became a no-op since columns were already removed in prior migration

- Controllers / APIs:
  - `AiDocumentController`
    - `POST /api/documents` - Upload document
      - Request: multipart/form-data with `files[]`, `text_input`, `custom_prompt`
      - Validation: at least one file OR text_input required; max 10 files; max 50MB per file; allowed types: pdf,jpg,png,txt
      - Response: `{"id": 1, "status": "ready_for_processing", "message": "..."}`
    - `PATCH /api/documents/{id}` - Update custom prompt or status
      - Request: `{"custom_prompt": "...", "status": "..."}`
      - Response: `{"id": 1, "status": "...", "custom_prompt": "..."}`
    - `GET /api/documents` - List user's documents
      - Query params: `status`, `source_type`, `page`, `per_page` (default 15)
      - Response: paginated list with `{"data": [...], "meta": {...}, "links": {...}}`
    - `GET /api/documents/{id}` - Get document details
      - Response: full document with files, processed_transaction_data, duplicate_warnings
    - `POST /api/documents/{id}/reprocess` - Trigger reprocessing
      - Response: `{"message": "Reprocessing queued", "status": "ready_for_processing"}`
    - `DELETE /api/documents/{id}` - Delete document and files
      - Response: 204 No Content
  - `AiProviderConfigApiController` (✅ implemented)
    - `GET /api/ai/config` - Get user's config (only one exists)
      - Response: `{"id": 1, "provider": "openai", "model": "gpt-4o-mini", "created_at": "...", "updated_at": "..."}` (API key never returned)
    - `POST /api/ai/config` - Create config (enforced: one per user)
      - Request: `{"provider": "openai|gemini", "model": "...", "api_key": "..."}`
      - Validation: provider required, model required, api_key required; rejects if config exists
      - Response: `{"id": 1, "provider": "...", "model": "...", "message": "AI provider configured successfully"}`
    - `PATCH /api/ai/config/{aiProviderConfig}` - Update config
      - Request: `{"provider": "...", "model": "...", "api_key": "..."}` (api_key can be omitted or `__existing__`)
      - Response: `{"id": 1, "provider": "...", "model": "...", "updated_at": "..."}`
    - `DELETE /api/ai/config/{aiProviderConfig}` - Delete config
      - Response: 204 No Content
    - `POST /api/ai/test` - Test connection
      - Request: `{"provider": "...", "model": "...", "api_key": "..."}` (api_key can be `__existing__`)
      - Response: `{"message": "Connection successful"}` OR `{"message": "..."}` (400)
  - `GoogleDriveController` under `/api/ai/*`
    - `GET /api/ai/google/auth-url` - Get OAuth URL
      - Response: `{"auth_url": "https://..."}`
    - `POST /api/ai/google/callback` - Handle OAuth callback
      - Request: `{"code": "..."}`
      - Response: `{"success": true, "message": "Connected"}`
    - `POST /api/ai/google/connect` - Manually trigger connection
      - Response: redirects to OAuth flow
    - `POST /api/ai/google/disconnect` - Remove OAuth tokens
      - Response: `{"message": "Disconnected"}`
    - `POST /api/ai/google/sync` - Manually trigger sync
      - Response: `{"message": "Sync queued", "documents_created": 3}`
    - `POST /api/ai/google/toggle` - Enable/disable monitoring
      - Request: `{"enabled": true|false}`
      - Response: `{"enabled": true|false}`
    - `GET /api/ai/google/status` - Get monitoring status
      - Response: `{"enabled": true, "folder_id": "...", "last_sync": "..."}`
  - `PayeeStatsController`
    - `GET /api/ai/payees/{id}/category-stats` - Category usage stats for a payee
      - Query params: `months` (default 6)
      - Response: `{"payee_id": 123, "months": 6, "categories": [{"category_id": 7, "count": 14, "percent": 0.7}]}`
      - Stats basis: transaction items linked to transactions for this payee within the time window; percent is based on total item count. (Handle if the same category appears multiple times in one transaction.)
  - **ReceivedMail controllers/services:** (✅ implemented)
    - `ReceivedMailController` is removed from user-facing routes (no direct view/edit/delete). (✅ implemented)
    - `ReceivedMailService` is removed becoming obsolete. (✅ implemented)
    - `ReceivedMailPolicy` is removed. (✅ implemented)
    - All received mail routes removed from web.php and breadcrumbs.php (✅ implemented)
    - Navigation menu entry for "Received emails" removed (✅ implemented)
    - Vue route loader for received-mail removed from app.js (✅ implemented)

- Services / Jobs:
  - `ProcessDocumentService`
    - Orchestrates full document processing pipeline
    - Validates files (type, size)
    - Extracts text/content from files
    - Prepares AI prompts with user assets and learning data
    - Fetches payee category statistics (last X months) to optimize item categorization
    - Calls AI provider via Prism
    - Validates AI response against schema
    - Updates document status
  - `AiProcessingJob` (queued on 'default' queue)
    - Wraps ProcessDocumentService for async execution
    - Implements retry logic (3 attempts, 30s delay)
    - Sends email notifications on success/failure (once per final outcome, including retries)
    - Updates document status on completion/failure
  - `AssetMatchingService`
    - Calculates similarity scores using `similar_text()`
    - Ignores similarity < threshold (0.5, to be finalized), to avoid polluting the AI prompt
    - Filters and ranks accounts/payees/investments
    - Returns top 10 matches if >10 exist, else all
    - Formats asset list for AI prompt (ID: Name|Alias)
  - `DuplicateDetectionService`
    - Queries transactions within date window (3 days)
    - Calculates similarity scores for matches
    - Returns array of transaction IDs with scores > threshold
    - Checks: type, date, amount (10%), account/payee/investment
  - `CategoryLearningService`
    - Normalizes item descriptions (lowercase, trim, punctuation)
    - Saves/updates learning records on transaction save
    - Retrieves learning data for AI prompt context
    - Increments usage_count on match
  - `GoogleDriveMonitorService` (scheduled job, runs every 15 min if enabled)
    - Fetches new files from configured folder
    - When multiple new files are detected, each file imported generates a dedicated new AiDocument
    - Checks for duplicates via google_drive_file_id and ignores already imported items
    - Downloads files to storage
    - Creates AiDocument records
    - Optionally deletes files from Drive after import
  - `EmailProcessingService` (extend existing)
    - Parses incoming email (text/HTML cleanup)
    - Extracts attachments
    - Creates AiDocument with source_type=received_email
    - Triggers AiProcessingJob
    - **Deviation:** Email processing refactored to use event-driven architecture instead of a dedicated service. MailHandler now fires EmailReceived event, CreateAiDocumentFromSource listener creates AiDocument. (✅ implemented)

- Policies / Auth:
  - `AiDocumentPolicy` (view, create, delete, reprocess)
  - `AiProviderConfigPolicy` (view, create, update, delete)

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
      - Legacy ProcessIncomingEmail listener and IncomingEmailReceived event removed (Feb 3, 2026)
      - Fixed duplicate AiDocument creation by relying on auto-discovery instead of manual Event::subscribe()
  - Email notifications on:
    - **Processing success (ready for review)**
      - Mailable: `App\Mail\AiDocumentProcessed`
      - Subject: "Document processed - Review your transaction"
      - Content: Document ID, source type, extracted amount/payee, link to review page
      - View: `resources/views/emails/ai-document-processed.blade.php`
    - **Processing failure (AI error, after depleting retries)**
      - Mailable: `App\Mail\AiDocumentProcessingFailed`
      - Subject: "Document processing failed"
      - Content: Document ID, error type (auth/quota/model/network), suggested action, link to AI config settings
      - View: `resources/views/emails/ai-document-processing-failed.blade.php`

## Frontend Scope (Vue + Bootstrap)

- Pages / Routes:
  - `Documents Index` - `/ai-documents`
    - Blade view: `resources/views/ai-documents/index.blade.php`
    - Layout: extends `layouts.app`
    - Vue component: `AiDocumentList.vue`
    - Features: DataTable with filters (status, source_type), pagination, search
  - `Document Review` - `/ai-documents/{id}`
    - Blade view: `resources/views/ai-documents/show.blade.php`
    - Layout: extends `layouts.app`
    - Vue components: `AiDocumentViewer.vue`, `TransactionPreview.vue` (existing)
    - Features: file preview, draft transaction display, finalize button, reprocess button
  - `AI Provider Settings` - `/user/settings` (✅ integrated into existing settings page)
    - Blade view: `resources/views/user/settings.blade.php`
    - Layout: extends `layouts.app`
    - Vue component: `AiProviderSettings.vue`
      - Features: provider/model selection, API key input, test connection button, add/update/delete
    - `Google Drive Settings` - `/settings/google-drive`
      - Blade view: `resources/views/settings/google-drive.blade.php`
      - Layout: extends `layouts.app`
      - Vue component: ` (✅ implemented)
    - No user-facing pages or CRUD actions for `ReceivedMail` (✅ implemented)
    - Any existing ReceivedMail views/routes should be removed or hidden (✅ implemented)
    - All ReceivedMail views, controllers, policies, and routes removed
    - Mail templates updated (TransactionCreatedFromEmail simplified, TransactionErrorFromEmail removed)
    - Blade view: `resources/views/ai-documents/create.blade.php`
    - Layout: extends `layouts.app`
    - Vue component: `DocumentUploadForm.vue`
    - Features: drag-drop file upload, text input, custom prompt textarea
  - **ReceivedMail UI:**
    - No user-facing pages or CRUD actions for `ReceivedMail`
    - Any existing ReceivedMail views/routes should be removed or hidden

- Components:
  - `DocumentUploadForm`
  - Reuse existing transaction view component for preview
  - Use existing transaction form and modal container for finalization

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
  "transaction_type_id": 1,
  "config": {
    "amount_to": 4,
    "amount_from": 4,
    "account_to_id": 583,
    "account_from_id": 9
  },
  "items": [
    { "amount": 2.50, "category_id": 12 },
    { "amount": 1.50, "category_id": 7 }
  ]
}
```

Investment transaction example:

```
{
  "raw": { ... },
  "date": "2023-09-05",
  "config_type": "investment",
  "transaction_type_id": 4,
  "config": {
    "account_id": 9,
    "investment_id": 144,
    "price": 125.34,
    "quantity": 2.0,
    "commission": 1.25,
    "tax": 0.75,
    "dividend": 0.0
  },
  "items": []
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
  "transaction_type_id": "number|null: the ID of the transaction type in YAFFA, or null if not determined",
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
  "items": "array of objects|null: line items for multi-item receipts; empty array if none",
    [
      {
        "amount": "number|null: the amount for this item",
        "category_id": "number|null: the ID of the category in YAFFA, or null if not determined"
      }
    ]
}
```

## Processing Flow

1. User submits document (web, email, or Google Drive).
   - When a submission contains multiple files, it is still considered to be one AiDocument.
   - E.g. a longer receipt could be attached using multiple photos
2. `AiDocument` record created with status `ready_for_processing`.
3. `AiProcessingJob` runs:
   - Extracts text/vision data from all attached files, treating them as one input
   - Builds AI prompt with normalized assets and category learning data.
   - Calls AI provider (OpenAI/Gemini).
   - Validates output schema.
   - Stores JSON draft in `processed_transaction_data`.
4. Status set to `ready_for_review` and email notification sent.
5. User opens document review (`/ai-documents/{id}`) and clicks Finalize.
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
   - If `category_id` is provided, preselect that category in the dropdown
   - If `category_id` is null, no category is preselected
   - User can change category selection freely
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
  - Use PHP's `similar_text()` function to calculate similarity against `name` + `import_alias` (existing pattern from email processing).
  - Send all matches if < 10; otherwise send top 10 sorted by similarity score descending.
  - AI returns best single match only, or N/A if no match.
  - For investments, match against `name`, `code`, and `isin` fields.

- Category learning:
  - Flat table with normalized item descriptions.
  - Normalization: lowercase, trim, remove punctuation.
  - Stored only on transaction save.

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
  - Max dimensions: 2048x2048 pixels
  - Resized images not persisted (memory only)
  - Original files always retained
- **Retention:**
  - Environment variable: `AI_DOCUMENT_FILE_RETENTION_DAYS=90` (default)
  - Empty or `0` disables auto-deletion
  - Cleanup job: `php artisan ai-documents:cleanup-old-files`
  - Scheduled daily via Laravel scheduler
  - Only deletes files, not database records
- **File upload limits:**
  - Max files per submission: 10
  - Max file size: 50MB per file (configurable via `AI_DOCUMENT_MAX_FILE_SIZE_MB`)
  - Max total submission size: 500MB (configurable via `AI_DOCUMENT_MAX_TOTAL_SIZE_MB`)
  - Allowed types: pdf, jpg, jpeg, png, txt

## Google Drive Monitoring

- **OAuth2 Configuration:**
  - Credentials stored in user settings table: `google_drive_access_token`, `google_drive_refresh_token`, `google_drive_folder_id`
  - OAuth scopes: `https://www.googleapis.com/auth/drive.file` (read/write to app-created files), `https://www.googleapis.com/auth/drive.readonly` (read existing files)
  - If delete-after-import enabled: requires `https://www.googleapis.com/auth/drive` (full drive access)
  - Client ID/Secret stored in `.env`: `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`
- **Folder Selection (MVP):**
  - Preferred: Google Drive Picker dialog to select a folder after OAuth connection
  - Fallback: Manual folder ID input with a “Validate” action
  - Store selected `google_drive_folder_id` in user settings
  - Picker is optional for self-hosted setups where it may be blocked
- **File Tracking:**
  - Store `google_drive_file_id` in `ai_documents` table
  - Unique constraint prevents duplicate imports
  - Track last sync timestamp in user settings
- **Monitoring Schedule:**
  - Job: `App\Jobs\GoogleDriveMonitorJob`
  - Frequency: every 15 minutes (configurable via `AI_GOOGLE_DRIVE_SYNC_INTERVAL_MINUTES`)
  - Only runs if user has `google_drive_enabled = true` in settings
- **Delete After Import:**
  - User setting: `google_drive_delete_after_import` (boolean, default false)
  - Requires expanded OAuth scope and re-authentication when enabled
  - Files delete (✅ implemented)

- To optimize AI token usage, implement email content cleanup before AI processing.
- Extend existing cleanup from `ProcessIncomingEmailByAi::cleanUpText()`:
  - Remove image references: `[image:.*?]`
  - Remove link references: `<http[^>]+>`
  - **New for MVP:** Remove inline styles (style attributes, `<style>` tags) (✅ implemented in CreateAiDocumentFromSource::cleanHtmlContent)
  - **New for MVP:** Remove inline SVG elements (✅ implemented)
  - **New for MVP:** Remove base64-encoded data URIs (✅ implemented)
  - **New for MVP:** Strip unnecessary HTML tags while preserving text structure (✅ implemented)
  - HTML cleanup now performed in CreateAiDocumentFromSource listener before storing email content

## Testing & Development Tools (✅ implemented)

- **Email Simulation Command:** `php artisan ai:simulate-incoming-email` (✅ implemented)
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

    ````bash
    # Quick test with demo user
    sail artisan ai:simulate-incoming-email --use-demo

    # Custom email with text and HTML
    sail artisan ai:simulate-incoming-email \
      --from=user@example.com \
      --subject="Receipt from Coffee Shop" \
      --text="Coffee: $4.50" \
      --html="<p>Coffee: <strong>$4.50</strong></p>" \
      --create-user

    # Synchronous processing for debugging
    sail artisan ai:simulate-incoming-email --use-demo --sync
    ```  - **New for MVP:** Remove base64-encoded data URIs
    ````

  - **New for MVP:** Strip unnecessary HTML tags while preserving text structure

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
  - `GoogleDriveIntegrationTest`
    - OAuth flow
    - File fetch from Drive
    - Duplicate prevention (google_drive_file_id)
    - Document creation
    - Optional file deletion after import
  - `DuplicateDetectionIntegrationTest`
    - Multiple duplicate warnings
    - Soft warning display
    - User can proceed despite warning
  - `PayeeCategoryStatsTest`
    - Returns top categories for a payee
    - Honors `months` query parameter
    - Returns empty categories array when no history exists

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

## AI Provider Configuration

### Supported Providers & Models (MVP)

- For MVP, it's OK to work with a static list of supported models and providers.
- Future improvements can introduce further providers and dynamic model fetching.

**OpenAI:**

- `gpt-4o` - GPT-4 Omni (vision support, structured outputs, best for complex receipts)
- `gpt-4o-mini` - GPT-4 Omni Mini (cheaper, faster, recommended for MVP testing)
- `gpt-5-mini` - GPT-5 Mini (configured in app)

**Gemini:**

- `gemini-1.5-pro` - Gemini 1.5 Pro (vision support, long context, best for complex receipts)
- `gemini-1.5-flash` - Gemini 1.5 Flash (faster, cheaper, recommended for MVP testing)

### Default Configuration

- OpenAI default: `gpt-4o-mini`
- Gemini default: `gemini-1.5-flash`

### AI Parameters (based on existing email processing)

- Temperature: `0.1` (low for deterministic extraction)
- Top P: `1`
- Frequency penalty: `0`
- Presence penalty: `0`

## Prompt Engineering (based on existing patterns)

The system will use multi-step prompting similar to existing email processing:

1. **Main extraction prompt:** Extract transaction type, account, payee, date, amount, currency, and line items from document
2. **Account matching prompt:** Match extracted account name against user's account list (ID: Name|Alias format)
3. **Payee matching prompt:** Match extracted payee against user's payee list (ID: Name format)
4. **Investment matching prompt:** (if investment type) Match against user's investments (ID: Name|Code|ISIN format)
5. **Category matching prompt:** Match each line item description against user's category learning data.

**IMPORTANT:** line item matching is only needed for withdrawals and deposits, not for transfers or investment transactions.
After payee matching, there should be a backend call to determine the most used categories for that payee during the past 6 months (via `GET /api/ai/payees/{id}/category-stats`). If only one category is present in the stats for that period, category matching is skipped and that category is assigned to the entire amount as one line item.

All prompts require JSON responses with strict schemas to ensure validation.

## Edge Cases & Negative Test Scenarios

- **Upload Validation:**
  - No files and no text → reject with 422
  - File size 0 bytes → reject
  - File size > 50MB → reject
  - 11 files uploaded → reject (max 10)
  - Total size > 500MB → reject
  - Unsupported file type (.exe, .zip) → reject
  - Corrupted PDF → graceful failure with error message

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
  - OAuth token expired → refresh automatically or prompt re-auth
  - Folder deleted → fail gracefully, notify user
  - File deleted before download → skip, log warning
  - Network timeout → retry
  - Duplicate file (same google_drive_file_id) → skip silently

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

## Implementation Status & Deviations (Updated Feb 3, 2026)

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
- Mail templates updated (TransactionCreatedFromEmail simplified, TransactionErrorFromEmail removed)

**Event Architecture (✅ Completed):**

- EmailReceived event created (replaces IncomingEmailReceived)
- IncomingEmailReceived event deleted (Feb 3, 2026)
- ProcessIncomingEmail listener deleted (Feb 3, 2026)
- CreateAiDocumentFromSource auto-discovered by Laravel (no manual Event::subscribe needed)
- Fixed duplicate AiDocument creation bug by removing manual subscription in AppServiceProvider

**Testing Infrastructure (✅ Completed):**

- IncomingEmailTest updated for new flow (7 tests passing)
- SimulateIncomingEmailCommandTest created (2 tests, 11 assertions)
- ProcessIncomingEmailByAiTest renamed to test HTML cleanup in CreateAiDocumentFromSource
- ai:simulate-incoming-email command created with full MIME message support
- --use-demo flag added for simplified testing

**AI Provider Configuration (✅ Completed):**

- AiProviderConfig model implemented
- AiProviderConfigApiController with full CRUD + test endpoint
- API key encryption via encrypted cast
- One config per user enforcement
- Integration into user settings page

### Key Deviations from Original Plan

1. **Event-Driven Architecture:** Original plan mentioned "EmailProcessingService (extend existing)" but implementation uses event-driven architecture instead. MailHandler fires EmailReceived event, CreateAiDocumentFromSource listener creates AiDocument. This is cleaner and more maintainable.

2. **ReceivedMail Relationship Direction:** Changed from `belongsTo` to `hasOne` relationship to AiDocument. One ReceivedMail creates one AiDocument, not the reverse. This aligns with the flow: email → document → transaction.

3. **Listener Registration:** Original plan didn't specify how CreateAiDocumentFromSource would be registered. Implementation relies on Laravel 12's auto-discovery instead of manual Event::subscribe(). This prevents duplicate registrations.

4. **Legacy Compatibility:** ProcessIncomingEmailByAi job kept as short-circuit to EmailReceived event for backward compatibility, though it could be fully removed in future.

5. **Testing Command:** Created ai:simulate-incoming-email command (not in original plan) to facilitate local testing without SMTP setup. Includes --use-demo flag for quick testing.

### Pending/Not Yet Implemented

- AiDocument model and migrations
- AiDocumentFile model and migrations
- CategoryLearning model and migrations
- AiDocumentController and API endpoints
- ProcessDocumentService and AiProcessingJob
- AssetMatchingService
- DuplicateDetectionService
- CategoryLearningService
- GoogleDriveMonitorService
- Frontend Vue components (DocumentUploadForm, AiDocumentViewer, etc.)
- Transaction finalization flow
- Email notifications (AiDocumentProcessed, AiDocumentProcessingFailed)
- Google Drive integration
- Attachment handling in email processing
- File retention and cleanup job
