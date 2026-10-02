# Fast Transaction Entry — Specification

As of 2026-10-02 · Status: approved for implementation, no code yet

## 1. Purpose

This document is the implementation handoff for the agreed [concept](concept.md): transaction templates (R1), auto-recording of AI documents (R2, R3) and two-way duplicate detection (R4). The concept explains the *why*. This file defines the *what* and the *order*: data model, backend, frontend, tests and acceptance criteria, split into phases that each ship on their own. **Where this file and the concept differ, this file wins.**

The product principles in the concept apply to every phase. In short:

- Manual control beats speed.
- The Transaction model is never partial.
- The system never changes a recorded transaction.
- Manual entry is never blocked.
- Auto-recorded transactions are normal transactions.

## 2. Current State

What already exists and is reused. Line numbers are as of 2026-10-02.

| Area | Where | Notes |
| --- | --- | --- |
| AI draft | `ProcessDocumentService::buildTransactionData()` (`app/Services/ProcessDocumentService.php:512`), stored in `ai_documents.processed_transaction_data` | Shape: `{raw, date, config_type, transaction_type, config{amount_from, amount_to, account_from_id, account_to_id} \| {account_id, investment_id, …}, transaction_items[{amount, description, recommended_category_id, match_type, confidence_score}]}`. There is no version key and no `payee_id`: the payee is `config.account_to_id` for a withdrawal and `config.account_from_id` for a deposit |
| Draft enrichment for display | `enrichProcessedData()`, duplicated in `AiDocumentApiController` (`:478`) and `AiDocumentController` (`:98`) | Adds `matched_entities` and category names for the form selects |
| Draft → form | Modal: `initiateCreateFromDraft` window event → `ModalStandard.vue` / `ModalInvestment.vue` (action `finalize`). Page: `POST transactions.createFromDraft` → `TransactionController::createFromDraft()` (`:135`) | Already used by the AI viewer, the import page and the account page "new transaction" button |
| Form actions | `TransactionController::openTransaction()` (`:83`): `clone, create, edit, enter, finalize, replace, show`; `TransactionRequest` `action` rule (`:168`) | One request class for every action |
| Transaction creation | `TransactionApiController::storeStandard()` (`:539`) / `storeInvestment()` (`:603`) | Logic lives in the controller. `TransactionCreated` is dispatched only here |
| Event gap | `TransactionService::enterScheduleInstance()` (`:28`) | Creates transactions without `TransactionCreated`, so `currency_id`/`cashflow_value` and the summaries are not updated. Auto-recording must not copy this |
| Finalize | `TransactionApiController::finalizeAiDocument()` (`:944`) | Sets `finalized`, links `transactions.ai_document_id`, writes CategoryLearning |
| Matching | `AssetMatchingService` (`edgaras/strsim` Jaro-Winkler; `normalizeForMatching()` `:452` is private); alias = `account_entities.alias`, one entry per line | No identifier column exists anywhere |
| Duplicates | `DuplicateDetectionService::findDuplicates()` (`:36`), `POST /api/v1/documents/{id}/check-duplicates` | Scores date, amount and asset IDs (account/payee/investment) of the stored draft |
| Payee stats | `PayeeCategoryStatsService` (6-month category counts), `ProcessDocumentService::resolvePayeeCategoryShortcutItem()` (`:583`) | Basis for the payee profile |
| Settings | `ai_user_settings` + `AiUserSettingsResolver` (`DEFAULT_*` constants) + `AiUserSettingsRequest` + `AiBehaviorSettings.vue` | New settings follow this pattern |
| Statuses | `App\Enums\AiDocumentStatus`: `ready_for_processing, processing, processing_failed, ready_for_review, finalized` | Much code writes raw strings instead of the enum |
| Retention | `App\Jobs\CleanupOldAiDocuments` deletes `finalized` only; reminder email for everything else | CLAUDE.md critical rule |
| Reprocess | `AiDocumentApiController::reprocess()` (`:282`) and PATCH `update` | Allowed only from `ready_for_review` and `processing_failed`; a finalized document cannot be reprocessed |
| Dashboard | `resources/js/dashboard/components/Dashboard.vue`, widgets in `components/widgets/` | Widgets are hard-coded, self-contained cards |
| Morph map | `AppServiceProvider.php:69` | New morph targets need an alias |

## 3. Decisions

D1–D4 are owner decisions (2026-10-02). D5–D15 are defaults chosen while writing this spec. The owner may override any of them before the phase that uses it starts.

