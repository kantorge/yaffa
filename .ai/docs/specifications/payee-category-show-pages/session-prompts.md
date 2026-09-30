# Session Prompts — Payee and Category Show Pages

Copy-paste prompts to start one session per step. Every step works from
[`specification.md`](specification.md) on branch `feat/payee-category-show-pages`.

Order: 0 → 1 → (2a ∥ 2b) → 3 → 4 → 5. Steps marked **Checkpoint** end with a stop for owner
review.

Rules shared by all steps (already in `CLAUDE.md`, repeated here so no step skips them):
run commands via `vendor/bin/sail`; new tests in Pest 5; rebuild assets after JS/Vue changes;
Pint + PHPStan before finishing PHP work; run only the affected tests, then ask about the full
suite; commit on the branch, never push.

---

## Step 0 — Full-history performance check — ✅ DONE

Outcome: full history is too slow for large payees/categories. Owner decision: load the user's default transaction-history date range (spec §5.3, §9 R2).

```text
Branch: feat/payee-category-show-pages. Read .ai/docs/specifications/payee-category-show-pages/specification.md, especially §5.3 and risk R2 in §9.

Task: before any implementation, check whether loading a payee's or category's FULL transaction history into the browser (which the reporting widgets then aggregate) is acceptable.

1. Find the payee with the most standard transactions and the parent category with the most transaction items (including children) in the dev database. If the dev data is too small to be meaningful, tell me and propose how to generate a realistic large dataset (e.g. ~10 years of weekly transactions) — do not run migrate:fresh or anything destructive without asking.
2. Measure GET /api/v1/transactions?payees[]=<id> and ?categories[]=<id>: response time, payload size, row count.
3. Estimate browser cost: open Find transactions with the same filter (no date range) and note load/render time of the Summary, Timeline, Category details, Monthly breakdown, Waterfall and list tabs.
4. Report the numbers and a recommendation: full history OK / needs mitigation (which one). Do not change application code.

Checkpoint: stop after the report; I decide whether the design stands.
```

## Step 1 — Tests that lock in current behaviour — ✅ DONE

Outcome: 5 Pest browser tests added (`FindTransactionsReportTabsTest.php`, `InvestmentTransactionHistoryTest.php`), passing on current code. Not covered: pie charts and waterfall totals (canvas), Summary/Timeline "matching items only" (not honoured today; step 2a §7.4).

```text
Branch: feat/payee-category-show-pages. Read .ai/agents/testing.agent.md and .ai/docs/specifications/payee-category-show-pages/specification.md (§7, §8, §11).

Task: the upcoming frontend refactor (moving the reporting widgets to shared/, extracting TransactionReportTabs and TransactionTable) must not change the behaviour of two existing pages: Find transactions (resources/js/reports/components/find-transactions/FindTransactions.vue) and the investment show page's transaction history (resources/js/investments/components/display/TransactionHistoryCard.vue).

1. Inventory the existing coverage of both pages in tests/PEST/Pages/ (and legacy Dusk tests in tests/Browser/, for reference only — no new Dusk tests).
2. List the gaps against: tab switching, transaction list contents, delete from the list, monthly breakdown drill-down + return, the "matching items only" option's effect on category-aware tabs, investment transaction history rendering.
3. Show me the gap list, then write the missing Pest browser tests. They must pass on the current code. Keep them focused on visible behaviour, not implementation details, so they survive the move and rename.
4. Commit the tests.
```

## Step 2a — Frontend refactor (can run alongside 2b)

```text
Branch: feat/payee-category-show-pages. Read .ai/agents/frontend.agent.md and .ai/docs/specifications/payee-category-show-pages/specification.md (§2.2, §2.3, §7.1–§7.4, §8 phases 1–3).

Task: implement spec phases 1–3, one commit per phase, in this order:
1. Pure move/rename of the reporting widgets and aggregation helpers to resources/js/shared/ (§7.1). No behaviour change. The directory move is owner-approved.
2. Extract shared/ui/reports/TransactionReportTabs.vue (§7.2) and shared/ui/datatable/TransactionTable.vue (§7.3); switch Find transactions and TransactionHistoryCard to them. Find transactions keeps its in-page drill-down; the drill-down banner must stay where Find transactions shows it.
3. Widget fixes (§7.4): Summary and Timeline honour matchingItemsOnly/categoryIds/tagIds; MonthlyBreakdown category links point to categories.show. That route doesn't exist until step 2b is merged, so guard the link or coordinate as noted.

After each phase: rebuild assets, run ESLint on the touched files, run the step 1 browser tests for Find transactions and the investment page. Do not touch app/, routes/ or Blade views (step 2b owns those).

Checkpoint: after phase 3, stop and give me a short click-through checklist for Find transactions. Expected result: nothing changes except Summary/Timeline totals when "matching items only" is on.
```

## Step 2b — Backend (can run alongside 2a)

