# AI Document Retention — Architecture

Parent feature: [AI Document Processing](../ai-document-processing/overview.md) (implementation detail: `TECHNICAL.md`, "File Storage & Retention").

## What it is

An **opt-in, per-user** policy that irreversibly deletes old **finalized** AI documents (row, stored files, linked received email) after `ai_user_settings.document_retention_days` days. The transaction created from a document is kept. Old documents in any other status are never deleted; the owner gets a daily reminder email instead.

Default is `NULL` = keep forever. There is no env var and no global default (the former `AI_DOCUMENT_FILE_RETENTION_DAYS` was never read and is removed).

## Components

| Piece | File | Role |
|---|---|---|
| Setting | `ai_user_settings.document_retention_days` (unsigned smallint, nullable) | Per-user policy; validated `nullable\|integer\|min:1\|max:3650` in `AiUserSettingsRequest` |
| Resolver | `App\Services\AiUserSettingsResolver` | Returns the resolved value (`null` when unset) |
| Schedule | `routes/console.php` | `ai-documents:cleanup-old-files` daily 03:30, only where `yaffa.runs_scheduler` |
| Command | `App\Console\Commands\CleanupOldAiDocumentFiles` | Finds users with `document_retention_days > 0`, dispatches one job per user; optional `userId` scope |
| Job | `App\Jobs\CleanupOldAiDocuments` (queued) | Purges finalized old documents; counts old unprocessed ones; sends reminder |
| Path guard | `CleanupOldAiDocuments::isOwnedPath()` | Only paths inside `ai_documents/{userId}/`, without traversal/backslash/NUL, not escaping through a symlink, may be deleted |
| Scope | `AiDocument::olderThan()` | `created_at < cutoff AND updated_at < cutoff` |
| Mail | `App\Mail\AiDocumentsAwaitingAction` | Reminder about old unprocessed documents (see `emails.md`) |
| Manual trigger | `POST /api/v1/maintenance/cleanup-ai-document-old-files` → `AiDocumentApiController::cleanupOldFiles` | `Artisan::queue()` the same command scoped to the caller |
| UI | `AiBehaviorSettings.vue` ("Document Retention"), `user/maintenance.blade.php`, `AiDocumentFilters.vue` / `AiDocumentTable.vue` (`unprocessed` pseudo-status) | Set, trigger, review |

## Data changes

- `transactions.ai_document_id` FK changed from `ON DELETE CASCADE` to `ON DELETE SET NULL` (migration `2026_09_19_000001`). Applies to **every** document deletion path, including the manual delete action. Before this branch, deleting a finalized document deleted its transaction.
- `ai_document_files.file_path = '1'` rows (text-input bug: `Storage::put()` bool stored as path) repaired by `2026_09_19_000002` (MySQL `UPDATE … JOIN … CONCAT`; `down()` is a no-op).
- `ai_user_settings.document_retention_days` added by `2026_09_19_000003`.
- Cascades relied on: `ai_documents → ai_document_files` (cascade), `received_mails → ai_documents` (cascade; the job deletes the document first, then the mail).

## Trust boundaries

| Boundary | Crossing | Control |
|---|---|---|
| Browser / API token → server | Set retention, trigger cleanup | `auth:sanctum` + `verified` + `abilities:settings` on both endpoints; `AiUserSettingsPolicy` (own row); settings update blocked in sandbox mode |
| Scheduler / queue → job | No request, no `Auth` user | Job takes an explicit `userId`; every query filters `where user_id`; job re-resolves the setting so a cleared setting makes a queued job a no-op |
| Job → local disk | Deletes paths read from the DB | `isOwnedPath()` (see above). DB `file_path` is treated as untrusted |
| Job → mail provider | One reminder per user per run | Recipient is the owner's own `users.email`; no user-authored content in the body |
| Google Drive → `file_path` (upstream) | Drive file *names* are attacker-influenced (anyone with write access to the monitored folder) | `ProcessGoogleDriveConfigJob::safeFileName()` keeps only the last path segment and strips control characters; the purge path guard remains a second layer for rows imported before that fix |

## Known risks / assumptions

1. **Deletion is irreversible** and there is no soft-delete or undo. Mitigation: opt-in, min 1 day, UPGRADE.md tells users to back up first. The API `PATCH` has no confirmation step; only the UI asks (and only when Drive files would be re-importable).
2. **Drive re-import.** Deleting a Drive-sourced document removes its `google_drive_file_id` unique key, so a manual full sync can re-import files left in the monitored folder (`User::googleDriveKeepsImportedFiles()` drives a UI warning; scheduled runs cannot warn).
3. **Daily reminder has no cap or back-off** (`CleanupOldAiDocuments::handle()`); a user who never acts gets one email per day indefinitely. Documented as intended in the mail text.
4. **Orphaned files (legacy rows only).** Rows imported before Drive names were sanitized may hold a path with `\` or extra segments. The purge skips files failing `isOwnedPath()` while deleting the DB row, leaving an untracked file (and empty nested directories, since only `ai_documents/{user}/{id|uuid}` is removed). New imports cannot produce such paths.
5. **`updated_at` semantics.** "Old" needs both timestamps older than the cutoff; anything that touches the row (reprocess) resets the clock.
6. The manual delete action (`AiDocumentApiController::destroy`) does **not** use the path guard and deletes files before the DB row; retention purge is stricter than manual delete.

## Skipped conditional docs

- `seo.md` — no public or bot-facing routes are added.
- `automation.md` — the cleanup is a deterministic job: no LLM, agent, tool-calling or webhook. (AI extraction itself is covered by the parent feature's docs.)

## Related Documents

- [flows.md](flows.md) · [permissions.md](permissions.md) · [variables.md](variables.md) · [cron.md](cron.md) · [emails.md](emails.md) · [tests.md](tests.md)
- Parent: [`../ai-document-processing/TECHNICAL.md`](../ai-document-processing/TECHNICAL.md), [`../api-access-and-2fa/permissions.md`](../api-access-and-2fa/permissions.md) (token abilities)
- `UPGRADE.md` — "Automatic Cleanup of Old AI Documents"
