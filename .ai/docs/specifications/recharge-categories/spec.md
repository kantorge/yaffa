# Recharge categories (spec)

Purchases made on behalf of someone else, where reimbursement is expected, are booked to a
category that requires a tag (e.g. a claim number).

## Agreed decisions (2026-11-13)

- `categories.tag_requirement`: `none` | `required` ("Requires a tag") | `required_nil`
  ("Requires a tag - expect nil"). Enum `App\Enums\CategoryTagRequirement`. No parent inheritance.
- Never a hard block: the transaction form warns per item and confirms on save; API, quick entry,
  AI documents and import are unaffected.
- Page "Missing recharge tags": items in `required`/`required_nil` categories with no tag
  (non-schedule transactions only).
- Page "Open recharge tags": tags used on at least one item in a `required_nil` category.
  Balance = sum of **all standard items** carrying the tag (any category), deposits positive,
  withdrawals negative, converted to the base currency (monthly rates, as in
  `AssetOverviewService::aggregate()`). Settled = |balance| <= 0.01. Non-settled tags listed even
  if inactive; settled-but-active tags get per-row and bulk "Deactivate".
- Dashboard widget: counts of untagged items and open tags; hidden when no category has a
  tag requirement.

- (Added) Opt-in per user via a "Recharge tags" switch in user settings, plus a "Recharge backstop
  date" that hides older activity from both reports.

## Status

Implemented, see [`features/recharge-categories/architecture.md`](../../features/recharge-categories/architecture.md).
