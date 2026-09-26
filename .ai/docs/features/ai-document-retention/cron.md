# AI Document Retention — Scheduled Work

## Inventory

| Job | Schedule | Entry | Executes | Secrets | Limits | Retry |
|---|---|---|---|---|---|---|
| Retention cleanup | daily 03:30 (app timezone), only when `yaffa.runs_scheduler` is true | `routes/console.php` → `ai-documents:cleanup-old-files` | Command dispatches one `CleanupOldAiDocuments` job per user with `document_retention_days > 0` | none | Chunks of 100 documents; one job per user; no explicit `$tries`/`$timeout`/`$backoff` on the job (queue defaults apply) | Purge errors are caught per document and `report()`ed; the document is retried on the next daily run. A mail failure fails the job (queue default retry) |
| Manual trigger | on demand | `POST /api/v1/maintenance/cleanup-ai-document-old-files` | `Artisan::queue(...)` with the caller's id | none | One user | as above |

## Idempotency

- Deletion is idempotent: already-deleted documents are not selected; a purge rolled back by a disk error is reselected next run.
- Reminder email is **not** idempotent: every run re-sends while old unprocessed documents exist (by design: "daily until then"). Two runs on the same day (manual trigger + schedule, or two scheduler containers) send two emails. No `ShouldBeUnique`, no `onOneServer()`, no sent-at marker.
- The job is safe against a setting change between dispatch and execution: it re-resolves `document_retention_days` and exits when unset.

## How internal calls authenticate

No HTTP is involved between scheduler, command, and job; the trust boundary is the operator's control of the scheduler/queue infrastructure. The only HTTP entry (manual trigger) is authenticated and ability-gated (see `permissions.md`) and cannot address another user.

## Where to see last runs

- No dedicated run log or metrics. Command output (`AI document cleanup dispatched for N user(s).`) goes to the scheduler's stdout; job failures land in `failed_jobs`; per-document purge failures are only in the application log via `report($e)`.
- **Gap:** there is no record of *what was deleted* (no audit trail). The user is not notified about deletions, only about kept documents.
