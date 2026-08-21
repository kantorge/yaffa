# Frontend Unification — Actionable Tasks

Source: `review.md` in this folder. Each task is scoped to be picked up independently by an agent. Where a task references a "target pattern," that pattern was either the clear existing majority in the codebase, or an explicit decision made with the user during the review (see the decisions table at the end of `review.md`) — do not re-litigate those; if a task turns out to need a decision not covered here, stop and ask rather than guessing.

Run `vendor/bin/sail npm run dev` after any JS/Vue/SCSS change and re-test the affected page before considering a task done, per `resources/js/CLAUDE.md`. Run `./vendor/bin/pint` and `./vendor/bin/phpstan analyse` after any PHP change, per the root `CLAUDE.md`.

---

## Priority 0 — behavioral risk (not just cosmetic)

### T-01: Modals can silently discard unsaved input via backdrop-click or Esc

**Area**: Frontend
**Problem**: Every modal in the app except `resources/js/user/ApiTokenManager.vue` can be dismissed by clicking the backdrop or pressing Esc, with no confirmation — including modals that hold unsaved form input (`resources/js/reports/components/BudgetForm.vue`, `resources/js/payee/components/PayeeForm.vue`, `resources/js/category-learning/components/CategoryLearningForm.vue`, `resources/js/currency-rates/components/CurrencyRateModal.vue`, `resources/js/investment-price/components/InvestmentPriceModal.vue`, `resources/js/ai-documents/components/AiDocumentUploadForm.vue`).
**Target pattern**: no new library needed — everything required is already installed and already used elsewhere in the app. Confirmed by inspecting the actual installed packages:

