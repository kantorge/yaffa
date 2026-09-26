# Follow-up: Frontend Decimal Precision for Investment Price

Status: **not started**. Scoped out of the precision-improvements code-review response
(2026-08-15) as too large/risky to fix as part of that pass. This document is the handoff
brief for whoever picks it up next.

## Problem

The backend now serializes money/quantity fields as exact decimal strings (`MoneyCast`/
`DecimalCast`, see `specification.md` FR-4/FR-5). The frontend's job is to carry that
precision through editing without collapsing it into a lossy intermediate. For most fields
it doesn't matter: `transaction_items.amount` and `transaction_details_investment.quantity`/
`commission`/`tax`/`dividend` are DECIMAL scale 4, well inside what a JS `Number` (IEEE-754
double, exact up to 2^53, ~15-17 significant digits) can hold exactly.

**One field is not safe: `transaction_details_investment.price`, `DECIMAL(20,10)`.** 20
significant digits exceeds what a double can represent exactly. Two places in the frontend
currently collapse this field to a native `Number` regardless:

1. `resources/js/shared/lib/helpers/index.js:174` (`processTransaction()`) - the instant an
   API response is parsed, `transaction.config.price = toNumberOrNull(transaction.config.price)`
   converts the exact decimal string straight to a float. This runs for every transaction
   fetch, including opening an existing transaction for edit.
2. `resources/js/shared/ui/form/MathInput.vue:49-61` (`updateAmount()`) - evaluates the
   field's displayed text via `mathjs`'s `evaluate()` (float arithmetic, not exact), wraps
   the float result in a `Decimal` only to clamp fractional digits, then calls `.toNumber()`
   and emits a plain `Number`. This fires on **every blur**, not just when the user actually
   edited the text.

## Concrete failure scenario

An investment price gets written with more precision than a human ever types - typically an
automated price-provider import (`InvestmentService::fetchAndSavePrices()` /
`savePriceQuietly()`) or a price with many significant digits (e.g. a low-value crypto
asset). Opening that BUY/SELL transaction for edit, then saving it **without touching the
price field**, can silently truncate that price to whatever a JS double could hold - even
though the user made no change to that field. Just tabbing through the field (blur, no
edit) already triggers the lossy round-trip.