- **D1 Statuses: keep and extend.** The concept's names map onto the existing ones: received = `ready_for_processing`, needs_review = `ready_for_review`, recorded = `finalized`. `processing_failed` stays. New statuses: `auto_recorded`, `duplicate`, `awaiting_itemization`, `dismissed`. No data migration and no API break.
- **D2 Account identifiers are alias lines.** On account-type entities, an alias line that is all digits, optionally prefixed with `*`, is an identifier, for example `1234` or `*1234`. It matches the `card_last_digits` the AI extracts (D6) by exact digit equality. Payee normalization strips digit groups (D7), so account matching uses its own digit-preserving rule. No schema change.
- **D3 Global opt-in.** `ai_user_settings.auto_record_enabled`, default `false`. A payee on *Follow global* auto-records only when this setting is on (and `ai_enabled` is on) and its history qualifies. *Always allow* also needs the switch on.
- **D4 Retention covers every terminal status.** `finalized`, `auto_recorded`, `duplicate` and `dismissed` are deletable after the retention period. `ready_for_review`, `awaiting_itemization`, `processing_failed` and the processing states keep the reminder behaviour. On delete, origin rows with `relation = created` survive with `origin_id = NULL`, which keeps the provenance and review state. `duplicate_of` and `conflicts_with` rows are deleted with the document. `isOwnedPath()` and the never-delete-the-transaction rule are unchanged.
- **D5 Draft schema v2** (shared by AI documents and templates):
  ```jsonc
  {
    "schema_version": 2,
    "config_type": "standard" | "investment",
    "transaction_type": "withdrawal" | "deposit" | "transfer" | <investment types>,
    "date": "YYYY-MM-DD",            // optional; templates never store it
    "comment": "…",                  // optional
    "config": { /* as today: amount_from, amount_to, account_from_id, account_to_id | account_id, investment_id, quantity, price, commission, tax, dividend */ },
    "transaction_items": [{
      "category_id": 12,             // optional; user-chosen (templates, merged receipts)
      "amount": "1500.00",           // optional; decimal string
      "comment": "…", "tag_ids": [3],// optional
      "description": "…", "recommended_category_id": 12, "match_type": "exact", "confidence_score": 0.97  // AI-only, optional
    }],
    "raw": { … }                     // AI-only, never in templates
  }
  ```
  - Every key except `schema_version` and `config_type` is optional.
  - Amounts are decimal strings, following the MoneyCast/DecimalCast wire format.
  - A draft without `schema_version` is v1 and is upgraded on read. v1 → v2 only adds the key; nothing is renamed.
  - One `App\Services\TransactionDraftService` owns the draft. It validates, upgrades, enriches (it absorbs both `enrichProcessedData()` copies), and blanks stale references.
  - A stale reference is an ID that is deleted, inactive, or not owned by the user. Blanking it removes the key and adds a notice, for example `{field: "config.account_from_id", reason: "inactive"}`.
- **D6 New extraction fields.** The main extraction schema in `AiStepGateway` (standard keys in `AiExtractionSchemaValidator`) gains four optional fields:
  - `document_kind`: `bank_notification | receipt | invoice | other`
  - `transaction_time`: `HH:MM`
  - `bank_reference`
  - `card_last_digits`

  They are stored in `raw` and copied to `ai_documents.document_kind`, which is indexed for queries. A document is **itemized** when its kind is `receipt` or `invoice` and it has at least one item. `ai_documents.content_hash` is the sha256 of the sorted per-file sha256 values, computed when the document is created.
- **D7 Payee matching** reuses the existing normalization, which is extracted from `AssetMatchingService::normalizeForMatching()` into a public static helper. The new helper additionally strips legal suffixes and digit groups (`kft, zrt, bt, nyrt, kkt, ltd, gmbh`). Similarity reuses Jaro-Winkler; no new dependency. The concept's ≥ 0.92 fits Jaro-Winkler: one OCR error in a 12-character name scores about 0.95, whereas normalized Levenshtein would score 0.917 and fail.
- **D8 Payee profile window.** The most recent 50 non-schedule withdrawals and deposits of the payee, with no time limit. Infrequent payees keep enough history, and frequent payees adapt to recent behaviour.
- **D9 Wilson bound** uses z = 1.645 (two-sided 90%). This is the only z that reproduces the concept's examples: 10/10 = 0.787 misses, 11/11 = 0.803 passes, 20/21 = 0.812 passes, 18/20 = 0.738 misses. With z = 1.2816, 10/10 would already pass. The metric is the share of profile transactions that have exactly one item and whose item is in the dominant category.
- **D10 Payee settings** go on the `payees` config table, next to `category_id`. `account_entities` is shared with accounts.
- **D11 `transactions.ai_document_id` stays** the "created from" link, used by the existing UI and import. The origin record adds `decision_reason`, `reviewed_at`, and the `duplicate_of` / `conflicts_with` links. Finalize keeps setting `ai_document_id` and writes no origin record (concept: manual entry, no review).
- **D12 Transaction creation moves into a service.** `App\Services\TransactionCreationService` receives the logic of `storeStandard()` and `storeInvestment()`: config, transaction, items, remaining-payee-default item, schedule, item merge, and **`TransactionCreated` dispatch**. The controller and the Phase 4 auto-recorder both call it.
- **D13 Race safety.** A per-user `Cache::lock("ai-decide:{userId}")` on Redis wraps the "duplicate check → decide → commit" step. The AI calls before it stay parallel.
- **D14 Side effects of the new outcomes:**
  - Auto-recording writes no CategoryLearning (no person confirmed the category).
  - The processed-notification listener sends a "Recorded automatically" variant for `auto_recorded`. `duplicate` sends nothing; `awaiting_itemization` sends nothing.
  - **Reprocess** is allowed only from `ready_for_review`, `processing_failed` and `dismissed`. This extends the existing rule (`ready_for_review`, `processing_failed`) with `dismissed`. Documents linked to a transaction (`finalized`, and the new `auto_recorded` and `duplicate`) cannot be reprocessed.
- **D15 Aliases:**
  - A normalized payee alias line (and the normalized payee name) must be unique among one user's payees. A conflict is a 422 on save.
  - When several aliases match as leading tokens, the longest one wins.
  - `CsvParserService::loadPayeeLookup()` (`:682`) treats the whole alias as one string. Fix it to split on lines like everywhere else.

