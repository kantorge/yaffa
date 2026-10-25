# Transaction templates (Fast Transaction Entry, Phase 1)

As built. Design intent: [specification](../../specifications/fast-transaction-entry/specification.md), Phase 1.

## What it is

A template is a saved, partial transaction **without a date**. Using one opens the normal transaction form pre-filled, so the user only fills in what is missing (usually the date) and saves. The result is a normal manual transaction; nothing is recorded automatically and no origin or review record is written.

## Data

`transaction_templates`: `user_id` (cascade), `name` (unique per user), `payee_id` (null on payee delete), `draft` (JSON, schema v2 without `date`/`raw`), `is_featured`, `use_count`, `last_used_at`.

- `payee_id` is **derived** from the draft by `TransactionDraftService::payeeIdFromDraft()` (`config.account_to_id` of a withdrawal, `config.account_from_id` of a deposit, only if that entity is the user's payee). It is not fillable and a client-sent value is ignored.
- `draft` is always stored normalized: stale references are removed on save, and again on read (`GET .../{template}` returns the cleaned and enriched draft plus `notices`), because an account can be deactivated after the template was saved.

## Backend

| Piece | Where |
| --- | --- |
| Model, policy (owner only), factory | `TransactionTemplate`, `TransactionTemplatePolicy`, `TransactionTemplateFactory` |
| Validation | `TransactionTemplateRequest` (name, `is_featured`, `draft.date`/`draft.raw` prohibited); structure and references by `TransactionDraftService::normalize()` |
| API | `API\TransactionTemplateApiController`: `index` (`featured`, `limit`; most used first), `show`, `store`, `update`, `destroy`. `read` ability for index/show, `write` for the rest |
| Web | `TransactionTemplateController`: list page, create page (`/transaction-templates/create/{type}`), edit page. *Save as template* is `transaction.open` with action `template` |
| Usage statistics | `TransactionRequest` accepts `transaction_template_id` (owned); `TransactionCreationService::create()` calls `recordUse()` after commit |
| Draft display data | `TransactionDraftService::enrich()` also adds `category_full_name` and `tags` to items; `buildUnsavedItems()` attaches tags |

## Frontend

- `TransactionFormStandard/Investment.vue` get an action `template` through the `transaction-templates/templateForm.js` mixin: no date, reconciled, schedule or *action after saving*; a `TemplateFields.vue` card (name, featured, stale-reference notices); submit serializes **only the filled fields** to a v2 draft and POSTs/PATCHes the template API. There is no required-field client validation to switch off: the transaction form relies on server validation.
- **Using a template:** `transaction-templates/useTemplate.js` loads the template and dispatches `initiateCreateFromDraft` with `detail.templateId`. The modal passes it on as `transaction_template_id` and focuses the first empty required field. Templates with more than 3 items, and every *use* from the list page (which has no modal), POST to `transactions.createFromDraft` instead.
- Dashboard widget `widgets/TransactionTemplates.vue` (up to 6 featured), list page `resources/js/transaction-templates/index.js`, *Save as template* in `ActionButtonBar.vue` and the shared `TransactionTable` action buttons.

## Rules worth remembering

- A template never stores a date, schedule or reconciled flag, and AI-only draft keys (`raw`) are rejected.
- Tags typed in as new text cannot be stored in a template (only existing tag IDs).
- Template names are unique per user (422 otherwise).
- Tests: `tests/Feature/API/TransactionTemplateApiTest.php`, rows in `ApiAbilityEnforcementTest`, browser: `tests/PEST/Pages/TransactionTemplates/`.