This is a real gap, not a hypothetical: `specification.md` FR-6 ("Frontend decimal
adoption") is marked `✅ Completed`, but the two spots above were never actually converted to
exact decimal handling - only `TransactionItemContainer.vue`'s allocation logic and
`MathInput`'s `Decimal`-based rounding were.

## Current `MathInput` behavior (FR-8)

`MathInput.vue` has no per-field precision configuration. It rounds every value it emits,
price included, to a fixed `FLOAT_NOISE_CLEANUP_SCALE = 10` constant, purely to normalize
floating-point noise from `mathjs`'s `evaluate()` - never to enforce the price field's real
storage scale. It shows a warning toast whenever that rounding actually changes the value
(`Decimal.equals()` comparing the pre- and post-round value). This does not touch the concrete
failure scenario this document is about: `processTransaction()` collapsing an already-stored
`DECIMAL(20,10)` value to a native `Number` on every fetch, independent of whether the user
ever interacts with `MathInput`. "Suggested scope for a first pass" item 5 below
(precision-preserving evaluation inside `MathInput` itself, i.e. not routing a plain decimal
through `mathjs`'s float `evaluate()` at all) remains open: the warning toast surfaces that
precision was lost on manual entry, but doesn't prevent `evaluate()` from losing it.

## What's already correct - do not change

- **`TransactionItemContainer.vue`'s allocation logic** (`getSuggestedItemsFromHistory()` /
  `roundAmount()`, lines ~399-449). It already does the whole computation in `Decimal` and
  only converts to `Number` once, at the very end, for a scale-4 field. That's correct and
  safe - not a bug, despite an earlier code review flagging it as one.
- **Scale-4 fields in general** (`amount`, `quantity`, `commission`, `tax`, `dividend`).
  Leave their `Number` handling alone; only `price` needs to change.

## Why this is bigger than a two-file patch

Several other places already do plain native arithmetic (`+`, `*`, `-`) directly on
`transaction.config.price`/`quantity`/`dividend`/`commission`/`tax`, assuming they're
`Number`s:

- `resources/js/transactions/components/display/ShowInvestment.vue:226-239` (`total`
  computed and quantity display, native operators)
- `resources/js/shared/lib/datatable/index.js:336-394` (investment transaction row
  rendering, native operators)

If `processTransaction()` stops converting `config.price` to a `Number`, these two break
silently (native `+` on a string concatenates instead of adding). Any fix has to either:
(a) update these two call sites to use `Decimal` as well, or (b) keep a `Number` available
for display/list arithmetic and only preserve the exact string on the *editable form* path
(the one that round-trips through submission), accepting that read-only displays keep
today's float-based total. Also check `TransactionFormInvestment.vue:1317`
(`existingPriceForDate = Number(response.data.price)`) - currently only feeds a display
hint (not resubmitted), so it's lower priority than the two above, but audit it too.

`resources/js/investments/components/display/ResultsCard.vue` was already migrated to
`Decimal` for its ROI calculation and is a reasonable reference for the pattern to follow.

## Design decision to make before writing code

`MathInput` currently always emits a `Number` (its prop already accepts `[Number, String]`,
but the emit side doesn't use that). Fixing the price field's precision requires deciding
what it emits instead, since a `Number` fundamentally cannot hold scale-10 precision no
matter how carefully it's parsed on the way there:

- **Option A - emit a decimal string for `price` specifically.** Smallest blast radius:
  only the price-related consumers (`TransactionFormInvestment.vue`'s `total` computed,
  submission payload, `ShowInvestment.vue`, `datatable/index.js`) need to switch to
  `Decimal`-based math. Other `MathInput` consumers (amount, quantity, commission, tax,
  dividend) are unaffected.
- **Option B - change `MathInput`'s contract app-wide** to always emit a decimal string
  (or `Decimal` instance), never a `Number`. More consistent, but touches every v-model
  bound to `MathInput` across `TransactionFormStandard.vue`, `TransactionItem.vue`, and
  `TransactionFormInvestment.vue` (8 usages total) plus every place that reads those
  underlying data properties with `+`.

Recommend Option A unless there's a reason to want the broader consistency now.

Separately, `MathInput.vue:49` calls `mathjs`'s `evaluate()`, which does float arithmetic
internally *before* the result is ever wrapped in `Decimal` - so even the existing
`Decimal`-wrap-and-clamp step at line 58-62 is operating on an already-imprecise input for
anything beyond double precision. A full fix needs `updateAmount()` to either (a) parse a
plain decimal number directly (bypass `mathjs`) when the input has no operators, only
falling back to `mathjs.evaluate()` for actual expressions, or (b) skip re-evaluation
entirely when the displayed text hasn't changed since the last render (cheap partial fix:
avoids the blur-with-no-edit case, but doesn't help an input that *is* an expression, e.g.
`"12.3456789012 * 2"`).

## Suggested scope for a first pass

1. `MathInput.vue`: when the raw (separator-normalized) input string is unchanged from the
   current `modelValue`'s string form, skip re-evaluation entirely (no-op the blur). This
   alone fixes the "just tabbed through, didn't edit anything" drift case cheaply.
2. `processTransaction()`: stop converting `config.price` to `Number`; leave it as the
   decimal string from the API.
3. `TransactionFormInvestment.vue`: `total` computed already uses `new Decimal(...)` for
   every field including `price` (see the comment at line ~673) - verify it still works
   with a string `price` (it should; `Decimal` accepts strings). Update the submission path
   to send the string through unmodified when unedited.
4. `ShowInvestment.vue` and `datatable/index.js`: convert their `price`-involving arithmetic
   to `Decimal`, following `ResultsCard.vue`'s existing pattern.
5. Actual precision-preserving evaluation in `MathInput` for the case where the user *does*
   type a high-precision price (option A/B above for `evaluate()`) - the resubmission-drift
   case (item 1-3) remains the one with the most common real-world trigger (price-provider
   imports). A human manually typing an 11+-decimal price now gets a warning that precision
   was lost (see "Current `MathInput` behavior" above), but `evaluate()` still loses it before
   `Decimal` ever sees the value - the warning surfaces the symptom, not the root cause this
   item describes.

## Acceptance check

At minimum, a regression test/story that: seeds an `investment_prices` or
`transaction_details_investment.price` row with more than 15 significant digits (e.g. via
factory, bypassing the UI's own entry-precision clamp), opens the investment BUY/SELL
transaction edit form, saves without touching the price field, and asserts the stored price
is byte-for-byte unchanged.

## Out of scope

- Scale-4 fields (`amount`, `quantity`, `commission`, `tax`, `dividend`) - not at risk, leave
  as `Number`.
- `MathInput`'s rounding mechanism (the `Decimal.toDecimalPlaces()` call and its
  `Decimal.equals()`-gated warning toast) - correct and out of scope here. There is no
  per-field `precision` prop; `MathInput` rounds every field, price included, to the same
  fixed `FLOAT_NOISE_CLEANUP_SCALE = 10`. Tracked in `specification.md` FR-8, not in this
  document.
- Report endpoints (`/api/v1/reports/*`) - already documented (`UPGRADE.md`) as returning
  plain JSON numbers by design; no change needed there.