## 4. Data Model

All migrations must be reversible. Update the `database/schema/mysql-schema.sql` dump as well. It is already stale: `transactions.ai_document_id` is still shown as CASCADE and `document_retention_days` is missing.

| Table | Change | Phase |
| --- | --- | --- |
| `transaction_templates` (new) | `id`, `user_id` FK cascade, `name` varchar(255), `payee_id` FK → `account_entities` **null on delete**, nullable, `draft` json, `is_featured` bool default false, `use_count` unsigned int default 0, `last_used_at` timestamp null, timestamps. Unique (`user_id`, `name`); index (`user_id`, `is_featured`) | 1 |
| `ai_documents` | + `content_hash` char(64) null, index (`user_id`, `content_hash`); + `document_kind` varchar(32) null; + `status_changed_at` timestamp null (backfilled from `updated_at`) | 2 |
| `transaction_origins` (new) | `id`, `user_id` FK cascade, `transaction_id` FK cascade, `origin_type` varchar(64) null, `origin_id` unsigned bigint null, `relation` varchar(32) (`created`, `duplicate_of`, `conflicts_with`), `decision_reason` text null, `reviewed_at` timestamp null, timestamps. Index (`origin_type`, `origin_id`), (`user_id`, `relation`, `reviewed_at`) | 2 |
| `payee_profiles` (new) | `id`, `account_entity_id` FK unique cascade, `sample_size`, `dominant_category_id` FK null on delete, `dominant_share` decimal(5,4), `single_item_dominant_count` unsigned int, `wilson_lower` decimal(5,4), `amount_median` decimal(20,4) null, `amount_min`/`amount_max` decimal(20,4) null, `amount_mode_share` decimal(5,4) null, `known_amounts` json (distinct amounts, capped at 20), `typical_account_ids` json, `multi_item_share` decimal(5,4), `calculated_at` timestamp | 3 |
| `payees` | + `auto_record_policy` varchar(16) default `follow_global` (`follow_global`, `always`, `never`); + `itemization_expected` bool default false | 3 |
| `ai_user_settings` | + `auto_record_enabled` bool default false (4); + `auto_record_wilson_min` decimal(4,3) default 0.800; + `auto_record_min_history` unsigned tinyint default 10; + `auto_record_amount_tolerance_percent` decimal(5,2) default 20.00; + `itemization_timeout_days` unsigned tinyint default 14 (4); + `same_event_minutes` unsigned tinyint default 10 (2); + `payee_similarity_min` decimal(4,3) default 0.920; + `payee_similarity_margin` decimal(4,3) default 0.100. All are nullable-with-default in the DB and resolved through `AiUserSettingsResolver` (DB → config → `DEFAULT_*`). `duplicate_date_window_days` (default 3) is reused | 2–4 |
| `transactions` | **No change** | — |

Morph map: add `ai_document => AiDocument::class`. Money amounts on new models use `MoneyCast` or `DecimalCast`, never `float`. Profile amounts are in the payee's typical account currency; mixed currencies use `*_base` amounts, matching what the existing reports do.

## 5. Phases

| Phase | Ships | Depends on |
| --- | --- | --- |
| 0 Foundations | Nothing visible; refactors only | — |
| 1 Templates (R1) | Templates, dashboard widget | 0 |
| 2 Lifecycle and duplicates (R4) | New statuses, document dedupe, manual-entry warning, dismiss | 0 |
| 3 Payee profile and matching (R2/R3 groundwork) | Profiles, payee settings, candidates page | 0 (2 for the alias offer in review) |
| 4 Auto-recording (R2/R3) | Gates, auto-recording, itemization hold, review lists | 1, 2, 3 |

Phases 1, 2 and 3 are independent of each other and can run in any order or in parallel. Each phase is one PR. Phase 4 may be split into 4a (gates, auto-record, review list) and 4b (itemization hold, merge, timeout, conflicts).

---

### Phase 0: Foundations

**Scope:** D5 (service + v2 writes), D12, factory states. **State at end of phase:** behaviour unchanged; every new draft carries `schema_version: 2`.

**Backend**

- `TransactionDraftService`:
  - `normalize(array $draft, User $user): array{draft, notices}` validates, upgrades, and blanks stale references.
  - `enrich(array $draft, User $user): array` moves here from both controllers; the two private copies are deleted.
  - `fromTransaction(Transaction $t): array` builds a v2 draft without `date`; used by *Save as template*.
  - `toUnsavedTransaction(array $draft, User $user): Transaction` is extracted from `TransactionController::createFromDraft()`.
- `ProcessDocumentService::buildTransactionData()` adds `schema_version: 2`.
- `TransactionCreationService` per D12. `storeStandard()` and `storeInvestment()` become thin. `finalizeAiDocument()` remains a post-commit step in the service and keeps its response.
- `AiDocumentFactory` states: `readyForReview()`, `finalized()`, `withDraft(array)`; later phases add `autoRecorded()`, `duplicate()`, `awaitingItemization()`, `dismissed()`.

**Frontend:** none.

**Tests (Pest 5)**

