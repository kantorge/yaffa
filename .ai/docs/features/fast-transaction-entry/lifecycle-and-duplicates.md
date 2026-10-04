# Document lifecycle and duplicate detection (Fast Transaction Entry, Phase 2)

As built. Design intent: [specification](../../specifications/fast-transaction-entry/specification.md), Phase 2, and the [concept](../../specifications/fast-transaction-entry/concept.md), "Duplicate detection". Nothing is recorded automatically in this phase.

## Statuses

`App\Enums\AiDocumentStatus` gains `auto_recorded`, `duplicate`, `awaiting_itemization`, `dismissed`. Helpers: `terminal()` (`finalized`, `auto_recorded`, `duplicate`, `dismissed`), `linkedToTransaction()` (`finalized`, `auto_recorded`, `duplicate`), `open()` (`ready_for_review`, `awaiting_itemization`), `reprocessable()` (`ready_for_review`, `processing_failed`, `dismissed`).

- **Every status write after creation goes through `AiDocument::transitionTo()`**, which also sets `status_changed_at` (new documents get it from a `creating` hook). The one exception is the bulk claim in `ProcessAiDocuments`, which sets `status_changed_at` in its own `update()`.
- Only today's processing reaches `duplicate`; `dismissed` is user-set; `auto_recorded` and `awaiting_itemization` are reached in Phase 4.
- `AiProcessingJob` skips documents linked to a transaction; `SendAiDocumentProcessedNotification` sends nothing for `duplicate` and `awaiting_itemization`.

## Extraction and content hash

- The main extraction gains the optional `document_kind`, `transaction_time` (HH:MM), `bank_reference`, `card_last_digits`. `ProcessDocumentService::sanitizeOptionalFields()` drops unusable values (unknown kind, malformed time) rather than failing the extraction; `AiExtractionSchemaValidator` accepts them as optional keys. They are stored in the draft's `raw`, and `document_kind` is also copied to `ai_documents.document_kind`.
- `ai_documents.content_hash` = sha256 of the sorted per-file sha256 values (`AiDocument::hashFiles()`), set by the three create paths: upload (files and text), received email (the formatted text), Google Drive.

## SameEventClassifier

`App\Services\SameEventClassifier` (withdrawals, deposits and transfers only; investments are not classified).

- **Key:** exact amount (`config.amount_from`, decimal), date within `duplicate_date_window_days`, the payee side known and equal, the account side equal where both have one. Drafts are read through `TransactionDraftService::normalize()`.
- **Pair verdict** (`same` / `candidate` / `separate`), from a document and either another open document or a document linked to a committed transaction (through `ai_document_id` or a `duplicate_of` origin):
  - Same `content_hash` or `bank_reference`, or times within `same_event_minutes` → `same`.
  - Both times known but apart → `separate` for the same kind, `candidate` otherwise. Different references of the same kind → `separate`.
  - Complementary kinds (`bank_notification` against `receipt`/`invoice`) with no contradicting time → `same`.
  - Same kind with no signal → `candidate` against a document, `separate` against a transaction (**one to one**: a transaction absorbs at most one document of each kind; a signal that would otherwise absorb a same-kind document downgrades to `candidate`).
  - A transaction without any linked document (entered by hand) counts as complementary to every document: **a manual entry with a key match is the same event**. A genuine repeat of a purchase is protected only by the one-to-one rule.
- **Outcome** (`SameEventOutcome`), strongest first: `exact_repeat` (same hash as a `ready_for_review`, `awaiting_itemization`, `finalized` or `auto_recorded` document), `same_event_transaction`, `same_event_document`, `candidate`, `near_match` (amount within `duplicate_amount_tolerance_percent`, other key parts equal), `none`.

## Processing outcome

`ProcessDocumentService::decideOutcome()` runs at the end of `process()` under `Cache::lock("ai-decide:{userId}")` and a DB transaction:

| Outcome | Result |
| --- | --- |
| `exact_repeat` | `duplicate`, no origin row, silent |
| `same_event_transaction` | `duplicate` + `transaction_origins` row `duplicate_of` (the transaction is never changed) |
| anything else | `ready_for_review` (the Phase 4 gates will hook in at `none`) |

## Manual entry

- `POST /api/v1/transactions/duplicate-check` (`TransactionApiController::duplicateCheck`, `read`): `{transactions, documents}` matching amount, date window, payee and account of the entry. Transfers and investments return empty lists. `exclude_transaction_id` leaves out the transaction being edited.
- `TransactionRequest` accepts `close_ai_document_id` (owned, `ready_for_review` or `awaiting_itemization`, different from `ai_document_id`). After the transaction is committed, `TransactionCreationService::closeAiDocument()` locks the document and, if it is still open, writes a `duplicate_of` origin and sets `duplicate`. A document that changed in the meantime is skipped; saving is never blocked. The update (edit) path ignores the field.
- `TransactionFormStandard.vue`: debounced (500 ms) check once accounts, date and amount are filled (not for templates, schedules, transfers); a non-blocking warning above the save buttons lists transactions and open documents, each document with a *close this document when saving* checkbox (one at a time; hidden when editing).

## Dismiss and reprocess

- `POST /api/v1/documents/{id}/dismiss` (`write`): `ready_for_review` and `processing_failed` only, row-locked; else 422.
- Reprocess and the PATCH status path use `AiDocumentStatus::isReprocessable()`: `dismissed` is allowed, `duplicate` and `auto_recorded` are not (nor `finalized`).
- `AiDocumentViewer.vue`: *Dismiss* button; the linked-transaction entry also follows the `duplicate_of` origin (`GET /documents/{id}` now includes `origins`).

## Retention

`CleanupOldAiDocuments` deletes every terminal status; see `../ai-document-retention/`. `AiDocument`'s `deleting` hook deletes `duplicate_of`/`conflicts_with` origins and nulls `origin_id` on `created` ones.

## Settings

`ai_user_settings.same_event_minutes` (default 10, `AiUserSettingsResolver`, request, resource, `AiBehaviorSettings.vue`).

## Tests

`tests/Feature/FastTransactionEntry/`: `SameEventClassifierTest` (truth table), `AiDocumentLifecycleTest` (outcomes, dismiss, reprocess, hash, summary), `ManualEntryDuplicateTest` (duplicate-check endpoint, `close_ai_document_id`), `RetentionTerminalStatusesTest`; browser: `tests/PEST/Pages/AiDocuments/ManualEntryClosesDocumentTest.php` (group `critical`); ability rows in `ApiAbilityEnforcementTest`.

## Known gaps

- `database/schema/mysql-schema.sql` is not regenerated (it was already stale).
- A same-event match with another open document is only listed (`documents` in the response of `POST /documents/{id}/check-duplicates`, shown in the "Potential duplicates" card); the merge is Phase 4. The card is shown only while the document can be finalized.