- **Dirty check**: every affected form already uses `vform`'s `Form` class (`vform` is already a dependency, `node_modules/vform/src/Form.ts`), which snapshots the initial values into `this.originalData` on construction/`update()` and exposes current values via `.data()`. `vform` doesn't expose a ready-made `isDirty` getter, but the check is one line: `JSON.stringify(form.data()) !== JSON.stringify(form.originalData)`.
- **Intercepting the close**: CoreUI's modal component (already the project's modal implementation, `@coreui/coreui`) fires a genuinely cancelable `hide.coreui.modal` event before _every_ dismissal path — backdrop click, Esc, the close button, and a programmatic `.hide()` call alike (verified in `node_modules/@coreui/coreui/js/src/modal.js:131-134`: `if (hideEvent.defaultPrevented) return`). Listening for this event and calling `event.preventDefault()` when the form is dirty blocks all four dismissal paths uniformly with one listener — no need to special-case backdrop vs. Esc vs. the X button.
- **The confirm prompt**: SweetAlert2 (already installed, already the app's dominant confirm mechanism), ideally through the shared confirm helper from T-07 rather than a one-off `Swal.fire` call, so this doesn't introduce a third dialog style alongside T-07's work.

Shape: on `hide.coreui.modal`, if the form isn't dirty let it close normally; if it is dirty, `event.preventDefault()`, show the confirm, and on confirmation call `.hide()` again bypassing the dirty check (e.g. via a `forceClose` flag checked at the top of the listener) so it doesn't loop.

This supersedes the `ApiTokenManager.vue:351-357` approach (imperatively locking `backdrop`/`keyboard` for the whole modal lifetime) for this use case — that pattern unconditionally blocks every close regardless of dirty state, which is correct for its one-time-token scenario but wrong here: a user who opened a form modal and changed nothing should still be able to close it instantly via backdrop/Esc.
**Acceptance criteria**: A user who has typed into a form-holding modal and clicks outside it or presses Esc is asked to confirm before the modal closes; a user who hasn't changed anything can close freely via any dismissal path.

---

## Priority 1 — explicitly requested cleanups

### T-02: Move Currency and Tag deletion from form-post to API call

**Area**: Backend + Frontend
**Problem**: Every other entity's delete flow is AJAX (`api.v1.*.destroy`) + `Swal.fire` confirm + toast, with no page reload. Currencies and Tags are the last two holdouts still using the legacy native-`confirm()` + hidden-form-POST + full-page-reload + flash-banner path, via `resources/js/shared/lib/datatable/index.js:112-128` (`initializeDeleteButtonListener`), consumed by `resources/js/currencies/index.js:120` and `resources/js/tags/index.js:103`.
**Investigation already done**: neither entity has a matching API controller today — `app/Http/Controllers/API/` has no `CurrencyApiController` at all, and `app/Http/Controllers/API/TagApiController.php` exists but has no `destroy()` method.
**Steps**:

1. Backend: add `destroy()` to a new `CurrencyApiController` (or the appropriate existing one if a broader currency API surface is added later) and to `TagApiController`, each with a corresponding `Route::delete(...)` in `routes/api.php` (follow the existing `currency-rates.destroy` registration at `routes/api.php:43-44` as the template) and the `abilities:write` middleware gating documented in `.ai/docs/features/api-access-and-2fa/permissions.md`. Add the deny/allow test pairs to `tests/Feature/API/ApiAbilityEnforcementTest.php`'s data provider per the root `CLAUDE.md` rule.
2. Frontend: replace the `initializeDeleteButtonListener` wiring in `resources/js/currencies/index.js` and `resources/js/tags/index.js` with the shared confirm+delete pattern from T-07 (Swal confirm → `axios.delete(route('api.v1.currencies.destroy', ...))` / `api.v1.tags.destroy` → `toastHelpers.showSuccessToast(...)` → remove the row from the DataTable in place, no reload).
3. Remove dead code once the above is live and tested:
   - `CurrencyController::destroy()` (`app/Http/Controllers/CurrencyController.php:143`) and `TagController::destroy()` (`app/Http/Controllers/TagController.php:112`), plus their web routes — change `Route::resource('currencies', CurrencyController::class)->except(['show'])` to `->except(['show', 'destroy'])` (and same for `tags`) in `routes/web.php:62,81`, rather than deleting the whole resource registration (create/edit/update/index/store must stay).
   - Do **not** remove `resources/views/template/components/model-delete-form.blade.php` — it's still legitimately used by `resources/views/accounts/history.blade.php`, `resources/views/accounts/show.blade.php`, and `resources/views/investments/show.blade.php`. Only remove the `@include` of it from `resources/views/currencies/index.blade.php` and `resources/views/tags/index.blade.php` if those views pull it in for the delete button.
   - Check whether `initializeDeleteButtonListener` in `resources/js/shared/lib/datatable/index.js:112-128` has any other callers left after this change; if not, remove it too.
     **Acceptance criteria**: Deleting a currency or a tag shows the standard Swal confirm, deletes via AJAX, shows a toast, and updates the table in place with no page reload — matching every other entity. No orphaned routes/controller methods/JS remain.

### T-03: Remove dead backend `destroy()` web actions left over from earlier API migrations

**Area**: Backend
**Problem**: While investigating T-02, this review found that `route('transactions.destroy')`, `route('account-groups.destroy')`, `route('categories.destroy')`, `route('investment-groups.destroy')`, and `route('investments.destroy')` are **all** registered web routes (via `Route::resource(...)` in `routes/web.php`) pointing at controller `destroy()` methods that still contain full flash-message + `redirect()` implementations — but a repo-wide search of `resources/views` and `resources/js` found **zero references** to any of these five route names. All five entities' actual delete UI already goes through `api.v1.*.destroy` (AJAX) instead; these web routes appear to be leftovers from before those entities were migrated to API-based delete, never cleaned up.
**Confirmed dead** (verified by this review — safe to act on directly): `TransactionController::destroy` (`app/Http/Controllers/TransactionController.php:133-151`), `AccountGroupController::destroy`, `CategoryController::destroy`, `InvestmentGroupController::destroy`, `InvestmentController::destroy`.
**Needs verification before touching** (not checked by this review — `AccountEntityController` serves both Accounts and Payees, and the review did not confirm whether the web `destroy()` action is still used by either): `app/Http/Controllers/AccountEntityController.php`'s `destroy()` and its `account-entity.destroy` web route (`routes/web.php:34`). Check both `accounts/*.blade.php` and `payee/*` JS before concluding it's dead — Payee already uses `api.v1.account-entities.destroy` per `resources/js/payee/index.js:333`, but Accounts' delete flow wasn't traced in this review.
**Steps**: For each confirmed-dead entity, remove the `destroy()` method from its controller and change its `Route::resource(...)` registration in `routes/web.php` to `->except([..., 'destroy'])` (adding to the existing `except` list where one is already present, e.g. line 32, 49, 62, 72; adding a new `->except(['destroy'])` where none exists yet, e.g. line 73 for investments, line 99 for transactions). Remove now-unused imports (e.g. `RedirectResponse` return types) if nothing else in the file needs them. Run the relevant Feature tests for each controller afterward.
**Acceptance criteria**: The five confirmed routes no longer exist; their controllers no longer have a `destroy()` method; all existing tests for these controllers still pass (update/remove any test that specifically exercised the now-removed web `destroy` action — check `tests/Feature/InvestmentGroupTest.php`, `tests/Feature/InvestmentTest.php`, `tests/Feature/InvestmentGroupApiControllerTest.php`, etc. as a starting point, since these are listed as already-modified in the current working tree). `AccountEntityController::destroy` is left untouched pending its own verification, or is done as a follow-up task once that's confirmed.

---

## Priority 1.5 — design-token modernization (land before the visual-judgment tasks below)

### T-19: Design-token modernization pass (typography, palette, radius, elevation, spacing) - DONE

**Area**: Frontend (CSS only — `resources/sass/_variables.scss`, plus `resources/sass/app.scss`'s font `@import` if a webfont candidate is chosen)
**Problem**: see `review.md` §11. Over 90% of `resources/sass/_variables.scss`'s Bootstrap/CoreUI override surface is untouched (stock defaults); the app is still visually wearing CoreUI Free's out-of-the-box skin (stock Bootstrap blue `$primary`, CoreUI's stock blue-tinted grays, a 2012-era webfont, default ~4px radius, default button bevel), which is why it reads as generic/dated rather than as a deliberately designed product.
**This is not a per-file task** — unlike everything else in this document, it's a small number of edits to `_variables.scss` (and `app.scss`'s font import line, if a webfont candidate wins) that cascade to every component automatically through Bootstrap's CSS-variable-driven cascade. No Vue/Blade files are touched by this task itself.
**Steps**:

1. Three ready-to-try, **build-verified, complete** `_variables.scss` replacement files already exist in `variable-candidates/` in this folder, each isolating a different fork (typeface: system-ui vs. Inter; elevation: flat-hairline-border vs. soft-ambient-shadow; how bold a radius/spacing change) while sharing the same palette refresh (indigo `$primary`, neutral grays) so the comparison isolates the subjective choices rather than conflating them. See `variable-candidates/README.md` for the exact testing procedure (`cp` a candidate over `resources/sass/_variables.scss` — it's a full replacement, not a delta to merge — `sail npm run dev`, view a representative spread of screens in both light and dark mode, revert with `git checkout` and repeat for the next candidate).
2. Pick one candidate, or a hybrid (e.g. Candidate A's flat elevation with Candidate B's Inter typeface — build a one-off merge of the two files' relevant lines for that comparison), based on that live comparison — **do not decide this from the SCSS alone**, it's a visual judgment call.
3. The winning candidate file _is_ the new `resources/sass/_variables.scss` — the `cp` from step 1 is the real, permanent change once a candidate is chosen, not throwaway scaffolding needing a separate integration pass. Update `resources/sass/app.scss`'s font `@import` too if Candidate B (Inter) is chosen.
4. Dark-mode surface colors are explicitly **not** covered by the three candidates — once a light-mode direction is picked, do a dedicated pass on the dark-mode `--cui-*`/`-dark`-suffixed tokens (confirmed convention: `$gray-800-dark`/`$gray-900-dark` already exist and are overridden for contrast-calculation reasons — the fuller list of what's tunable this way wasn't verified in the original review and needs checking here) rather than leaving `_custom.scss`'s current reactive bug-fix patches (`:332-337` etc.) as the only dark-mode-specific styling.
5. Sweep `resources/sass/_custom.scss` afterward for any patches that become redundant once the real tokens are fixed (e.g. the box-shadow-opacity visibility fix at `:133` may no longer be needed under a flat-elevation candidate).
   **Acceptance criteria**: `_variables.scss` reflects a deliberately chosen palette/radius/elevation/typography, not stock CoreUI defaults; the app looks and reads as one coherent design decision in both light and dark mode, not a mix of framework defaults and ad hoc dark-mode patches.

**Blocks (should land after this, so they're reviewed against the final tokens instead of twice)**: T-08, T-10, T-11, T-12 — each has a "Depends on: T-19" note below.
**Independent (no ordering constraint either way)**: T-01, T-02, T-03, T-04, T-05, T-06, T-07, T-09, T-13, T-14, T-15, T-16, T-17, T-18 — these are either pure class-mapping decisions (which semantic Bootstrap class to use, not what it renders as) or purely functional/backend work with no visual-judgment component.

## Priority 2 — visual/interaction unification

### T-04: Unify Cancel/secondary-action button color to `btn-secondary`

**Target pattern**: `btn-secondary` for all Cancel buttons (card footers and modal dismiss buttons alike). Applies to modal dismiss buttons currently labeled `"Close"` with the invalid `btn-default` class too — relabel to `"Cancel"` + `btn-secondary`.
**Files to update** (see `review.md` §3–4 for the full list): `resources/js/user/AiProviderSettings.vue:272` (`btn-outline-secondary` → `btn-secondary`), `resources/js/user/GoogleDriveSettings.vue:716`, `resources/js/user/ApiTokenManager.vue:244-251`, `resources/js/transactions/components/form/TransactionFormStandard.vue:508,543` (`btn-outline-dark` → `btn-secondary`), `resources/js/transactions/components/form/TransactionFormInvestment.vue:316,456,494`, `resources/js/shared/ui/date/DateRangeFilterCard.vue:69`, and the modal-dismiss `"Close"`/`btn-default` sites: `resources/js/reports/components/BudgetQuickView.vue:54`, `resources/js/reports/components/BudgetForm.vue:151-157`, `resources/js/payee/components/PayeeForm.vue:154-159`, `resources/js/category-learning/components/CategoryLearningForm.vue:111-116`.
Also update the SweetAlert2 `cancelButton` customClass wherever it's currently `'btn btn-outline-secondary ms-3'` (e.g. `resources/js/user/AiProviderSettings.vue:603`) to `'btn btn-secondary ms-3'` — see T-07, do this as part of building the shared confirm helper rather than editing each call site by hand.
**Acceptance criteria**: No `btn-outline-secondary`, `btn-outline-dark`, or `btn-default` remains on a Cancel/dismiss button anywhere in `resources/js`.

### T-05: Unify Delete/Destroy button color to `btn-danger`

**Target pattern**: `btn-danger` for every destructive-action button.
**Files to update**: `resources/js/user/ApiTokenManager.vue:72` (revoke token), `resources/js/user/TwoFactorSettings.vue:47`, `resources/js/reports/components/find-transactions/FindTransactionSelectCard.vue:9`, `resources/js/import/components/FileImportProfileManager.vue:443,453` — all currently `btn-outline-danger`.
**Acceptance criteria**: No `btn-outline-danger` remains on a delete/destroy/revoke button.

### T-06: Unify informational icon color to `text-info`

**Target pattern**: `text-info` for `fa-info-circle` tooltip/hint icons.
**Files to update**: all instances in `resources/js/transactions/components/form/TransactionFormStandard.vue` (e.g. lines 26, 178) and `resources/js/transactions/components/form/TransactionFormInvestment.vue` (e.g. lines 17, 154, 205), plus `resources/js/reports/components/BudgetForm.vue:51`, `resources/js/reports/components/find-transactions/FindTransactions.vue:44`, `resources/js/transactions/components/display/TransactionTypeFilterCard.vue:9` — all currently `text-primary`.
**Acceptance criteria**: No `fa-info-circle` icon uses `text-primary`; all use `text-info`.

### T-07: Retire native `confirm()` in favor of a shared `Swal.fire` confirm helper

**Area**: Frontend
**Problem**: 14 sites use native `confirm()` and ~15 sites hand-roll an identical `Swal.fire` options object for delete confirmation — see `review.md` §5 for the full site list. `resources/js/investments/index.js` uses both mechanisms for the same delete action in two different code paths (`:135` vs. `:274`).
**Target pattern**: One shared helper (new file, suggested location `resources/js/shared/lib/confirm/index.js`, following the existing convention of `resources/js/shared/lib/toast/index.js`) exporting something like `confirmDelete(text, { confirmButtonText } = {})` that wraps `Swal.fire` with the agreed defaults: `animation: false`, `icon: 'warning'`, `showCancelButton: true`, `buttonsStyling: false`, `customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-secondary ms-3' }` (per T-04's Cancel-color decision), `cancelButtonText: __('Cancel')`, `confirmButtonText: __('Confirm')` by default. Returns the same promise `Swal.fire` does so call sites keep their existing `.then((result) => ...)` structure.
**Steps**:

1. Build the helper.
2. Migrate every native-`confirm()` call site (see `review.md` §5 list) to the helper.
3. Migrate every hand-rolled matching-pattern `Swal.fire` call site to the helper, removing the duplicated options object.
4. Fix the two sites currently missing `buttonsStyling`/`customClass` entirely (`resources/js/transactions/components/form/TransactionItemContainer.vue:303-312,334-341`) by routing them through the same helper (or a sibling `confirmAction()` helper for the non-delete confirm at line 334, if a plain OK/Cancel with no danger styling is genuinely wanted there — check with the user if the intent is unclear).
5. Add a missing `icon: 'warning'` to `resources/js/user/TwoFactorSettings.vue:338-352` (currently the only confirm-style Swal with no icon at all).
6. Leave the two legitimate non-destructive exceptions as-is, don't force them through the delete helper: `resources/js/investments/components/display/TransactionHistoryCard.vue:115-128` (`btn-warning`, non-destructive skip-instance confirm) and `resources/js/user/GoogleDriveSettings.vue:1217-1236` (`icon:'question'` + `btn-primary`, non-destructive overwrite-name prompt).
   **Acceptance criteria**: `grep -rn "window.confirm\|[^.]confirm(" resources/js` (excluding the helper itself and unrelated matches) returns nothing outside legitimate non-Swal contexts; every delete confirmation in the app uses the shared helper; no `Swal.fire` call site duplicates the full options object inline anymore.

### T-08: Unify closable/foldable card patterns

**Area**: Frontend
**Depends on**: T-19 (design-token modernization) — this task involves visual/spacing judgment (chevron placement, dismiss-button treatment) that's more efficient to settle once against the final tokens than to redo after a radius/shadow change.
**Problem**: Four different implementations exist for what is conceptually 1–2 interactions (collapse vs. dismiss) — see `review.md` §7.
**Steps**:

1. `resources/js/dashboard/components/widgets/PayeeCategoryRecommendation.vue:126` — replace the raw jQuery `$('#widgetPayeeCategoryRecommendation').hide()` with Vue-reactive `v-if`/`v-show` state, matching `resources/js/dashboard/components/widgets/OnboardingCard.vue`'s already-correct pattern.
2. Extract the corner-X dismiss markup + `dismiss`-event contract duplicated across `resources/js/import/components/ScheduleCandidatesPanel.vue:19-23`, `resources/js/import/components/DuplicateCandidatesPanel.vue:15-19`, and `resources/js/import/components/RelatedAiDocumentsPanel.vue:16-20` into one shared component (e.g. `resources/js/shared/ui/DismissiblePanel.vue`), used by all three.
3. `resources/js/import/components/ImportDraftTable.vue:157-163` — evaluate whether its hand-rolled `expandedRows` chevron-swap can become a standard `collapse-control`/CoreUI-collapse toggle (pattern 1 from `review.md` §7) instead of custom state; if the interaction genuinely needs per-row independent expand/collapse state that Bootstrap's collapse plugin can't express cleanly, leave it as-is and note why in the component rather than forcing a bad fit — ask the user if unsure.
   **Acceptance criteria**: No `$(...).hide()`/`.show()` remains for a dismissible dashboard card; the three corner-X dismiss panels share one component instead of three copies.

### T-09: Unify button icon layout to `fa me-1 {icon}`

**Area**: Frontend
**Target pattern**: `<i class="fa me-1 {icon}"></i>{{ label }}` — base `fa`, then `me-1`, then the specific icon class, immediately followed by the label text with no extra whitespace/line break. This supersedes the originally-referenced `fa fa-fw {icon} me-1` pattern (see `review.md` §8) — **`resources/js/user/AiProviderSettings.vue` itself needs updating too** (lines 249, 263, 272, 284), it is no longer the literal target once this correction is applied, even though it remains the right file to model _everything else_ (footer layout, Swal usage, etc.) on.
**Files to update** (representative — see `review.md` §8 for the full list): `resources/js/user/AiProviderSettings.vue:249,263,272,284`, `resources/js/user/GoogleDriveSettings.vue:675,691,707-711,731-733`, `resources/js/user/AiBehaviorSettings.vue:646`, `resources/js/transactions/components/display/ActionButtonBar.vue:14-15,51,59`, `resources/js/user/TwoFactorSettings.vue:94-97`, `resources/js/user/ApiTokenManager.vue:138-142`, `resources/js/transactions/components/form/TransactionFormStandard.vue:555` and `TransactionFormInvestment.vue:506` (also switch `<span>`→`<i>` and `fa-floppy-disk`→`fa-save` to match every other Save button), the context-menu trigger icon repeated in `resources/js/account/index.js:30`, `resources/js/payee/index.js:419`, `resources/js/reports/schedules.js:192`, `resources/js/investments/index.js:28`, `resources/js/ai-documents/components/AiDocumentTable.vue:516` (currently `me-2 fa-fw fa-solid fa-ellipsis-vertical`, reversed order and wrong base class), and the inconsistent `fa-fw` on the "+New" button across index pages (`resources/views/tags/index.blade.php:34`, `resources/views/investments/index.blade.php:32` vs. `resources/views/payees/index.blade.php:36`, `resources/views/accounts/index.blade.php:35`, `resources/views/categories/index.blade.php:35` — drop `fa-fw` from all of them per the new standard, since it's not part of the corrected pattern).
**Separate, larger gap** (call out but don't silently fold into this task): plain Blade CRUD forms (`resources/views/categories/form.blade.php:168-169`, `resources/views/accounts/form.blade.php:259-260`, `resources/views/tags/form.blade.php:84-85`, `resources/views/currencies/form.blade.php:123-124`, `resources/views/investments/form.blade.php:228-229`, `resources/views/investment-groups/form.blade.php:55-56`, `resources/views/account-groups/form.blade.php:55-56`, `resources/views/payees/form.blade.php:136-137`) have **no icon at all** on Save/Cancel — they use plain `<input type="submit">`/`<a>` tags, not `<i>` + text. Adding icons here means changing these from plain form controls to icon-bearing buttons, a bigger visual change to the app's oldest screens than a class-order fix. Flag to the user for a go/no-go before doing this broadly; if approved, do it as its own follow-up rather than bundling into T-09.
**Acceptance criteria**: every `<i class="fa ...">` paired with button text in `resources/js` uses `fa me-1 {icon}` with no extra whitespace before the label.

### T-10: Unify spinner/busy-indicator conventions

**Area**: Frontend
**Depends on**: T-19 (design-token modernization) — mostly for the `ScheduleCalendar.vue` per-moment judgment call, which is easier to settle against final elevation/radius than to re-review after.
**Target pattern**: two situations, two indicators — (a) a button in a busy/submitting state uses `fa-spinner fa-spin` swapped in for its normal icon; (b) a section/widget waiting on data uses Bootstrap `placeholder`/`placeholder-glow` skeletons. `spinner-border` is retired in favor of (a) where it's used for button busy-state.
**Files to update**: migrate `spinner-border spinner-border-sm` off button busy-states in `resources/js/ai-documents/components/AiDocumentUploadForm.vue:176`, `resources/js/import/components/DuplicateCandidatesPanel.vue:70`, `resources/js/import/components/ScheduleCandidatesPanel.vue:82`, `resources/js/import/components/ImportUploadCard.vue:112`, `resources/js/currency-rates/components/CurrencyRateModal.vue:84`, `resources/js/investment-price/components/InvestmentPriceModal.vue:78,91`, `resources/js/import/components/ProfileCreationWizard.vue:158,696`, `resources/js/import/components/FileImportProfileManager.vue:235,339,459`, onto `fa-spinner fa-spin`. Add a busy-state spinner to the Save buttons currently losing their icon with no replacement during submit: `resources/js/user/GoogleDriveSettings.vue:675`, `resources/js/user/AiBehaviorSettings.vue:646`, `resources/js/transactions/components/form/TransactionFormStandard.vue:555`, `resources/js/transactions/components/form/TransactionFormInvestment.vue:506`. Fix `resources/js/dashboard/components/widgets/ScheduleCalendar.vue`, which mixes `placeholder-glow` (lines 18-19) and an FA spinner (line 446) for two different loading moments in the same component — decide per-moment which of the two categories each one actually is and apply consistently.
**Acceptance criteria**: no button busy-state uses `spinner-border`; no button loses its icon during submit with nothing replacing it; section-level data loading uses `placeholder-glow` consistently.

### T-11: Unify card-header layout

**Depends on**: T-19 (design-token modernization) — card-header spacing/padding decisions are worth reviewing against the final radius/spacing tokens rather than the current stock ones.
**Target pattern**: `<div class="card-header d-flex justify-content-between align-items-center">` with `<div class="card-title">` as the title. See `review.md` §1 and its "Note on card-header-actions" section — there is no pre-existing named class to adopt (the review checked and confirmed `card-header-actions` is not a real class in this project's CoreUI package), so this is a convergence onto the existing majority pattern, not adoption of something new.
**Files to update**: add `align-items-center` where missing (`resources/js/dashboard/components/widgets/AccountBalance.vue:3`, `resources/js/user/AiSettings.vue:3`, `resources/js/investments/components/display/InvestmentDetailsCard.vue:3`); remove the redundant `mb-0` on `card-title` (`resources/js/user/TwoFactorSettings.vue:4`, `resources/js/user/ApiTokenManager.vue:4` — `_custom.scss` already zeroes this margin); wrap the bare text header in `resources/views/auth/passwords/confirm.blade.php:8` in a `card-title` div.
**Acceptance criteria**: every `card-header` with more than one child uses the `d-flex justify-content-between align-items-center` combination; no component sets a redundant `mb-0` the theme already provides.

### T-12: Unify card-footer action layout

**Depends on**: T-19 (design-token modernization) — same reasoning as T-11, this is a spacing/layout judgment call best made once against final tokens.
**Target pattern**: primary action first (left), secondary/cancel next (left), a lone destructive action alone on the right via `justify-content-between` (only when a destructive action is present) — model on `resources/js/user/AiProviderSettings.vue:241-280`.
**Files to update**: `resources/js/transactions/components/form/TransactionFormStandard.vue:539-558` — move Save/Cancel into a proper `card-footer` and correct the order (currently Cancel-before-Save, reversed from the rest of the app).
**Ask before proceeding**: `resources/js/dashboard/components/widgets/AccountBalance.vue:93-100`'s empty-spacer-div trick — confirm whether this footer should gain real left-side content or the spacer approach is intentional and fine to leave; not clear from the code alone.
**Acceptance criteria**: `TransactionFormStandard.vue`'s form actions are in a `card-footer` with Save before Cancel.

### T-13: Split "Add/New" button color by actual semantics

**Target pattern**: `btn-success` for page-level "New X" launcher buttons (navigating to a create form); `btn-primary` for an in-place "Add" submit button inside a modal (it's functionally a Save).
**Files to check**: confirm `resources/js/currency-rates/components/CurrencyRateModal.vue:78` (already `btn-primary`, correct) is the pattern to replicate for any other modal "Add" submit button that might currently be `btn-success` by mistake — sweep `resources/js/**/*.vue` for modal-context Add/Save submit buttons using `btn-success` and correct them to `btn-primary`. Leave `resources/js/currency-rates/components/CurrencyRateActions.vue:29` (`btn-xs btn-primary`, icon-only add trigger that opens a modal — arguably closer to a "launcher") and `resources/js/transactions/components/form/TransactionItem.vue:107` (`btn-sm btn-outline-success`, inline accept-suggestion) as-is per the review's reasoning — these aren't the same interaction as either bucket.
**Acceptance criteria**: no modal's Save/Add submit button uses `btn-success`.

---

## Priority 3 — technical debt / duplication

### T-14: Extract shared CurrencyRate/InvestmentPrice component skeleton

**Problem**: `resources/js/currency-rates/components/{CurrencyRateTable,CurrencyRateManager,CurrencyRateOverview}.vue` and `resources/js/investment-price/components/{InvestmentPriceTable,InvestmentPriceManager,InvestmentPriceOverview}.vue` are near-line-for-line duplicates (DataTables config, edit/delete button HTML strings, delete methods, `updateTableData`), differing mainly in field names and one legitimate behavioral difference (investment-price's rolling 30-day default date range vs. currency-rate's precomputed timestamp comparison).
**Target pattern**: extract the shared skeleton (table config shape, delete flow via T-07's helper, `Manager`'s `Overview`+`Actions`+`Table`+chart+`Modal` orchestration) into shared component(s)/composable(s) under `resources/js/shared/`, parameterized by field names and the date-range behavior, leaving each feature folder with only its genuine domain-specific logic.
**Acceptance criteria**: the two `Table.vue`/`Manager.vue`/`Overview.vue` trios no longer duplicate the DataTables/delete/orchestration boilerplate; each retains only its real domain difference.

### T-15: Extract a shared "card + DataTable" list wrapper

**Problem**: no shared component exists for the "Bootstrap card containing a DataTable" pattern used by `resources/js/currency-rates/components/CurrencyRateTable.vue`, `resources/js/investment-price/components/InvestmentPriceTable.vue`, `resources/js/ai-documents/components/AiDocumentTable.vue`, `resources/js/import/components/ImportDraftTable.vue`, and others — each hand-rolls its own card/header/body markup.
**Target pattern**: `resources/js/shared/ui/date/DateRangeFilterCard.vue` is proof this kind of extraction already works in this codebase (reused by 4 features) — follow the same approach for a list-card wrapper. Scope this after T-14, since T-14 will already pull the two closest examples together.
**Acceptance criteria**: at least the CurrencyRate/InvestmentPrice pair (post-T-14) share one list-card wrapper component instead of two copies.

### T-16: Extract a shared modal-form skeleton

**Problem**: `resources/js/payee/components/PayeeForm.vue`, `resources/js/category-learning/components/CategoryLearningForm.vue`, and `resources/js/reports/components/BudgetForm.vue` each re-declare the same modal-header/body/footer boilerplate and "new"/"edit" title-toggle idiom around the shared third-party `vform` `AlertErrors`/`AlertSuccess` components.
**Target pattern**: a shared `resources/js/shared/ui/FormModal.vue` (or similar) providing the header/footer/title-toggle skeleton as a slot-based wrapper, each form component filling in only its fields.
**Acceptance criteria**: the three named form components no longer duplicate the modal skeleton markup.

### T-17: Consolidate the native-confirm + jQuery-ajax delete duplication in categories/account-groups/investment-groups

**Problem**: `resources/js/categories/index.js:293-330`, `resources/js/account-groups/index.js:58-93`, `resources/js/investment-groups/index.js:58-93` are ~35-line near-identical blocks, despite a shared helper (`resources/js/shared/lib/datatable/index.js:112`, `initializeDeleteButtonListener`) already existing and being used correctly by `resources/js/tags/index.js` and `resources/js/currencies/index.js`. These three reimplement it inline, likely because they also need client-side row/array mutation the shared helper doesn't support.
**Note**: after T-02 and T-03 land, revisit whether categories/account-groups/investment-groups should also move to the AJAX+toast pattern (T-02's target) instead of this legacy path entirely — that may make this task moot. Check the state of T-02/T-03 before starting this one; if those are done, this task may reduce to "migrate these three the same way as Currency/Tag" rather than "extend the old helper."
**Acceptance criteria**: no duplicated ~35-line delete block remains across these three files.

---

## Priority 4 — future phase (logged, not scoped for this pass)

### T-18: Migrate remaining Create/Edit flash-redirect flows to inline AJAX + toast

**Area**: Backend + Frontend (larger scope than anything above — touches navigation behavior, not just visuals)
**Problem**: Create/Edit for Investments, Transactions, and other Blade-form-backed entities still do a synchronous POST → full-page redirect → Bootstrap flash-banner notification, while Delete for the same entities already uses AJAX + instant toast with no reload (see `review.md` §6 for the full comparison, e.g. Investments' create/edit at `resources/views/investments/form.blade.php` + `app/Http/Controllers/InvestmentController.php:111,166` vs. its delete at `resources/js/investments/index.js:135-300`).
**Status**: explicitly logged as a distinct future phase per the user's decision during this review — do not start this without a fresh scoping/planning pass, since it changes page-navigation behavior for some of the app's most-used flows (not a pure look-and-feel fix like the tasks above). Treat this entry as a placeholder to pick up later, not a ready-to-implement task.