- Unit `TransactionDraftServiceTest`:
  - v1 is upgraded to v2.
  - Unknown keys are rejected.
  - A deleted, inactive or foreign account/payee/category ID is blanked with a notice.
  - Amounts stay decimal strings.
  - `fromTransaction()` round-trips a standard and an investment transaction without the date.
- Feature: the existing transaction store/finalize tests stay green unchanged. One new test asserts that `TransactionCreated` fires when creating through the service.

**Acceptance**

- Given a document processed after the deploy, when its draft is read, then it has `schema_version: 2`.
- Given a draft whose account was deleted, when it is normalized, then the account key is gone and a notice names it.

---

### Phase 1: Transaction templates (R1)

**Scope:** concept "Transaction templates". **State at end of phase:** users can create, use and manage templates; featured templates show on the dashboard.

**Backend**

- `TransactionTemplate` model (`#[Fillable]`, `draft` array cast, `ModelOwnedByUserTrait`), with `TransactionTemplatePolicy` and `TransactionTemplateRequest`.
  - The request validates `name` and `is_featured`, and validates `draft` through `TransactionDraftService` in template mode: everything optional, `date` and `raw` rejected.
  - `payee_id` is **derived** server-side from the draft (`account_to_id` for a withdrawal, `account_from_id` for a deposit, otherwise null). It is not client-writable.
- `API\TransactionTemplateApiController` (`HasMiddleware`). `index`/`show` use `abilities:read`; `store`/`update`/`destroy` use `abilities:write`.
  - `GET /api/v1/transaction-templates`: `?featured=1&limit=6`; sort `use_count desc, last_used_at desc`.
  - `GET /api/v1/transaction-templates/{template}`: returns the normalized + enriched draft and `notices`.
  - `POST`, `PATCH`, `DELETE`.
- Web: `GET /transaction-templates` (list page), `GET /transaction-templates/create/{type}` and `GET /transaction-templates/{template}/edit`. Each renders `transactions/form.blade.php` with action `template`, with the transaction built by `toUnsavedTransaction()`.
- *Save as template*: add `template` to `openTransaction()`'s allowed actions. The form opens pre-filled from the transaction, like `clone`, with the date cleared. No extra endpoint.
- `TransactionRequest` accepts an optional `transaction_template_id` (owned). After commit, `TransactionCreationService` increments `use_count` and sets `last_used_at`. No origin record and no review (concept: normal manual entry).

**Frontend**

- `TransactionFormStandard.vue` / `TransactionFormInvestment.vue` get a `template` action:
  - The date field is hidden.
  - Required-field client validation is off.
  - Name and *featured* inputs appear.
  - Submit serializes only the filled fields to a v2 draft and POSTs or PATCHes the template API.
  - Stale-reference notices show as a dismissible alert above the form.
- Using a template: dispatch `initiateCreateFromDraft` with `detail.templateId`. The modal passes it on as `transaction_template_id` and focuses the first empty required field (the date for the parking example). Templates with more than 3 items open the standalone page instead, as the AI viewer does.
- Template list page (`resources/js/transaction-templates/`): a DataTable with name, payee, type, use count and last used, plus actions use / edit / delete / toggle featured.
- *Save as template* entries: the transaction show page and the transaction list action menus.
- Dashboard widget `widgets/TransactionTemplates.vue`: a card with up to 6 featured templates, one *use* button each, an empty state linking to the list page, and loading and error states. Placed in `Dashboard.vue`'s left column.

**Tests**

- Feature `TransactionTemplateApiTest`:
  - CRUD happy path.
  - Another user's template returns 403/404.
  - A `date` key in the draft returns 422.
  - `payee_id` is derived for a withdrawal and a deposit, and null for a transfer.
  - A client-sent `payee_id` is ignored.
  - Deleting the payee nulls `payee_id` and the template survives.
  - Stale notices are returned.
  - Featured filter and sort order.
- Feature: storing a transaction with `transaction_template_id` bumps `use_count` and `last_used_at`; a foreign template ID returns 422.
- Feature: `GET /transactions/{t}/template` renders for the owner and returns 403 for others.
- `ApiAbilityEnforcementTest`: data-provider rows for every new action.
- Browser (`tests/PEST/Pages/TransactionTemplates/`, group `critical`): dashboard widget → *use* → modal opens with payee, amount and category filled and focus on the date → enter the date → save → transaction exists.

**Acceptance**

- Given a transaction, when I choose *Save as template* and save, then a template exists with every field except the date.
- Given a featured template, when I click it on the dashboard, then the transaction modal opens pre-filled and I only need to enter the date and save.
- Given a template whose account was deactivated, when I use it, then the account field is empty and a notice tells me why.

---

### Phase 2: Document lifecycle and duplicate detection (R4)

**Scope:** concept "AI document lifecycle" (without auto-recording) and "Duplicate detection"; D1, D4, D6, D13, D14 (reprocess). **State at end of phase:** duplicate documents close themselves, manual entry warns about matches and can close open documents, documents can be dismissed. Nothing is auto-recorded yet.

**Backend**

- `AiDocumentStatus` gains `AutoRecorded`, `Duplicate`, `AwaitingItemization` and `Dismissed`, with labels. The last two are only reached in Phase 4 or through user action, but they are added now so the list UI and retention are done once.
  - Add `isTerminal()` and `isLinkedToTransaction()` helpers.
  - Every status write goes through a single `AiDocument::transitionTo(AiDocumentStatus)` that also sets `status_changed_at`. Replace the raw-string writes listed in §2 while touching them.
