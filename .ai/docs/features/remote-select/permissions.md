# Permissions: Remote Select

No new role, ability, policy or gate. The project-wide model (session or Sanctum token, `abilities:*`,
per-user ownership) is in `.ai/docs/features/api-access-and-2fa/permissions.md`.

## Endpoints this feature calls or changed

| Resource / route | Operation | Gate | Ownership enforced by | Changed here? |
|---|---|---|---|---|
| `GET /api/v1/{accounts,accounts/investment,payees,categories,tags,investments,category-learning}` | search | `auth:sanctum`, `verified`, `abilities:read` | user-scoped query in the controller | No |
| `GET /api/v1/payees/{id}` | view | `abilities:read` | `#[Authorize('view')]` → `AccountEntityPolicy` | No (now the only way the edit modal loads a payee) |
| `POST /api/v1/payees` | create | `abilities:write` | `AccountEntityRequest` exists-rules scoped to the user's categories | No |
| `PATCH /api/v1/payees/{id}` | update | `abilities:write` | `#[Authorize('update')]` + the same request rules | No |
| `GET /account-entity/create?type=payee` | → redirect | `can:create,AccountEntity` | n/a | **Yes**: redirects to the list modal |
| `GET /account-entity/{id}/edit` (payee) | → redirect | `can:update,account_entity` (runs before the redirect) | policy | **Yes**: redirects to the list modal |
| `POST /account-entity`, `PATCH /account-entity/{id}` with `config_type=payee` | write | `can:create` / `can:update` | policy | **Yes**: 404, no write |

## Code-enforced rules (no DB-level row security)

- Every search query is scoped to the authenticated user in the controller. MySQL has no row-level
  security.
- `ModelOwnedByUserTrait` fills `user_id` from the authenticated user only when it's null. It never
  overrides an explicit value.
- Client-side exclusions (`filterResults`: the merge source can't be the target, preferred vs
  excluded category) are UX only. Merge is enforced server-side. Preferred vs excluded is currently
  **not** (see `architecture.md`).
