# Flows: Remote Select

Only the flows where this change touches authorization, data integrity or side effects.

## Flow 1: Search a record in a dropdown

Actor: authenticated user (session). Precondition: a page with a remote select is open.

1. Open or type (150 ms debounce) → the previous request is aborted and its options dropped.
2. Browser → `GET /api/v1/<entity>?q=…&<form-state params>`. Crosses browser → server.
   Authz: `auth:sanctum` + `verified` + `abilities:read` (a no-op for session requests). Results are
   scoped with `$request->user()->…` or `where('user_id', …)` in each controller.
   Deny case: an unauthenticated request gets 401. Another user's records never appear, because
   every query is user-scoped (not per-row policy checks).
3. The response is mapped and rendered. API strings reach the DOM through Tom Select templates
   (escaped).
4. Selection → `onSelect`/`onChange` callbacks update Vue state or the underlying `<select>`. No
   write happens until the form is submitted.

Side effects: none (read-only GET).

## Flow 2: Create or edit a payee

Actor: authenticated user. Two entry points:

- **Old URL** `GET /account-entity/create?type=payee` or `GET /account-entity/{id}/edit`
  1. Middleware `can:create,AccountEntity` / `can:update,account_entity` runs first.
     Deny case: another user's payee id → 403 (pinned by `PayeeFormRedirectTest`).
  2. `302` → `/account-entity?type=payee&create=1` or `&edit={id}`.
  3. `payee/index.js` reads the param, drops it with `history.replaceState`, and opens the modal.
     The `edit` value is coerced to a positive number.
  4. Edit: `GET /api/v1/payees/{id}`, `#[Authorize('view', 'accountEntity')]`. Deny: 403 for a
     foreign id.
- **Modal save**
  1. `POST /api/v1/payees` or `PATCH /api/v1/payees/{id}` (`abilities:write`; update also has
     `#[Authorize('update', 'accountEntity')]`).
  2. `AccountEntityRequest`: `config.category_id`, `config.preferred.*` and `config.not_preferred.*`
     must be the user's own categories. The preferred ∩ not-preferred rule is **not** enforced
     server-side (see `architecture.md`, Known risks).
  3. `PayeePersistenceService` writes `account_entities`, `payees` and the category-preference pivot.

The legacy web write routes (`POST /account-entity`, `PATCH /account-entity/{id}` with
`config_type = payee`) return 404 and write nothing.

## Flow 3: Transaction entry

Same as Flow 1 for account, payee, category, tag and investment. On a transaction type change, the
form destroys its account/payee selects (aborting any pending request) and rebuilds them against the
new endpoint. Tags may be created inline: the typed text becomes the option value, and the existing
transaction API handles it on save exactly as before (unchanged backend).