- Extraction: the D6 fields go into the `AiStepGateway` schema, `AiExtractionSchemaValidator` and the `AiPromptBuilder` main prompt. `content_hash` is computed in the three create paths (upload, email listener, Drive job).
- `TransactionOrigin` model (`morphTo origin`, `belongsTo transaction`); `Transaction::origins()` hasMany; `AiDocument::origins()` morphMany.
- `App\Services\SameEventClassifier`, which reuses `DuplicateDetectionService` for the candidate query:
  - **Candidate key:** exact amount, date within ±`duplicate_date_window_days`, same resolved payee, same account when both sides have one.
  - **Same-event signals:** equal `content_hash` or `bank_reference`; complementary kinds (`bank_notification` against `receipt`/`invoice`); `transaction_time` within `same_event_minutes` when both sides have one.
  - **One to one:** a transaction already linked (through `ai_document_id` or a `duplicate_of` origin) to a document of the same kind cannot absorb another one.
  - It returns an outcome: `exact_repeat | same_event(transaction|document) | candidate | near_match | none`.
- The processing outcome, run at the end of `ProcessDocumentService::process()` under the D13 lock:

  | Classifier result | Status | Side effect |
  | --- | --- | --- |
  | Same `content_hash` as another document of the user | `duplicate` | none, silent |
  | Same event as a committed transaction | `duplicate` | `transaction_origins` row with `duplicate_of` |
  | Same event as an open `ready_for_review` document | `ready_for_review` | Candidate shown; the merge only happens in Phase 4 |
  | Candidate without a signal, or a near match with a different amount (within `duplicate_amount_tolerance_percent`) | `ready_for_review` | Candidates listed by the existing check-duplicates UI |
  | None | `ready_for_review` | (Phase 4: the gates run here) |
- `POST /api/v1/documents/{id}/dismiss` (`abilities:write`): allowed from `ready_for_review` and `processing_failed`.
- `POST /api/v1/transactions/duplicate-check` (`abilities:read`), handled by a `DuplicateCheckRequest`.
  - Input: `config_type`, `transaction_type`, `account_id`, `payee_id`, `date`, `amount`, optional `exclude_transaction_id` (for edits).
  - Output: `{transactions: [...], documents: [...]}`. `documents` are the user's `ready_for_review` and `awaiting_itemization` documents matching the candidate key.
- `TransactionRequest` accepts an optional `close_ai_document_id` (owned, status `ready_for_review` or `awaiting_itemization`, and not the same as `ai_document_id`). After commit the document transitions to `duplicate` and a `duplicate_of` origin row is written.
- Reprocess guard extended per D14 (`dismissed` allowed; `auto_recorded` and `duplicate` blocked), in both the reprocess and the PATCH `update` paths.
- Retention per D4:
  - `CleanupOldAiDocuments` deletes `finalized, auto_recorded, duplicate, dismissed`. Everything else counts towards the reminder email.
  - Before deleting, null `origin_id` on `created` origin rows and delete the rest.
  - The `unprocessed` pseudo-filter in `AiDocumentTable.vue` becomes "every non-terminal status".
  - Update the CLAUDE.md critical rule and `.ai/docs/features/ai-document-retention/` to say "terminal" instead of "finalized".
- `UPGRADE.md`: new status values in `/api/v1/documents`, the new `document_kind` field, and the reprocess restriction.

**Frontend**

- Transaction form: once the account(s), payee, date and amount are filled, call the duplicate-check endpoint, debounced at 500 ms and re-run on change.
  - The result shows as a non-blocking alert above the save buttons. Matching transactions appear as information (date, amount, link). Matching documents each get a *close this document* toggle, which sets `close_ai_document_id`.
  - Saving is never disabled.
  - Skip the check in the `template` action and for scheduled transactions.
- AI document list and filter: the new statuses get labels and badges.
- `AiDocumentViewer.vue`: a *Dismiss* button for `ready_for_review` and `processing_failed`. For `duplicate`, a link to the transaction. *Reprocess* is hidden when it is not allowed.

**Tests**

- Unit `SameEventClassifierTest`, as a truth table with one case per row:
  - Two parking notifications, same day, different times: both stay separate.
  - Notification and receipt, same amount/payee, within 10 minutes: same event.
  - Notification and receipt with no times: same event (complementary kinds).
  - Same `bank_reference`: same event.
  - A second notification against a transaction already linked to a notification: not absorbed.
  - Amount differs by 5%: near match.
  - Date outside the window: none.
- Feature `AiDocumentLifecycleTest`:
  - Each row of the processing outcome table, with status, `status_changed_at` and origin rows asserted.
  - The same file uploaded twice ends as `duplicate` silently, with no email.
  - Dismiss works from the allowed statuses and returns 422 from others.
  - Reprocess returns 422 for `duplicate` and `auto_recorded`, and succeeds for `dismissed`.
- Feature `TransactionDuplicateCheckApiTest`: transaction and document matches, ownership isolation, `exclude_transaction_id`.
- Feature: saving with `close_ai_document_id` closes the document and writes the origin; a foreign or terminal document returns 422.
- Feature: extend `CleanupOldAiDocumentFilesCommandTest`: every terminal status is deleted; `created` origins survive with a null `origin_id`; `duplicate_of` origins are gone; non-terminal statuses trigger the reminder.
- `ApiAbilityEnforcementTest` rows for dismiss and duplicate-check.
- Browser (group `critical`): open a held/open document scenario, enter the matching transaction manually, the warning appears, close the document, save; the document shows as `duplicate`.

