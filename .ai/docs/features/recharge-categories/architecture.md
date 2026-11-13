# Recharge categories (as built)

Purchases made on behalf of someone else, where reimbursement is expected, are booked to a
category that requires a tag (e.g. a claim number). The tag ties the spend to its reimbursement.
Design decisions: [`specifications/recharge-categories/spec.md`](../../specifications/recharge-categories/spec.md).

## User settings (opt-in)

- `users.recharge_tags_enabled` (bool, default `false`): the "Recharge tags" switch in the user settings.
  While off: the Reports menu links and the category form's tag-requirement field are not rendered,
  the category list column/detail badge are hidden, the transaction form does no tag check,
  the pages and the deactivate post return 404, and the summary endpoint returns `{enabled:false}`.
  Stored `tag_requirement` values are kept (the field is `sometimes` in `CategoryRequest`).
- `users.recharge_backstop_date` (date, nullable): "Recharge backstop date". Untagged items dated before
  it are not listed; tags whose latest item is before it are not listed. A tag with activity on or after
  the date keeps its **full** balance (items before the date included), so a claim spent before and
  reimbursed after the date shows as settled. Both pages show a note while a backstop is set.
- Both are optional in `UserRequest` (`sometimes`) and exposed in `window.YAFFA.userSettings` and the
  settings API response.

## Data

- `categories.tag_requirement` (string, default `none`), cast to `App\Enums\CategoryTagRequirement`:
  `none` | `required` ("Requires a tag") | `required_nil` ("Requires a tag - expect nil").
  Set per category on the category form; not inherited by child categories.
- Exposed in `CategoryResource` (`/api/v1/categories`) and in `/api/v1/reference-data` categories,
  which the transaction form preloads.

## Never a hard block

- No server-side validation: API, quick entry, AI documents and imports save untagged items as usual.
- Transaction form (`TransactionItem.vue`): an item whose category requires a tag and has none shows
  "This category expects a tag" under the tag field (the tag field is also shown on mobile then).
- `TransactionFormStandard::confirmMissingRequiredTags()` asks "... Save anyway?" before saving.
  Skipped for schedules/budgets (a future instance has no claim reference yet) and templates.
- The set of tag-requiring category ids comes from `loadTagRequiredCategoryIds()` in
  `resources/js/shared/lib/select-data` (empty set if the reference data fails to load).

## Service: `App\Services\RechargeTagService`

- `untaggedItems()` / `untaggedItemCount()`: items of non-schedule transactions in a `required` or
  `required_nil` category with no tag.
- `tagBalances()`: every tag used on at least one non-schedule item in a `required_nil` category.
  Balance = sum of **all** standard (withdrawal/deposit) non-schedule items carrying the tag, in any
  category, deposits `+`, withdrawals `-` (`TransactionType::amountMultiplier()`), converted to the
  base currency with the user's monthly rates (same approach as `AssetOverviewService`).
  `rates_missing` flags a foreign amount added unconverted. Settled = `|balance| <= 0.01`
  (`SETTLED_TOLERANCE`). An item with several tags counts towards each.
- `deactivateSettled(user, ?ids)`: sets `active = false` on tags that are settled **and** active,
  optionally limited to the given ids; ids of other users, open or inactive tags are ignored.
- `summary()`: counts for the dashboard widget.

## Pages and endpoints

| Route | Purpose |
| ----- | ------- |
| `GET /reports/recharges/untagged` (`reports.recharges.untagged`) | Items missing a tag, with edit links |
| `GET /reports/recharges/open` (`reports.recharges.open`) | Unsettled tags (active or not) with balance; settled active tags with per-row and bulk "Deactivate" |
| `POST /reports/recharges/deactivate-settled` | Form post; optional `tag_ids[]` (`DeactivateSettledTagsRequest`) |
| `GET /api/v1/reports/recharges/summary` (`abilities:read`) | `{enabled:false}` while the setting is off or no category requires a tag; else `untagged_items`, `open_tags`, `settled_active_tags` |

Both pages are server-rendered Blade (`resources/views/reports/recharges/`); `resources/js/recharges/index.js`
only formats `[data-money-amount]` and `[data-date]` with the shared i18n formatters.
Menu: Reports → Missing recharge tags / Open recharge tags. Dashboard widget: `RechargeSummary.vue`.

## Tests

`tests/Feature/RechargeTagTest.php` (Pest); the summary endpoint is in `ApiAbilityEnforcementTest`.
