# AI Document Retention — Flows

Only flows that change data, delete files, send email, or cross a permission boundary.

## F1. Set the retention period

**Actor:** verified user (session, or API token with `settings` ability). **Precondition:** not in sandbox mode. **Outcome:** `ai_user_settings.document_retention_days` is an integer 1–3650, or `NULL`.

| # | Step | Authz / check | Deny case |
|---|---|---|---|
| 1 | `AiBehaviorSettings.vue` → `PATCH /api/v1/ai/settings` | `auth:sanctum`, `verified`, `abilities:settings` | Token without `settings` → 403; unverified → 403 |
| 2 | `AiUserSettingsRequest` validates | `sometimes\|nullable\|integer\|min:1\|max:3650` | `0`, negative, `3651`, non-numeric → 422 |
| 3 | `Gate::authorize('update', $settings)` | `AiUserSettingsPolicy::isOwnItem` (settings are always resolved from the caller) | Other user's row is unreachable |
| 4 | Sandbox check | `config('yaffa.sandbox_mode')` | 403 |
| 5 | UI only: SweetAlert if new value differs from saved and `drive_keeps_imported_files` | Client-side courtesy, **not** re-enforced by the API | API callers get no warning |

**Side effects:** one row write. Nothing is deleted at this moment; the first deletion happens at the next 03:30 run or manual trigger.

## F2. Scheduled cleanup (daily)

**Actor:** scheduler container (`RUNS_SCHEDULER=true`). No user context.

1. `Schedule::command(CleanupOldAiDocumentFiles::class)->dailyAt('03:30')` — no `onOneServer()` / `withoutOverlapping()`.
2. Command selects `ai_user_settings.document_retention_days > 0` → `pluck('user_id')` → `CleanupOldAiDocuments::dispatch($id)` per user. Users with `NULL` are never dispatched.
3. Queue worker runs the job (crosses scheduler → queue boundary; the payload is only an int user id).
4. Job re-reads the setting via `AiUserSettingsResolver`; user missing or value ≤ 0 → return (**fail closed**, nothing deleted).
5. `cutoff = now() - N days`; finalized + `olderThan(cutoff)` + `user_id` → chunk(100) → F4 per document (per-document `try/catch` + `report()`).
6. Old **non-finalized** documents → counted; if > 0 → F5.

## F3. Manual "Run cleanup"

**Actor:** verified user on `/user/maintenance` (button disabled until a period is set; SweetAlert confirmation, with Drive warning when relevant).

1. `POST /api/v1/maintenance/cleanup-ai-document-old-files` — `auth:sanctum`, `verified`, `abilities:settings`.
2. `Artisan::queue('ai-documents:cleanup-old-files', ['userId' => $request->user()->id])` — scope is taken from the authenticated user, never from the request body.
3. Continues as F2 step 2 for that user only. A user without a period → nothing dispatched.

**Deny cases:** unauthenticated → 401 (`AiDocumentMaintenanceApiTest`); token without `settings` → 403 (`ApiAbilityEnforcementTest`). The confirmation dialog is UI-only; the API deletes on a single authorised POST.

## F4. Purge one document (`CleanupOldAiDocuments::purge`)

1. Collect `file_path`s from `ai_document_files`; keep only those passing `isOwnedPath()`:
   - prefix `ai_documents/{document.user_id}/`, no `.`/`..` segment, no `\` or NUL;
   - `realpath()` of the file must sit under `realpath(ai_documents/{user})`, unless it does not resolve (nothing to delete).
2. `DB::transaction`: delete `ai_documents` row (cascades files; `transactions.ai_document_id` → `NULL`), delete `received_mails` row, then delete files on the `local` disk. If the disk delete returns false → throw → **rollback**, retried next run.
3. After commit: remove `ai_documents/{user}/{id|uuid}` if it is exactly one level under the user directory and `allFiles()` is empty.

**Deny cases:** path in another user's directory, traversal, symlink escape → file left untouched; non-finalized or recently-updated document → never selected.
**Side effects:** irreversible loss of row, files, received-mail body; the linked transaction survives with `ai_document_id = NULL`; Drive file (if any) is untouched.
**Partial-failure note:** if some files deleted before a failure, rollback restores the row but not the files (finalized documents only, so no processing depends on them).

## F5. Reminder email

After F2 step 6, `Mail::to($user->email)->locale($user->language)->send(new AiDocumentsAwaitingAction(...))` — synchronous inside the job. One email per user per run. Link: `route('ai-documents.index', status=unprocessed, date_to=cutoff date)`. Repeats daily until the documents are finalized or deleted. See `emails.md`.

## F6. Manual document delete (existing; behaviour changed by the FK migration)

`DELETE /api/v1/documents/{aiDocument}` → `AiDocumentApiController::destroy` (`abilities:write`, `Authorize('delete')`). Deletes files (no path guard), the received mail, then the row. **Changed:** the linked transaction is now kept (`SET NULL`) instead of cascade-deleted. Clients or users that relied on "delete document ⇒ delete transaction" are affected (called out in `UPGRADE.md`).