**Acceptance**

- Given a bank notification already recorded manually, when the same purchase's notification arrives, then it is closed as `duplicate` and linked, and the transaction is unchanged.
- Given two parking notifications on the same day at different times, when both are processed, then neither is closed as a duplicate of the other.
- Given an open grocery notification, when I enter the purchase by hand, then the form warns me and lets me close the notification on save.

---

### Phase 3: Payee profile, settings and matching (R2/R3 groundwork)

**Scope:** concept "Payees: profile, settings and aliases"; D7–D10, D15. **State at end of phase:** every payee has a stored profile, users can set policy and itemization, and the candidates page shows who *would* qualify. Nothing auto-records.

**Backend**

- `PayeeProfile` model and `App\Services\PayeeProfileService::calculate(AccountEntity $payee): PayeeProfile`, using the D8 window and D9 Wilson bound.
  - The Wilson calculation is a small pure function `wilsonLowerBound(int $successes, int $n, float $z = 1.645): float`.
  - "Typical accounts" are the account IDs covering ≥ 20% of the window.
- `App\Jobs\RecalculatePayeeProfile` (`ShouldBeUnique`, key = payee ID, queue `default`).
  - It is dispatched from `ProcessTransactionCreated`/`Updated`/`Deleted` for the standard payee side; for an update, both the old and the new payee.
  - Command `payees:recalculate-profiles {userId?}`, scheduled `dailyAt('02:30')` inside the `runs_scheduler` block. It also covers paths that fire no event (schedule auto-record, reconcile).
- Payee settings: columns on `payees` (§4), `Payee` fillable, `PayeePersistenceService` store/update, payee API request rules (`in:follow_global,always,never`, boolean).
- `App\Services\PayeeMatcher::match(string $text, User $user): ?PayeeMatch{payee, tier: exact|leading_token|similarity, score, margin}` covers concept tiers 1–4:
  - Normalization helper per D7.
  - Leading-token matching works on whole tokens, and the longest alias wins.
  - A similarity result is returned with `auto_eligible = score ≥ payee_similarity_min && margin ≥ payee_similarity_margin && strlen(alias) ≥ 4`.
- Alias uniqueness validation per D15 in the payee request (normalized, per user, payees only). Also fix `CsvParserService::loadPayeeLookup()`.
- Candidates: `GET /api/v1/payees/auto-record-candidates` (`abilities:read`). It returns four groups:
  - **qualifying:** passes the history gate and its policy is not *Never*.
  - **near:** missing *n* more transactions, or Wilson within 0.10 of the minimum.
  - **itemization_mismatch:** `multi_item_share ≥ 0.3` while *Summary sufficient*, or `≤ 0.05` while *Itemization expected*.
  - **template_candidates:** ≥ 5 transactions in the window where `amount_mode_share ≥ 0.8` and single category, and no template for the payee yet.

  Each row carries the numbers and a `reason` string.
- `AiUserSettingsRequest`, `AiUserSettingsResolver` (`DEFAULT_*`) and `AiUserSettingsResource` gain the Phase 3 settings (§4).

**Frontend**

- `PayeeForm.vue`: an *Auto-recording* select and an *Itemization* select, with help text. Alias conflict errors show at the field.
- Payee show page: a small profile card (sample size, dominant category and share, Wilson bound, amount median and range, *qualifies / missing X*).
- Auto-record candidates page (web route + Blade + Vue island under `resources/js/payee/`): four tabs or sections, each row linking to the payee and opening `PayeeForm` in edit mode. A notice at the top while `auto_record_enabled` is off: "nothing is recorded automatically yet".
- `AiBehaviorSettings.vue`: an *Auto-recording* section with the thresholds (the switch itself is added in Phase 4).
- `AiDocumentViewer.vue`: when the user changes the payee and finalizes, offer *Add "<leading words>" as alias of <payee>* (one PATCH to the payee).

**Tests**

- Unit:
  - `wilsonLowerBound` gives the D9 values to 3 decimals.
  - Normalizer: `OMV 4471 BUDAPEST` → `omv budapest`; `Példa Kft.` → `pelda`; accents are transliterated.
  - `PayeeMatcher` tiers: alias `OMV` matches `OMV 4472 DEBRECEN`; the longest alias wins; similarity below the minimum, a low margin, or an alias under 4 characters is not auto-eligible; an exact name match wins over similarity.
- Feature `PayeeProfileTest`:
  - Creating, updating and deleting a transaction queues the recalculation for the right payee(s) (use `Queue::fake()`, then run the job and assert the stored metrics).
  - The nightly command recalculates every payee of the user.
  - Only the 50 newest transactions count.
  - Schedules are excluded.
- Feature: alias conflict returns 422; the policy and itemization fields persist.
- Feature `AutoRecordCandidatesApiTest`: group membership for crafted histories (11/11 qualifies, 10/10 is near, a grocery-like payee is an itemization mismatch); ownership isolation.
- `ApiAbilityEnforcementTest` row for candidates.

**Acceptance**

