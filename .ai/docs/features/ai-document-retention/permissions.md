# AI Document Retention — Permissions

## Model

Single-tenant-per-user data. There are no admin/role tiers for this feature: every actor is the **owner** of the data. Scope is derived from the authenticated user (session or Sanctum token) for HTTP paths, and from an explicit `userId` argument (integer) for the command/job. Row ownership is code-enforced (`user_id` filters and policies); the database only enforces referential integrity, not tenancy.

Sanctum token abilities apply (see [`../api-access-and-2fa/permissions.md`](../api-access-and-2fa/permissions.md)). Session requests always pass ability checks.

## Matrix

| Resource · operation | Owner (session) | Token `read` | Token `write` | Token `settings` | Other user | Unauthenticated |
|---|---|---|---|---|---|---|
| Read retention setting (`GET /api/v1/ai/settings`) | ✅ | ❌ | ❌ | ✅ | ❌ (own row only) | ❌ |
| Change retention setting (`PATCH /api/v1/ai/settings`) | ✅ (not in sandbox) | ❌ | ❌ | ✅ | ❌ | ❌ |
| Trigger cleanup (`POST /api/v1/maintenance/cleanup-ai-document-old-files`) | ✅ (own docs only) | ❌ | ❌ | ✅ | ❌ | ❌ |
| Manual delete of a document (`DELETE /api/v1/documents/{aiDocument}`) | ✅ | ❌ | ✅ | ❌ | ❌ (`AiDocumentPolicy`) | ❌ |
| Settings page / maintenance page (`/user/ai-settings`, `/user/maintenance`) | ✅ (`web`, `auth`, `verified`) | n/a | n/a | n/a | n/a | redirect to login |
| Scheduled cleanup | system (scheduler + queue worker) — no principal | | | | | |

`verified` (email-verified) is required on all API endpoints above.

## Where scope is derived

| Path | Source of user id |
|---|---|
| Settings API | `$request->user()`; `getOrCreateForUser($user)` — cannot address another user's row |
| Maintenance endpoint | `$request->user()->id` passed to Artisan; request body is ignored |
| Command `userId` argument | CLI/operator input; validated to be an existing user; only reachable by an operator (or by the endpoint above with the caller's own id) |
| Job | Constructor `int $userId`; all queries `where user_id`; files must lie under `ai_documents/{userId}/` |

## Code-enforced vs. DB-enforced

| Rule | Enforced by |
|---|---|
| Only own documents are deleted | `where('user_id', …)` in the job; no DB-level tenancy |
| Only finalized documents are deleted | `where('status', 'finalized')` in the job |
| Only files under the owner's directory are deleted | `isOwnedPath()` in the job |
| Transaction survives document deletion | DB FK `ON DELETE SET NULL` |
| Files/rows deleted together | DB cascade (`ai_document_files`), explicit delete (`received_mails`), DB transaction |
| Token `settings` ability on settings + maintenance | `abilities:settings` middleware; pinned in `ApiAbilityEnforcementTest` |

## Documented-but-not-enforced / gaps

- No ability distinction for the destructive part: a token with `settings` can set `document_retention_days = 1` and trigger deletion of all finalized documents older than a day. There is no re-authentication or second confirmation on the API path.
- The UI Drive-duplicate warning is not enforced server-side.
- Manual delete uses `abilities:write`; retention setup uses `abilities:settings` — the two ways to lose the same data have different ability requirements.
