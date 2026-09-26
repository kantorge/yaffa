# AI Document Retention — Variables & Configuration

## Configuration

| Name | Used by | Scope | Source | Rotation / change | Risk |
|---|---|---|---|---|---|
| `ai_user_settings.document_retention_days` | `CleanupOldAiDocuments`, command, maintenance page | Server, per user | DB, user-editable (UI/API) | Any time; `NULL` disables | High impact when set: drives irreversible deletion. Not a secret |
| `RUNS_SCHEDULER` → `config('yaffa.runs_scheduler')` | `routes/console.php` | Server (env) | `.env` | Deploy-time | If no container has it set, nothing runs daily; if two containers have it set, jobs are dispatched twice (no `onOneServer`) → duplicate reminder emails |
| Queue connection / worker | Job (`ShouldQueue`), `Artisan::queue` | Server | `.env` / infra | Deploy-time | No worker → cleanup and reminders silently never run |
| `MAIL_*`, `mail.from.address`, `mail.from.name` | `AiDocumentsAwaitingAction` | Server | `.env` | Per mail-provider policy | Mail transport failure throws inside the job (job fails, retried per queue policy) |
| `filesystems.disks.local.root` = `storage_path('app')` | Job, upload code | Server | `config/filesystems.php` | Fixed | Path guard assumes `ai_documents/{user}` lives under this root |
| `AI_DOCUMENT_MAX_FILES_PER_SUBMISSION`, `AI_DOCUMENT_MAX_FILE_SIZE_MB`, `AI_DOCUMENT_ALLOWED_TYPES` | Upload/import validation (parent feature) | Server | `.env` | Deploy-time | Allowed types also bound what a Drive file name extension may be |

## Removed

- `AI_DOCUMENT_FILE_RETENTION_DAYS` and `config('ai-documents.local_storage_file_retention.retention_days')` — deleted in this branch (were never read by working code). Operators who set the variable can leave it; it has no effect. A stale value cannot re-enable deletion.

## Secrets

None introduced. The feature adds no keys, tokens, or credentials, and nothing is bundled client-side. The settings page receives one boolean (`aiSettingsPageMeta.drive_keeps_imported_files`) and the maintenance page receives the user's own retention value.

## Pre-go-live checklist

- [ ] Exactly one container runs the scheduler (`RUNS_SCHEDULER=true`).
- [ ] A queue worker is running and mail is configured (reminders are sent from the job).
- [ ] Storage backup exists for `storage/app/ai_documents` and the DB (deleted documents cannot be restored).
- [ ] Migrations run in a maintenance window if `transactions` is large (FK drop/re-add rebuilds the table on MySQL).
- [ ] Operators of Google Drive imports without a post-import action understand the re-import behaviour.
- [ ] No `storage/app/duskapiconf_tmp.txt` on the server (would override `config()` values).