- Given a payee with 11 single-item transactions in one category, when I open the candidates page, then it is listed as qualifying with the numbers behind it.
- Given a payee with 10 such transactions, then it is listed as near, missing 1 transaction.
- Given alias `OMV` on one payee, when I add `omv` to another payee, then saving fails with a validation error.

---

### Phase 4: Auto-recording (R2/R3)

**Scope:** concept "Auto-recording rules", the itemization hold, conflicts, "Transaction origin and review"; D2, D3, D11, D13, D14. **State at end of phase:** with the switch on, qualifying documents become normal transactions with an explained origin; summary-only documents for *Itemization expected* payees wait for their receipt; the user can review what was recorded.

**Backend**

- `auto_record_enabled` setting, through the request, resolver, resource and form.
- `App\Services\AutoRecord\GateEvaluator::evaluate(AiDocument $doc): GateResult{passed: bool, failed_gate: ?string, reasons: string[], hold: bool}`. Each gate is a small private method returning pass or fail with a reason. Evaluation stops at the first failure.

  | # | Gate | Passes when |
  | --- | --- | --- |
  | 0 | Switch | `ai_enabled` and `auto_record_enabled` |
  | 1 | Extraction | date, amount and payee text present; date ≤ today; amount parses to a positive decimal |
  | 2 | Payee | `PayeeMatcher` tier `exact` / `leading_token`, or `similarity` with `auto_eligible` |
  | 3 | Policy | `auto_record_policy != never` |
  | 4 | History | policy `always`, or `sample_size ≥ auto_record_min_history` and `wilson_lower ≥ auto_record_wilson_min` |
  | 5 | Account | `card_last_digits` matches exactly one active account's identifier line (D2), or the payee has exactly one template with an account |
  | 6 | Completeness | payee is *Summary sufficient*, or the document is itemized. Otherwise `hold = true` → `awaiting_itemization` (not review) |
  | 7 | Amount | within ±`auto_record_amount_tolerance_percent` of `amount_median`, or equal to one of `known_amounts` |
  | 8 | Duplicates | `SameEventClassifier` returns `none` (re-run inside the lock) |
- `App\Services\AutoRecord\AutoRecorder::record(AiDocument $doc, GateResult $r): Transaction`, run under the D13 lock inside a DB transaction:
  1. Build the transaction: the payee's single template draft if one exists (concept), otherwise one item of the full amount in the profile's dominant category. An itemized document uses its own items (the AI recommended category when `match_type = exact`; otherwise the gate fails as *Extraction: uncategorized items* and the document goes to review).
  2. Create it through `TransactionCreationService` (fires `TransactionCreated`) and set `ai_document_id`.
  3. Write the `created` origin with `decision_reason`, generated from the gate results, for example "47 of 49 past transactions in Dining (lower bound 0.89), amount within usual range, account from card ending 1234".
  4. Transition to `auto_recorded`.
- Wiring: at the "None" row of the Phase 2 outcome table, run the `GateEvaluator`.
  - All gates pass → `AutoRecorder`.
  - `hold` → `awaiting_itemization`.
  - Otherwise → `ready_for_review`, with the failed gate and reason saved in the draft (`auto_record.failed_gate`, `auto_record.reason`) for the viewer.
- Itemization hold and merge:
  - When an incoming itemized document is a same-event match with an `awaiting_itemization` document, merge them. Items come from the receipt; account, `transaction_time` and `bank_reference` come from the notification.
  - The receipt becomes the main document and the gates run on the merged draft. The notification transitions to `duplicate`; once a transaction exists, it also gets a `duplicate_of` origin pointing to it.
  - A manual entry with `close_ai_document_id` (Phase 2) closes a held document the same way.
  - Command `ai-documents:expire-itemization`, `dailyAt('03:45')`: moves `awaiting_itemization` documents older than `itemization_timeout_days` (by `status_changed_at`) to `ready_for_review` with the reason "no receipt within N days".
- Conflict: when an itemized document is a same-event match with an auto-recorded transaction whose amount, items or categories differ, write a `conflicts_with` origin, set `ready_for_review`, and leave the transaction unchanged.
- Review:
  - `GET /api/v1/transaction-origins?relation=created&reviewed=0` and `?relation=conflicts_with` (`abilities:read`).
  - `POST /api/v1/transaction-origins/{origin}/review` (`abilities:write`) sets `reviewed_at`. A bulk variant takes `ids[]`.
  - `TransactionOriginPolicy`.
  - Reconciling sets `reviewed_at` on the transaction's unreviewed `created` origins: in `TransactionApiController::reconcile()`, and in the update path when `reconciled` flips to true.
- Notifications per D14.
- `UPGRADE.md`: the auto-record setting, the origin API.

**Frontend**

- `AiBehaviorSettings.vue`: the *Record automatically* switch with an explanatory text and a link to the candidates page.
- *Auto-recorded* review page (a tab on the AI documents page, `?tab=auto-recorded`):
  - A list of unreviewed `created` origins (date, payee, amount, account, decision reason), with one-click *Confirm* and bulk confirm.
  - A *Conflicts* section with the transaction next to the document, and links to edit the transaction or dismiss the document.
- Dashboard `AiDocumentSummary.vue`: an "N auto-recorded to review" count.
- Transaction show page: an origin badge ("Recorded automatically", the reason, the reviewed state).
- `AiDocumentViewer.vue`: for `auto_recorded`, a link to the transaction and the reason; for `ready_for_review`, the failed gate and reason ("Account: no card ending matched"); for `awaiting_itemization`, "waiting for receipt until <date>".

