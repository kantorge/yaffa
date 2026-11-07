# Part 3 — Responsive investment detail page

Read `specification.md` (shared contract) first. Self-contained session brief. No backend changes.

## Goal
Make the investment show page usable on phones, at card layout level and inside the cards. Desktop remains the primary use case and must not regress. Follow `.ai/docs/ui-conventions.md` (icon-only buttons below `md` with label in `<span class="d-none d-md-inline">`, no repeated `dt` labels, `.not-set`). Bootstrap utilities first; no inline styles/new libraries.

## Current state (verify)
Only the `md` breakpoint is used: `row > col-md-8 | col-md-4`, inner `col-md-5 | col-md-7`. Stack order on mobile: Details, Assets, Results, Transactions, Prices, Quantities.

## Changes (`resources/js/investments/components/display/`)
1. `InvestmentDisplayContainer.vue`: grid `col-12 col-xl-8` / `col-12 col-xl-4` and inner `col-12 col-md-5` / `col-md-7`; confirm a sensible order on small screens (Details, Assets, Results, Transactions, then charts). Coordinate with Part 1's account selector (full-width on mobile) — if Part 1 isn't merged, leave a spot but don't add it.
2. `CurrentAssetsCard.vue`: `dt col-7 col-sm-8` / `dd col-5 col-sm-4 text-end text-break` so large currency values don't wrap badly.
3. `ResultsCard.vue`: date inputs stack cleanly on `<sm` (label above input, full-width), `dl` blocks full width on mobile, Reset button icon-only below `md`.
4. `TransactionHistoryCard.vue` / `resources/js/shared/ui/datatable/TransactionTable.vue`: wrap the table in `.table-responsive` (horizontal scroll) as the minimal fix (9+ columns overflow today). Only add the DataTables `responsive` extension (currently used just by `AiDocumentTable.vue`) if scroll proves inadequate. TransactionTable is shared: check every consumer still renders correctly (make the wrapper opt-in via a prop if any page regresses).
5. `PriceHistoryCard.vue` / `QuantityHistoryCard.vue`: charts are fluid; use a height class (~220px below `sm`, 300px above) instead of the fixed 300px style; verify amCharts resizes on orientation change.
6. `InvestmentDetailsCard.vue`: check only (already `dt col-4 / dd col-8`); allow `text-break` for long names/ISIN/comment.

## Tests / verification
No logic change → no new unit tests needed. Verify manually at 375 / 768 / 1280 px (devtools) in light and dark mode: no horizontal page scroll, tables scroll inside their card, buttons usable, charts resize. If the Pest browser plugin supports viewport sizing, add one smoke test (`tests/PEST/Pages/Investments/`) asserting no page-level horizontal overflow at 375px. ESLint on `resources/js`; rebuild assets (`sail npm run dev`).

## Done when
Investment page works at 375px width with no clipped values or page-level horizontal scroll, desktop layout unchanged, other TransactionTable pages unaffected.
