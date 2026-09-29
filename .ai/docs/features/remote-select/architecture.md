# Architecture: Remote Select (Tom Select migration)

Behaviour and UX are described in `SPECIFICATION.md`. This file covers only what a reviewer needs:
what changed on the trust boundary and what didn't.

## What the change is

- Frontend-only swap of the searchable dropdown library: Select2 (jQuery plugin) → Tom Select, behind
  one factory, `resources/js/shared/lib/tom-select/index.js` (`createRemoteSelect`, `setSelected`,
  `clearSelect`).
- Removal of the full-page payee create/edit form (`resources/views/payees/form.blade.php`,
  `resources/js/payee/form.js`, `CategoryListComposer`). Payees are now created and edited only in the
  payee list modal (`PayeeForm.vue`), which writes through `/api/v1/payees`.
- `ModelOwnedByUserTrait` now checks `auth()->check()` per `creating` event instead of once at boot.
- Test tooling: Pest 5 + `pest-plugin-browser` browser suite in `tests/PEST/` (CI:
  `.github/workflows/test-e2e.yml`). The Select2 Dusk helper package and its VCS composer repository
  were removed.

## Trust boundaries

| Boundary | Before | After |
|---|---|---|
| Browser → `/api/v1/{accounts,payees,categories,tags,investments,category-learning}` search | Select2 `$.ajax` GET | axios GET with `AbortController`. Same endpoints, same params (`queryString.js` keeps jQuery's `undefined`/`null` semantics) |
| Browser → payee write | Web form POST/PATCH `account-entity.store`/`update` **or** API | API only (`POST /api/v1/payees`, `PATCH /api/v1/payees/{id}`). The web routes return 404 for `config_type = payee` |
| Browser → payee create/edit page | Blade view | `302` to `account-entity.index?type=payee&create=1` / `&edit={id}`, **after** the existing `can:create` / `can:update` middleware |
| API data → dropdown HTML | Select2 templates | Tom Select default templates (escape labels). The single custom template (investment option, `TransactionFormInvestment.vue`) wraps every field in `escape` |

No route, policy, ability, Form Request rule, migration or config value was added. The backend
diff is limited to the payee redirects/404s, the trait fix and the removed view composer.

## Known risks / assumptions

- **Client-only rule: a category can't be both preferred and excluded for a payee.**
  `PayeeForm.vue` enforces it with `filterResults`. `AccountEntityRequest` lines 126/134 try to
  enforce it with `Rule::notIn('config.not_preferred')`, which compares against the literal string,
  not the field, so the server accepts overlaps. It predates this branch. The impact is limited to
  the user's own data.
- **Custom templates are an XSS sink.** `renderOption`/`renderItem` return HTML. Today there is one,
  and it escapes. Any new template must too (guardrail in `.ai/agents/frontend.agent.md`).
- **No query cache, by design (spec D4).** Every open and every debounced keystroke hits the server.
  Select2 behaved the same here (`cache: true` only dropped jQuery's cache-buster; the API sends no
  cache headers), so load is unchanged.
- **`ModelOwnedByUserTrait`:** `user_id` defaults to the authenticated user only when the caller left
  it null. Unauthenticated creates (jobs, seeders) still need an explicit `user_id`, as before.

## Related Documents

- `SPECIFICATION.md`: behaviour, inputs/outputs, edge cases
- `flows.md`, `permissions.md`, `variables.md`, `tests.md`
- `.ai/docs/specifications/tom-select-migration/`: design spec, widget inventory, test plan
- `.ai/docs/features/api-access-and-2fa/permissions.md`: token abilities on the endpoints used here

No emails, scheduled work, public/SEO routes or embedded automation are involved: no `emails.md`,
`cron.md`, `seo.md` or `automation.md`.
