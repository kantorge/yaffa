# QIF/CSV Import — Permissions

## Enforcement Model

This app has no database row-level security (RLS) — it's Laravel + MySQL with **all access control enforced in application code**: Laravel Policies (`Illuminate\Auth\Access\Gate`), Form Request validation closures, and inline controller/service checks. Every check documented below is a PHP-level check that runs on every request; there is no equivalent DB-level constraint backing any of them (MySQL grants are undifferentiated — the app's DB user can read/write any row).

There is a single role in this feature's scope: **authenticated + email-verified user** (`auth:sanctum` + `verified` middleware on both API controllers). There is no admin/staff role that can manage system profiles through the UI — the only way to add/change a system profile is to edit `SystemFileImportProfileRegistry` in code and redeploy (see `flows.md` § f).

## Resource × Operation × Role Matrix

### `FileImportProfile`

| Operation | System profile (`type=system`) | Own user profile (`type=user`, `user_id = me`) | Another user's profile (`type=user`, `user_id ≠ me`) | Enforced via |
|---|---|---|---|---|
| List (`GET /imports/file-profiles`) | Visible to all authenticated users | Visible | Not visible (excluded by query scope) | `FileImportProfilePolicy::viewAny` (always `true`) + `FileImportProfile::selectableForUser()` scope (`app/Models/FileImportProfile.php:79`) — the scope is what actually filters rows, `viewAny` is a coarse "logged in" gate |
| View single (resolved during parse) | Allowed | Allowed | Denied (403) | `FileImportProfilePolicy::view` = `isSystem() \|\| isUserOwnedBy($user)` (`FileImportProfilePolicy.php:15`) |
| Create (`POST /imports/file-profiles`) | N/A — clients cannot create `type=system` (request field `'type' => ['prohibited']`) | Allowed | N/A | `FileImportProfilePolicy::create` (always `true`) + `FileImportProfileRequest` field-level `prohibited` rules + controller hard-sets `type='user'`, `user_id=$user->id` (`FileImportProfileApiController.php:75-76`) |
| Update (`PATCH /imports/file-profiles/{id}`) | **Denied (403)** — no `user_id` can ever satisfy `isUserOwnedBy` for a `null`-owner system row | Allowed | Denied (403) | `FileImportProfilePolicy::update` = `isUserOwnedBy($user)` (`FileImportProfilePolicy.php:25`) |
| Delete (`DELETE /imports/file-profiles/{id}`) | **Denied (403)** | Allowed, unless referenced by ≥1 account's `preferred_file_import_profile_id` (then `422`, not 403) | Denied (403) | `FileImportProfilePolicy::delete` = `isUserOwnedBy($user)` (line 30) + inline `accountEntities()->exists()` guard in controller (`FileImportProfileApiController.php:96`) |
| Use to parse a file (`POST /imports/parse`, `file_import_profile_id`) | Allowed if `file_type` matches `source_type` | Allowed if owned and `file_type` matches | Denied (403) | Double-enforced: `ImportParseRequest`'s validation closure (`selectableForUser`, `ImportParseRequest.php:54`) **and** `Gate::authorize('view', $profile)` re-run in the controller (`ImportApiController.php:126,144,156`) |

### `AccountEntity.preferred_file_import_profile_id`

| Operation | Rule | Enforced via |
|---|---|---|
| Set/change via account create/edit web form | Value must resolve via `FileImportProfile::selectableForUser($user)` (i.e. a system profile, or a `type=user` profile owned by the editing user) | `AccountEntityRequest` validation closure (`app/Http/Requests/AccountEntityRequest.php:42-60`) — **note this is the only write path**; there is no separate API endpoint for this field (confirmed: no other controller references `preferred_file_import_profile_id`) |
| Read via account list / import profile UI | Only the current user's own accounts are ever shown alongside a profile, even for a shared system profile | `FileImportProfileApiController::index`'s eager load explicitly filters `accountEntities` to `where('user_id', $user->id)` (`FileImportProfileApiController.php:38-40`) — this matters because a system profile can be the `preferred_file_import_profile_id` for many different users' accounts; without this filter a user could see other users' account names in the profile's affected-accounts list |
| Auto-select in the import UI (`ImportPage.vue:autoSelectProfile`) | Client-side convenience only, reads `account.preferred_file_import_profile_id` from the already-authorized `/api/v1/accounts` response | No new authz surface — relies on the existing accounts endpoint's own user-scoping (out of scope of this feature) |

### `ImportApiController::parse` (the account being imported into)

| Operation | Rule | Enforced via |
|---|---|---|
| Parse a file into account `X` | Caller must own account `X` (`user_id` match) **and** `X` must be `config_type='account'` (not a payee) | `ImportPolicy::parse` (`app/Policies/ImportPolicy.php:10`), invoked via `Gate::authorize('import.parse', $accountEntity)` — this is the only place ownership of the *target account* is checked; profile ownership is checked separately (above) |

### AI profile suggestion (`POST /imports/file-profiles/suggest`)

| Operation | Rule | Enforced via |
|---|---|---|
| Use AI suggestion | Requires the caller to have their own `AiProviderConfig` row; the config is looked up strictly by `user_id = $user->id`, never a shared/fallback config | `FileImportProfileApiController::suggest` (line 111) |
| Optional `account_id` context | If provided, must be viewable by the caller | `Gate::authorize('view', $account)` (line 131) — reuses the account policy, not a new one; if the account isn't viewable this field is silently ignored rather than the whole request being rejected (worth confirming is intentional — the code doesn't `abort` here, it just skips adding context) |

## Enrichment Read Scoping (not "permissions" in the CRUD sense, but a data-isolation boundary)

Every read performed during draft enrichment is scoped to the requesting user, with no cross-user data ever entering another user's draft response:

- Payee matching: `$user->payees()` (`ImportNormalizationService.php:94`)
- Duplicate detection: `$user->transactions()->whereBetween('date', ...)` (`ImportDuplicateDetectionService.php:119`)
- Scheduled-transaction matching: `$user->transactions()->where('schedule', true)->whereHas('transactionSchedule', ...)` (`ImportDuplicateDetectionService::loadScheduleWindow()`) — same `$user->transactions()` owner-scoped relation as duplicate detection, additionally filtered to schedule-owning rows with an active `TransactionSchedule` whose `next_date` falls in the window
- Related AI documents: `AiDocument::where('user_id', $user->id)` (`ImportNormalizationService.php:305`)

None of these use a Policy class — they're inline `Eloquent` relationship/query scoping in the service layer. This is consistent with the rest of the codebase's approach to "list my own X" endpoints (see `.ai/docs/assets/account/overview.md` for the same `user_id`-scoping pattern elsewhere), but it means a regression here (e.g. someone changing `$user->transactions()` to `Transaction::query()`) would not be caught by a Policy test — only by a Feature test that asserts cross-user isolation.

## Summary: Can a user mutate a system profile?

**No — and it's enforced server-side, not just hidden in the UI.** `FileImportProfilePolicy::update` and `::delete` both gate on `isUserOwnedBy($user)`, which is defined as `type === 'user' && user_id === $user->id` (`FileImportProfile.php:95-98`). A system profile always has `type='system'` and `user_id=null`, so `isUserOwnedBy` is unconditionally `false` for it — no user id, admin flag, or role can make it `true` through this code path. The UI additionally never renders edit/delete controls for system profiles (`FileImportProfileManager.vue` filters `userProfiles` separately from system ones for the action column), but that's a UX nicety on top of the server-side deny, not the actual boundary.