**Tests**

- Unit `GateEvaluatorTest`: one pass and one fail case per gate.
  - *Always allow* skips only gate 4.
  - *Never* fails gate 3.
  - Switch off fails gate 0.
  - *Itemization expected* + summary sets `hold`; *Itemization expected* + itemized passes.
  - An amount 10× the median fails; an amount equal to a known amount outside ±20% passes.
- Feature `AutoRecordTest`:
  - Happy path: a document for a qualifying payee with a card-ending alias ends up with a transaction (one item, dominant category), `TransactionCreated` dispatched (`Event::fake([TransactionCreated::class])` in a separate test), monthly summary updated (non-faked run), a `created` origin with a non-empty reason, status `auto_recorded`, and no CategoryLearning row.
  - The single-template payee uses the template's account and category.
  - Each gate failure goes to `ready_for_review` with `auto_record.failed_gate` set.
  - Switch off: nothing is recorded.
- Feature `ItemizationHoldTest`: a summary for an *Itemization expected* payee is held; the receipt arrives and is merged and recorded with the notification's account; the timeout command moves a held document to review after N days but not before; a manual entry closes a held document.
- Feature `AutoRecordConflictTest`: a receipt with different items for an auto-recorded transaction writes a `conflicts_with` origin, and the transaction is unchanged (assert every column and item).
- Feature `AutoRecordRaceTest`: two same-event documents processed back to back for one purchase, with the lock held and released, give exactly one transaction, and the second document is a `duplicate`.
- Feature `TransactionOriginReviewApiTest`: list filters, confirm and bulk confirm, ownership; reconcile (endpoint and edit) sets `reviewed_at`.
- `ApiAbilityEnforcementTest` rows for the origin endpoints.
- Browser (group `critical`): the auto-recorded tab lists an origin and *Confirm* removes it from the unreviewed list.

**Acceptance**

- Given auto-recording is on and *Parking* qualifies with card ending 1234 on my *Visa* account, when a parking notification with card ending 1234 arrives, then a transaction is recorded on *Visa* with the parking category, and the review list explains why.
- Given auto-recording is off, when the same notification arrives, then it waits in review as before.
- Given a grocery store set to *Itemization expected*, when its notification arrives, then nothing is recorded and the document waits; when the receipt arrives, then one transaction is recorded with the receipt's items and the notification's account.
- Given no receipt within 14 days, then the held notification moves to review.
- Given an auto-recorded lunch, when a receipt with a different total arrives, then the transaction is unchanged and the conflict is listed for me.
- Given an unreviewed auto-recorded transaction, when I reconcile it, then it leaves the unreviewed list.

## 6. Cross-Cutting Rules

- **API abilities:** every new `API` controller declares `abilities:read|write` per action with `HasMiddleware`, and gets deny/allow rows in `tests/Feature/API/ApiAbilityEnforcementTest.php`.
- **Authorization:** a policy for each new model (`TransactionTemplate`, `TransactionOrigin`); all lookups are user-scoped.
- **Wire format:** money and quantity values in drafts, profiles and API responses are decimal strings (MoneyCast/DecimalCast).
- **Recurrence:** not touched. Templates have no schedule.
- **Tests:** new tests are Pest 5. Feature tests are preferred. Browser tests go in `tests/PEST/Pages/` for the critical flows listed per phase only. Run only the affected tests per phase, then ask before running the full suite.
- **Quality gates:** Pint, PHPStan and ESLint; rebuild assets before any UI check.
- **Docs after each phase:** extract the as-built docs into `.ai/docs/features/fast-transaction-entry/` (documentation agent). Update `.ai/docs/features/ai-document-processing/TECHNICAL.md` (statuses, schema), `.ai/docs/features/ai-document-retention/`, `.ai/docs/assets/payee/`, and the CLAUDE.md retention rule (Phase 2).

## 7. Risks and Open Items

- **R1 Threshold tuning.** All starting values are in the concept's open-items table and live in `ai_user_settings`. Without shadow mode (out of scope), the Phase 3 candidates page is the calibration tool. Ship Phase 3 before Phase 4 and look at real data.
- **R2 AI field reliability.** `document_kind`, `transaction_time` and `card_last_digits` depend on the model. A wrong kind can only cost auto-recording (a wrongly complementary pair still needs exact amount, payee and date), never alter a transaction. Prompt examples should include Hungarian bank notifications.
- **R3 Currency.** The amount gate compares in the document currency against the profile in the account currency. When the currencies differ, gate 7 fails (review), which is the safe default.
- **R4 Existing bugs found during analysis** (not in scope unless touched):
  - The AI schema uses `interest` while `TransactionType` has `interest_yield`, so such extractions fail validation (`AiStepGateway.php:245`, `AiDocumentApiController.php:587`).
  - `ProcessDocumentService` re-sets `processing` after the command has already claimed it.
- **R5 Stale schema dump.** `mysql-schema.sql` lags the migrations (§4). Regenerate it in Phase 0.

## 8. Future Directions

- Shadow mode: record gate results without recording, to calibrate the thresholds.
- Schedules as an origin source (the polymorphic origin already allows it).
- Splitting one receipt into several transactions during review.
- A widget-specific template selection and order.
- A daily digest email of auto-recorded transactions instead of per-document emails.