```text
Branch: feat/payee-category-show-pages. If step 2a is running at the same time, work in a separate git worktree on a branch off it (e.g. feat/payee-category-show-pages-backend) and merge back when done. Read .ai/agents/laravel-backend.agent.md, app/CLAUDE.md and .ai/docs/specifications/payee-category-show-pages/specification.md (§4, §5, §6, §11 backend part).

Task: implement the backend of spec §6:
- routes: enable categories.show; payees keep account-entity.show;
- AccountEntityController::show() payee branch → payees.show view (account branch unchanged); new CategoryController::show() with authorize('view');
- lifetime overview aggregate queries for payee (P1) and category incl. children (C1) as single aggregate queries in a service. Do NOT use Payee::transactions() or its accessors;
- category budgets (C4) with next occurrence via RecurrenceRuleService, CategoryLearning entries (C7), payee links (C6), parent/children;
- minimal Blade views resources/views/payees/show.blade.php and categories/show.blade.php following investments/show.blade.php, passing the data to a Vue mount point (the Vue pages come in steps 3–4; a placeholder mount is fine).

No new API endpoints unless unavoidable; if you add one, follow the abilities middleware rule in CLAUDE.md and update ApiAbilityEnforcementTest.

Tests: Pest 5 feature tests from spec §11 (owner 200 / other user 403, accounts still render, withdrawal/deposit split, schedules excluded, split transaction counts only the matching item, parent totals include children, base-currency sums). Run Pint and PHPStan. Commit.
```

## Step 3 — Payee page

```text
Branch: feat/payee-category-show-pages (steps 2a and 2b merged). Read .ai/agents/frontend.agent.md and .ai/docs/specifications/payee-category-show-pages/specification.md (§5.1, §5.3, §7.2, §7.5).

Task: build the payee show page Vue island on top of the Blade view from step 2b:
- P1 overview card from server data, P2 actions (reuse the existing PayeeForm modal, merge link, new transaction, open in Find transactions, delete only when unused), P3 pending category suggestion accept/dismiss (existing endpoints), P4 TransactionReportTabs over GET /api/v1/transactions?payees[]=<id> defaulting to the user's "Default date range for transaction history" setting via DateRangeFilterCard (spec §5.3), P5 upcoming schedules (scheduled-items with accountSelection=selected&accountEntity=<id>), P6 similar payees with merge link.
- Drill-down: no in-page drill-down. Navigate to reports.transactions with payees[]=<id> plus the payload's date_from/date_to/categories[] (§7.2).
- Payee list: make the name link to account-entity.show (§7.5). Do not add links to the shared transaction column definitions.
- Use existing i18n/format helpers only; reuse RecordOverviewCard or the account show overview pattern where it fits.

Rebuild assets, lint, add the Pest browser tests for the payee page from spec §11. Commit.

Checkpoint: stop and tell me how to open the page for review. The category page will copy this layout, so I want to review the UX first.
```

## Step 4 — Category page

```text
Branch: feat/payee-category-show-pages. Read .ai/agents/frontend.agent.md and .ai/docs/specifications/payee-category-show-pages/specification.md (§5.2, §5.3, §7.2, §7.5). Use the payee page from step 3 (and my review feedback on it) as the layout reference.

Task: build the category show page Vue island:
- C1 overview (parent/children links), C2 actions (edit, merge, open in Find transactions, open reports.budgetchart?categories[]=<id>), C3 TransactionReportTabs over GET /api/v1/transactions?categories[]=<id> with matchingItemsOnly: true and categoryIds = the category + its children, defaulting to the user's "Default date range for transaction history" setting (§5.3), C4 budgets, C5 scheduled transactions (scheduled-items?type=schedule&categories[]=<id>), C6 payee links to their show pages, C7 AI learning entries with a link to /category-learning.
- Drill-down: navigate to reports.transactions with categories prefilled plus the payload's dates (§7.2).
- Category list: make the name link to categories.show.

Rebuild assets, lint, add the Pest browser tests from spec §11 (split-transaction item totals, drill-down link). Commit.
```

## Step 5 — Documentation, review, PR

```text
Branch: feat/payee-category-show-pages (all implementation done). Read .ai/agents/documentation.agent.md and .ai/docs/specifications/payee-category-show-pages/specification.md.

Task:
1. Update .ai/docs/assets/payee/overview.md (remove the "no show page" assumption, describe the page) and .ai/docs/assets/category/overview.md.
2. Extract an as-built feature doc to .ai/docs/features/payee-category-show-pages/ from the code, not from the spec. Note every place where the implementation departs from the spec.
3. Add a changelog/UPGRADE note for the Find transactions Summary/Timeline "matching items only" change, following how the repo records user-visible changes.
4. Run /code-review on the branch diff against develop and report the findings. Don't fix anything without asking.
5. Ask me whether to run the full test suite, then draft the PR description (ending with the Claude Code attribution line) and ask before opening the PR.
```
