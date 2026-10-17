# Auto-recording (Fast Transaction Entry, Phase 4)

As built. Design intent: [specification](../../specifications/fast-transaction-entry/specification.md), Phase 4, and D2, D3, D11, D13, D14. Off by default: nothing is recorded unless the user turns on `ai_user_settings.auto_record_enabled` (and `ai_enabled` is on).

## Flow

`ProcessDocumentService::decideOutcome()` runs at the end of `process()` under `Cache::lock("ai-decide:{userId}")` and a DB transaction. It classifies the document with `SameEventClassifier`, then:

| Outcome | Result |
| --- | --- |
| `exact_repeat` | `duplicate`, silent |
| `same_event_transaction` | `duplicate` + `duplicate_of` origin — **unless** the document is itemized, the transaction has a `created` origin (auto-recorded) and its items differ: then a `conflicts_with` origin is written, the document goes to `ready_for_review`, the transaction is left unchanged |
| `same_event_document`, the document is itemized and the other one is `awaiting_itemization` | **merge** (below) |
| `none` | the gates run (`runGates()`) |
| anything else | `ready_for_review` |

## Gates

`App\Services\AutoRecord\GateEvaluator::evaluate()` returns a `GateResult` (`passed`, `failedGate`, `reasons`, `hold`, plus the resolved payee, account, profile, template and the items to record). Evaluation stops at the first failure.

| # | `failedGate` | Passes when |
| --- | --- | --- |
| 0 | `switch` | `ai_enabled` and `auto_record_enabled` |
| 1 | `extraction` | standard withdrawal/deposit with date (not in the future), positive amount and payee text (`raw.payee`) |
| 2 | `payee` | `PayeeMatcher` tier exact/leading token, or an auto-eligible similarity match |
| 3 | `policy` | `auto_record_policy != never` |
| 4 | `history` | `PayeeProfileService::qualification()` (policy `always` skips it); also fails when there is no active category to record under |
| 5 | `account` | `raw.card_last_digits` equals the digits of exactly one active account's alias line (all digits, optional `*`), else the payee's single template of that type with an account |
| 6 | `completeness` | payee is *Summary sufficient*, or the document is itemized. *Itemization expected* + summary sets `hold`. An itemized document also needs every item in an exact category (`match_type = exact`) and items summing to the total, else `extraction` |
| 7 | `amount` | within `auto_record_amount_tolerance_percent` of the median, or equal to a known amount; fails on a currency mismatch with the account |
| 8 | `duplicates` | `SameEventClassifier` returns `none`, keyed on the payee and account the gates resolved (the draft may not carry them) |

## Recording

`AutoRecorder::record()` builds the validated payload and calls `TransactionCreationService::create('standard', …)` (so `TransactionCreated` fires), with `ai_document_id` set, then writes the `created` origin (`decision_reason` generated from the gate reasons) and transitions the document to `auto_recorded`. Items: the document's own for an itemized one, else one item of the full amount in the payee's single template's category, or the profile's dominant category. No CategoryLearning is written.

On a failed gate the document goes to `ready_for_review` with `processed_transaction_data.auto_record = {failed_gate, reason}` (not saved for `switch`, which would be noise). On `hold` it becomes `awaiting_itemization` with `auto_record.hold_until`.

## Itemization hold and merge

- A receipt/invoice that is a same-event match with an `awaiting_itemization` document takes over: the account side, `card_last_digits`, `transaction_time` and `bank_reference` come from the notification, the notification is closed as `duplicate` first (so gate 8 does not see it), the gates run on the receipt, and when a transaction results the notification gets a `duplicate_of` origin pointing to it.
- A manual entry with `close_ai_document_id` closes a held document too (Phase 2).
- `ai-documents:expire-itemization` (daily 03:45) moves documents held longer than `itemization_timeout_days` (by `status_changed_at`) to `ready_for_review` with the reason "No receipt within N days.".

## Review

- `GET /api/v1/transaction-origins` (`read`), `POST /api/v1/transaction-origins/{origin}/review` and `POST /api/v1/transaction-origins/review` (`write`); `TransactionOriginPolicy`.
- `Transaction::booted()` marks the unreviewed `created` origins reviewed whenever `reconciled` flips to true (covers the reconcile endpoint and edits).
- UI: AI documents page, "Review auto-recorded transactions" / "Review conflicts" actions (`?review=auto-recorded|conflicts`, `AiDocumentTable.vue`: status badges, row context-menu confirm / resolve conflict, "Confirm all shown"); dashboard `AiDocumentSummary` count; origin badge on the transaction show page; `AiDocumentViewer` note (reason / failed gate / hold date); settings switch in `AiBehaviorSettings.vue`.
- `SendAiDocumentProcessedNotification` sends the "recorded automatically" mail variant for `auto_recorded`.

## Tests

`tests/Feature/FastTransactionEntry/`: `GateEvaluatorTest` (one case per gate), `AutoRecordFlowTest` (record, event, switch off, failed gate, hold + merge, timeout, conflict, duplicate receipt, back-to-back purchase, mail), `TransactionOriginReviewApiTest`; `ApiAbilityEnforcementTest` rows; browser: `tests/PEST/Pages/AiDocuments/ConfirmAutoRecordedTest.php` (group `critical`). Shared helper: `tests/Concerns/ProcessesAiDocuments.php`.

## Known gaps

- A real concurrent race is not tested (the lock is tested only sequentially); the lock itself wraps classify → decide → record.
- Gate reasons are stored in the language active at processing time.
- `mysql-schema.sql` is still not regenerated.
