# Frontend Consistency Review

Date: 2026-08-20
Scope: `resources/js/**/*.vue` and `resources/views/**/*.blade.php`, ~100 Vue components surveyed. Pure observation — no code changed as part of this review. Actionable follow-ups are tracked separately in `tasks.md` in this folder.

Goal (per request): unify look-and-feel and interaction patterns to make user flows more fluent. Not a redesign — no layout, theme, or color-palette changes are in scope, only convergence of existing divergent patterns onto one existing pattern each.

---

## Note on "card-header-actions"

The request asked to review card-header button placement "using `card-header-actions`" as a named pattern. This was checked directly against the codebase and the installed package:

- `grep -r "card-header-actions"` across the entire repo (`.vue`, `.blade.php`, `.scss`, `.css`, `.js`) returns **zero matches**.
- `grep -r "card-header-actions"` across `node_modules/@coreui` (the installed `@coreui/coreui@5.7.1` package, including its SCSS source) also returns **zero matches** — the class does not exist in this project's CoreUI build at all.

`card-header-actions` is not a Bootstrap class, and it is not part of the CoreUI CSS library this project ships (`@coreui/coreui`). It appears in CoreUI's _admin template marketing/demo pages_ (the paid dashboard template product, and some framework-specific component wrappers like `@coreui/react`) as a semantic wrapper `<div>` name used in their example markup — but even there it carries no bundled styling of its own; it's just a naming convention in their docs, not a utility class. Since this project only depends on the base `@coreui/coreui` CSS package, adopting `card-header-actions` here would mean writing new CSS for a class name that has no meaning yet, effectively inventing a new pattern rather than converging on an existing one.

**Conclusion**: there is no pre-existing "actions area" convention to standardize on. Every card-header action group in this codebase is currently hand-rolled with Bootstrap flex utilities (`d-flex justify-content-between`, sometimes with `align-items-center`, sometimes without). See §1 below for the actual divergence and the recommended target pattern.

---

## 1. Card headers (title / actions)

- No shared "header actions" wrapper (see note above) — every header with actions is a hand-rolled flex div, and the flex classes differ:
  - Plain, no flex class at all: `resources/js/user/UserSettings.vue:9-13`.
  - `d-flex justify-content-between` (no vertical centering): `resources/js/dashboard/components/widgets/AccountBalance.vue:3`, `resources/js/user/AiSettings.vue:3`, `resources/js/investments/components/display/InvestmentDetailsCard.vue:3`.
  - `d-flex justify-content-between align-items-center`: `resources/js/user/ApiTokenManager.vue:3`, `resources/js/import/components/FileImportProfileManager.vue:4`.
- Collapsible headers: the whole `card-header` is the toggle target in `resources/js/import/components/FileImportProfileManager.vue:4-10`, but only the inner `card-title` is the toggle target everywhere else, e.g. `resources/js/currency-rates/components/CurrencyRateActions.vue:4-9`, `resources/js/shared/ui/date/DateRangeFilterCard.vue:3-8`, `resources/views/investment-groups/index.blade.php:18-21`.
- Title markup: most use `<div class="card-title">`; `resources/js/user/TwoFactorSettings.vue:4` and `resources/js/user/ApiTokenManager.vue:4` add a redundant `mb-0` (the app's `resources/sass/_custom.scss:183-186` already zeroes `card-title` margin globally); `resources/views/auth/passwords/confirm.blade.php:8` skips `card-title` entirely and puts text directly in `card-header`; `resources/js/user/InvestmentProviderSettings.vue:16-17` replaces the title with a `nav nav-tabs card-header-tabs` strip — structurally different from every other header.
- Header info/warning icon placement: inline inside `card-title` in `resources/js/dashboard/components/widgets/CategoryWaterfall.vue:4-8`, vs. a sibling flex-child after `card-title` in `resources/js/user/AiSettings.vue:7-8` and `resources/js/investments/components/display/InvestmentDetailsCard.vue:7-8`.

**Suggested standard**: `<div class="card-header d-flex justify-content-between align-items-center">` with `<div class="card-title">...</div>` as the first child and an actions/icons wrapper `<div>` as the second — this is the pattern already used in the two most recently written settings panels (`ApiTokenManager.vue`, `FileImportProfileManager.vue`) and is a strict superset of the other variants (adding `align-items-center` never breaks a header that didn't need it).

## 2. Card footer button order & alignment

- **Blade CRUD forms are internally consistent** with each other: plain `card-footer` (no flex, default left alignment), Save (`btn-primary`, submit) first, Cancel (`btn-secondary.cancel.confirm-needed`) second. E.g. `resources/views/accounts/form.blade.php:255-261`, `resources/views/currencies/form.blade.php:121-125`, `resources/views/tags/form.blade.php:81-86`, `resources/views/account-groups/form.blade.php:53-58`. `.confirm-needed` triggers an unconditional `confirm()` in `resources/js/app.js:143-145` regardless of whether the form is actually dirty (not a true dirty-check).
- **Vue widget/settings footers diverge from that convention and from each other**:
  - Single primary button only, no Cancel: `resources/js/user/UserSettings.vue:215-223`, `resources/js/user/ChangePassword.vue:72-80`.
  - Empty spacer `<div></div>` used purely to push footer content right via `justify-content-between`: `resources/js/dashboard/components/widgets/AccountBalance.vue:93-100`.
  - Left button-group (Save, Test, Cancel — Save first) + a lone `btn-danger` Delete pushed to the far right by `justify-content-between`: `resources/js/user/AiProviderSettings.vue:241-280`, `resources/js/user/GoogleDriveSettings.vue:667-700`. This exact layout exists only in these two files.
  - No `card-footer` at all — buttons sit inline in the form body, and the order is **reversed** from the Blade convention (Cancel before Save): `resources/js/transactions/components/form/TransactionFormStandard.vue:539-558`.
  - `card-footer` reused to hold unrelated view-toggle button groups (not Save/Cancel/Delete actions at all): `resources/js/transactions/components/display/ItemContainer.vue:57-76`, `resources/js/dashboard/components/widgets/CategoryWaterfall.vue:51-71`.

**Suggested standard**: primary action first (left), secondary/cancel next (left), destructive action alone on the right via `d-flex justify-content-between align-items-center` — i.e. the `AiProviderSettings.vue` / `GoogleDriveSettings.vue` pattern, since it's the only one that already scales to the "has a destructive action too" case. For footers with just Save+Cancel, keep the Blade order (Save first) without needing the `justify-content-between` wrapper.

## 3. Button & icon coloring

Same semantic action, different Bootstrap color depending on which component wrote it:

| Action                       | Colors found                                                                                                                              | Examples                                                                                                                                                                                                 |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Cancel                       | `btn-secondary` / `btn-outline-secondary` / `btn-outline-dark`                                                                            | `resources/js/user/AiProviderSettings.vue:255` vs `:272` (**same file, two Cancel buttons, two different colors**) vs `resources/js/transactions/components/form/TransactionFormStandard.vue:508`        |
| Delete/Destroy               | `btn-danger` (majority) / `btn-outline-danger`                                                                                            | `resources/js/user/AiProviderSettings.vue:284` vs `resources/js/user/ApiTokenManager.vue:72`, `resources/js/user/TwoFactorSettings.vue:47`                                                               |
| Edit (pencil icon)           | `btn-primary` (plain edit) / `btn-success` ("edit + insert schedule instance")                                                            | `resources/js/shared/lib/datatable/index.js:64` vs `:88`                                                                                                                                                 |
| Add/New                      | `btn-success` (page-level "New X" launcher, majority) / `btn-primary` (modal "Add" submit) / `btn-sm btn-outline-success` (inline accept) | index pages e.g. `resources/views/payees/index.blade.php:31` vs `resources/js/currency-rates/components/CurrencyRateModal.vue:78` vs `resources/js/transactions/components/form/TransactionItem.vue:107` |
| Info icon (`fa-info-circle`) | `text-primary` (transaction forms) / `text-info` (settings pages)                                                                         | `resources/js/transactions/components/form/TransactionFormStandard.vue:26,178` vs `resources/js/user/AiBehaviorSettings.vue:15`                                                                          |

Also found: `btn-default` — a Bootstrap 3 class not present in this theme at all — used for two unrelated buttons: `resources/views/reports/schedule.blade.php:92-93` ("Clear selection"/"Select all") and as several modals' dismiss button class (§4). Context-menu icon colors (Edit=primary, Delete=danger, View=success/info) are otherwise fairly consistent across `resources/js/account/index.js`, `resources/js/payee/index.js`, `resources/js/investments/index.js`, with one outlier at `resources/js/reports/schedules.js:284-286`.

### Decisions made for this review (confirmed with the user)

- **Cancel / secondary-action standard → `btn-secondary`** (matches the Blade CRUD form majority). Applies to card-footer Cancel buttons and modal dismiss buttons alike.
- **Info icon standard → `text-info`** (matches Bootstrap's own semantic color naming).
- **Edit vs. "edit + insert instance" → kept intentionally distinct** (`btn-primary`/`text-primary` for plain edit, `btn-success`/`text-success` reserved for the heavier "edit and create an instance" action). Not a bug, no change needed.
- **Delete/Destroy standard → `btn-danger`** (clear majority, no ambiguity — decided without needing to ask).
- **Add/New: two legitimate categories, not one action** — `btn-success` for page-level "New X" _launcher_ buttons (navigates to a create form/page), `btn-primary` for an in-place "Add" _submit_ button inside a modal (functionally a Save, should follow the Save color). The `btn-sm btn-outline-success` inline-accept variant (`TransactionItem.vue:107`) is a different, compact micro-interaction (accepting a suggestion inline) and is left as a reasonable exception rather than forced into either bucket.

## 4. Modal dialog behavior

- Close (X) button is present and consistent everywhere: `btn-close` + `data-coreui-dismiss="modal"` in the `.modal-header`, e.g. `resources/js/transactions/components/form/ModalStandard.vue:9-14`, `resources/js/currency-rates/components/CurrencyRateModal.vue:11-15`. No divergence here.
- Footer dismiss-button label/class is **not** consistent:
  - `"Close"` + `btn-default` (invalid class, see §3): `resources/js/reports/components/BudgetQuickView.vue:54`, `resources/js/reports/components/BudgetForm.vue:151-157`, `resources/js/payee/components/PayeeForm.vue:154-159`, `resources/js/category-learning/components/CategoryLearningForm.vue:111-116`.
  - `"Cancel"` + `btn-secondary`: `resources/js/currency-rates/components/CurrencyRateModal.vue:69-75`, `resources/js/investment-price/components/InvestmentPriceModal.vue:70-81`, `resources/js/ai-documents/components/AiDocumentUploadForm.vue:161-168`.
  - `"Cancel"` + `btn-outline-secondary`: `resources/js/user/ApiTokenManager.vue:244-251`.
- `resources/js/transactions/components/display/Modal.vue:30-37` (transaction quickview) and `ModalStandard.vue`/`ModalInvestment.vue` don't have a standard footer at all — Save/Cancel are embedded in the body via a sub-component instead.
- **Backdrop/Esc dismissal — the one behavioral (not just cosmetic) risk found in this review.** Only one modal in the app restricts backdrop-click/Esc dismissal, and it does so imperatively at runtime: `resources/js/user/ApiTokenManager.vue:351-357` (`this.createModal._config.backdrop = dismissible ? true : 'static'`), specifically to protect a one-time-shown API token. Every other modal — including forms with unsaved input like `BudgetForm.vue`, `PayeeForm.vue`, `CategoryLearningForm.vue` — can be dismissed via backdrop-click or Esc at any time, silently discarding whatever was entered, with no confirmation.
- No "confirm before closing if dirty" pattern exists for any modal, anywhere.

**Suggested standard**: dismiss button labeled `"Cancel"` with the agreed `btn-secondary` class (retire the `"Close"`/`btn-default` variant entirely — it uses a class that isn't even styled in this theme). The backdrop/Esc data-loss gap is flagged as a priority item in `tasks.md`, separate from the pure cosmetic unification.

## 5. Native `confirm()` vs. SweetAlert2

- 14 sites still use native `confirm()`: `resources/js/shared/lib/datatable/index.js:116`, `resources/js/categories/merge.js:166,173`, `resources/js/payee/merge.js:117,124`, `resources/js/investment-groups/index.js:60`, `resources/js/account-groups/index.js:60`, `resources/js/categories/index.js:293`, `resources/js/currencies/index.js:126`, `resources/js/investments/index.js:135`, `resources/js/app.js:144`, `resources/js/transactions/components/form/TransactionFormInvestment.vue:1201`, `resources/js/transactions/components/form/TransactionFormStandard.vue:1247,1443`.
- 28 sites use `Swal.fire`. `resources/js/investments/index.js` uses **both** mechanisms for the _same_ delete action in two different code paths: `:135` (`confirm()`, row-menu) vs. `:274` (`Swal.fire`, context-menu).
- Within the Swal calls, ~22/28 match the reference pattern (`icon:'warning'`, `buttonsStyling:false`, `customClass:{confirmButton:'btn btn-danger', cancelButton:'btn btn-outline-secondary ms-3'}` — note: per the Cancel-color decision above, the cancel class here should become `btn-outline-secondary` → `btn-secondary` too, for consistency with §3). 6 diverge:
  - Missing `buttonsStyling`/`customClass` entirely (falls back to default un-Bootstrapped SweetAlert2 styling): `resources/js/transactions/components/form/TransactionItemContainer.vue:303-312,334-341`.
  - `btn-warning` confirm button paired with `icon:'warning'` for a non-destructive confirm: `resources/js/investments/components/display/TransactionHistoryCard.vue:115-128` — reasonable as-is (it isn't destructive, red would overstate it); not treated as drift.
  - `icon:'question'` + `btn-primary` variant for a non-destructive "overwrite name" prompt: `resources/js/user/GoogleDriveSettings.vue:1217-1236` — also reasonable given it isn't a delete.
  - No icon set at all: `resources/js/user/TwoFactorSettings.vue:338-352` (password prompt for disabling 2FA) — this one is a genuine gap, should set `icon:'warning'` like every other destructive/security confirm.
  - `html` payload instead of `text`: `resources/js/import/components/FileImportProfileManager.vue:739` — fine given it needs to embed a formatted list, not treated as drift.
- No shared `confirmDelete()` helper exists despite ~15 call sites reproducing the identical options object verbatim, e.g. `resources/js/user/AiProviderSettings.vue:593-604`, `resources/js/user/InvestmentProviderSettings.vue:518-532`, `resources/js/user/GoogleDriveSettings.vue:1425-1440`.

**Suggested standard**: `Swal.fire` everywhere (retire native `confirm()`), via one shared confirm helper carrying the agreed defaults (`icon:'warning'`, `buttonsStyling:false`, `confirmButton:'btn btn-danger'`, `cancelButton:'btn btn-secondary'` per the Cancel decision above), with `icon`/`confirmButton` overridable for the legitimate non-destructive cases already identified.

## 6. Toast vs. Bootstrap notification (reload-based)

Two non-interoperating systems coexist:

- **Toast** (`resources/js/shared/lib/toast/index.js`) — client-side, shown instantly, no navigation. Used consistently everywhere it's used — confirmed no rogue reimplementations; ~35 files import it correctly.
- **Bootstrap flash notification** (`resources/js/shared/ui/notifications/BootstrapNotificationContainer.vue`, fed by `app/Components/FlashMessages.php` session flash) — only ever appears **after** a redirect/reload, either a classic server-side POST-redirect, or a client-side `storeNotification()` call queued into `localStorage` right before `window.location.href = ...`.

The split is not by feature, it's by which code path happens to fire it, producing same-entity inconsistency:

- **Investments**: create/edit → synchronous Blade form submit → flash banner after reload (`resources/views/investments/form.blade.php`, `app/Http/Controllers/InvestmentController.php:111,166`). Delete → AJAX + instant toast (`resources/js/investments/index.js:135-160,274-300`).
- **AI Documents**: delete-from-table → instant toast (`resources/js/ai-documents/components/AiDocumentTable.vue:391-431`). Delete-from-viewer, the _same_ action from a different screen → `storeNotification()` + redirect → flash banner (`resources/js/ai-documents/components/AiDocumentViewer.vue:835-870`).
- **Transactions**: create/edit → `storeNotification()` + navigate → flash banner (`resources/js/transactions/components/form/ContainerStandard.vue:181-221`, `ContainerInvestment.vue:157-193`). Delete (any list view) → instant toast, no reload.
- **Currencies/Tags**: delete still goes through the legacy native-`confirm()` + hidden-form-POST + full reload + flash banner path (`resources/js/shared/lib/datatable/index.js:112-128`), unlike every other entity's delete (AJAX + toast). This is the specific outlier tracked as a task in `tasks.md`.
- `app/Http/Controllers/TransactionController.php:133-151` (`destroy`, registered via `Route::resource('transactions', ...)` in `routes/web.php:99`, producing the `transactions.destroy` web route) still implements a full flash+redirect delete. Confirmed via repo-wide search: **nothing in `resources/views` or `resources/js` references this route** — all live transaction-delete call sites use `api.v1.transactions.destroy` instead. This is dead backend code, tracked as its own task.

**Decision (confirmed with the user)**: the broader Create/Edit-still-uses-flash-redirect pattern (Investments, Transactions, etc.) is logged as a distinct, larger **future phase** in `tasks.md` rather than scoped into this pass — it changes navigation behavior, not just visual styling, and deserves its own sign-off.

## 7. Foldable / closable cards

Four separate implementations, no shared abstraction:

1. **CoreUI collapse + CSS-rotated chevron** (majority pattern): `class="collapse-control"` + `data-coreui-toggle="collapse"` + `data-coreui-target="#id"` + `<i class="fa fa-angle-down">`. Rotation is pure CSS via `resources/sass/_custom.scss:190-200`. Examples: `resources/js/currency-rates/components/CurrencyRateActions.vue:4-9`, `resources/views/account-groups/index.blade.php:20-26`, `resources/views/categories/index.blade.php:13-19`.
2. **Hand-rolled Vue-state chevron swap**, no Bootstrap collapse involved, different icon family (`fa-chevron-up/down` vs. `fa-angle-down`): `resources/js/import/components/ImportDraftTable.vue:157-163`, driven by a component-local `expandedRows` Set.
3. **Server-persisted "Dismiss" labeled button**, no chevron/X: `resources/js/dashboard/components/widgets/OnboardingCard.vue:61-69` (Vue-reactive, fine) and `resources/js/dashboard/components/widgets/PayeeCategoryRecommendation.vue:42-50,126` — this one hides itself with raw jQuery `$('#widgetPayeeCategoryRecommendation').hide()` instead of Vue reactivity, an outlier even within this Options-API codebase.
4. **Session-only corner-X dismiss**, copy-pasted near-identically across three files instead of factored into one component: `resources/js/import/components/ScheduleCandidatesPanel.vue:19-23`, `resources/js/import/components/DuplicateCandidatesPanel.vue:15-19`, `resources/js/import/components/RelatedAiDocumentsPanel.vue:16-20`, each emitting a `dismiss` event handled independently in `resources/js/import/components/ImportDraftTable.vue:582`.

**Suggested standard**: pattern 1 (CoreUI collapse + chevron) is the dominant, already-shared-infrastructure pattern — converge pattern 2 onto it where the underlying interaction is genuinely "collapse", and replace the jQuery `.hide()` in pattern 3 with Vue-reactive `v-if`. Pattern 4's three near-identical files are a good candidate for one shared component (see §8).

## 8. Button icon layout

**Reference pattern for icon+text buttons, per the user's explicit correction: `fa me-1 {icon}`** (class order: base `fa`, spacing `me-1`, then the specific icon class — e.g. `class="fa me-1 fa-save"`). Note this differs from the pattern originally read off `AiProviderSettings.vue` (`fa fa-fw {icon} me-1`) — the standard going forward drops `fa-fw` and reorders `me-1` before the icon class; `AiProviderSettings.vue` itself will need updating to match, not just the divergent files below.

Variants currently in the codebase, all of which need to converge on the corrected standard:

- `fa fa-fw {icon} me-1` (the original reference, now superseded): `resources/js/user/AiProviderSettings.vue:249,263,272,284`.
- No `fa-fw`, `me-1` after icon: `resources/js/user/GoogleDriveSettings.vue:675`, `resources/js/user/AiBehaviorSettings.vue:646`.
- No spacing class at all, relies on a line break/whitespace in the template: `resources/js/user/GoogleDriveSettings.vue:707-711,731-733`, `resources/js/transactions/components/display/ActionButtonBar.vue:14-15`, `resources/js/user/TwoFactorSettings.vue:94-97`, `resources/js/user/ApiTokenManager.vue:138-142`.
- Icon immediately before text, single literal space, no class: `resources/js/transactions/components/display/ActionButtonBar.vue:51,59`.
- `<span>` instead of `<i>`, and a different icon name for the same "Save" concept (`fa-floppy-disk` vs. `fa-save` used everywhere else): `resources/js/transactions/components/form/TransactionFormStandard.vue:555`, `TransactionFormInvestment.vue:506`.
- `me-2` + reversed class order + `fa-solid` prefix, for the repeated context-menu trigger icon: `resources/js/account/index.js:30`, `resources/js/payee/index.js:419`, `resources/js/reports/schedules.js:192`, `resources/js/investments/index.js:28`, `resources/js/ai-documents/components/AiDocumentTable.vue:516`.
- The same "+New" button has `fa-fw` on some index pages (`resources/views/tags/index.blade.php:34`, `resources/views/investments/index.blade.php:32`) and not others (`resources/views/payees/index.blade.php:36`, `resources/views/accounts/index.blade.php:35`, `resources/views/categories/index.blade.php:35`).
- All plain Blade CRUD forms (categories, accounts, tags, currencies, investments, investment-groups, account-groups, payees) have **no icon at all** on Save/Cancel, e.g. `resources/views/categories/form.blade.php:168-169` — a bigger structural gap than a class-order mismatch, listed separately in `tasks.md`.

## 9. Spinners & loading placeholders

Three coexisting paradigms for conceptually the same "busy" state:

- **Bootstrap `spinner-border spinner-border-sm`** — import/AI-document family only: `resources/js/ai-documents/components/AiDocumentUploadForm.vue:176`, `resources/js/currency-rates/components/CurrencyRateModal.vue:84`, `resources/js/investment-price/components/InvestmentPriceModal.vue:78,91`, `resources/js/import/components/ProfileCreationWizard.vue:158,696`.
- **FontAwesome `fa-spinner fa-spin`** swapped into a button icon — used almost everywhere else for the same "button in busy/submitting state" concept: `resources/js/user/AiProviderSettings.vue:263`, `resources/js/user/GoogleDriveSettings.vue:693,708`, `resources/js/user/InvestmentProviderSettings.vue:224,240,259`, plus all legacy jQuery DataTables row actions in `resources/js/shared/lib/datatable/index.js:31,43,79`.
- **Bootstrap `placeholder`/`placeholder-glow` skeletons** — used consistently for dashboard-widget data-loading states: `resources/js/dashboard/components/widgets/CategoryWaterfall.vue:36-37`, `AccountBalance.vue:14-18`, `AiDocumentSummary.vue:11-15`, `ScheduleCalendar.vue:18-19`, and the reporting-canvas widgets. This is the most internally consistent pattern found — but `ScheduleCalendar.vue` also uses an FA spinner (`:446`) for a _different_ loading moment in the same component, so even this one isn't fully unified end-to-end.
- Some Save buttons hide their icon on busy (`v-show="!form.busy"`) with **no spinner substituted at all**: `resources/js/user/GoogleDriveSettings.vue:675`, `resources/js/transactions/components/form/TransactionFormStandard.vue:555` — the button just loses its icon during submit, with no busy cue at all.

**Suggested standard**: these three actually map to two legitimately distinct situations, not one — (a) **button busy-state** → `fa-spinner fa-spin` swapped for the button's normal icon (already the majority pattern; migrate the `spinner-border` outliers onto it, and add a spinner to the buttons currently losing their icon with no replacement); (b) **section/content data-loading** → `placeholder`/`placeholder-glow` skeleton (already the majority pattern for this case; fix the one `ScheduleCalendar.vue` case that mixes both for different moments in the same component).

## 10. Technical layer — shared vs. duplicated components

- **Whole feature module duplicated near-verbatim**: `CurrencyRateTable.vue`/`Manager.vue`/`Overview.vue` vs. `InvestmentPriceTable.vue`/`Manager.vue`/`Overview.vue` — same DataTables config shape, same button HTML strings, same `confirmDelete`/delete methods, differing only in field names. `resources/js/currency-rates/components/CurrencyRateTable.vue:2-11` vs. `resources/js/investment-price/components/InvestmentPriceTable.vue:2-11`.
- **Delete-confirmation `Swal` block** copy-pasted near-identically across ≥9 files (§5).
- **Native-confirm + jQuery-ajax delete** copy-pasted 3 ways (`resources/js/categories/index.js`, `resources/js/account-groups/index.js`, `resources/js/investment-groups/index.js`) despite a shared helper already existing (`resources/js/shared/lib/datatable/index.js:112`, `initializeDeleteButtonListener`, used correctly by `resources/js/tags/index.js:103` and `resources/js/currencies/index.js:120`) — the other three reimplement the same ~35-line block inline instead of extending the shared one.
- **No shared "card + DataTable" wrapper** exists in `resources/js/shared/ui/` — every table component hand-rolls its own card/header/body markup. By contrast, `resources/js/shared/ui/date/DateRangeFilterCard.vue` _is_ a working shared component, reused by four different features (`FindTransactions.vue`, `CurrencyRateManager.vue`, `AiDocumentManager.vue`, `InvestmentPriceManager.vue`) — proof the pattern is achievable here, just not applied to the plain list-card case.
- **No shared modal-form skeleton**: `resources/js/payee/components/PayeeForm.vue`, `resources/js/category-learning/components/CategoryLearningForm.vue`, `resources/js/reports/components/BudgetForm.vue` each re-declare the same modal-header/body/new-vs-edit-title boilerplate around the (legitimately shared, third-party) `vform` `AlertErrors`/`AlertSuccess` components.
- **Toast helper usage is fully unified** — confirmed no exceptions; every one of ~35 consumers imports `resources/js/shared/lib/toast/index.js` correctly. No action needed.

---

## 11. UI modernization — design-token refresh

Separate from the pattern-unification findings above: the app currently reads as dated not because of any deliberate design choice, but because almost none of CoreUI's own customization surface has been used. Checked directly:

- `resources/sass/_variables.scss` mirrors the entire Bootstrap/CoreUI Sass variable surface (~1700 lines) — **well over 90% of it is commented out**, i.e. left at stock defaults. The only live overrides are CoreUI's own stock gray palette (`$gray-base: #3c4b64`, `$gray-100`-`$gray-900` — these are CoreUI's own default brand grays, not a custom choice), a `$font-size-base: 0.875rem` density reduction, and two technical fixes (`$border-color-translucent`, `$gray-800-dark`/`$gray-900-dark`) needed to stop dark-mode contrast math from breaking.
- `resources/sass/_custom.scss` (492 lines) is almost entirely reactive patches — comments like "hardcoded 0.05 box-shadow opacity to something visible" and dark-mode `.card`/`.card-header`/`.card-footer` background/border fixes (`:332-337`) — not a deliberate aesthetic pass.
- `$primary` is untouched stock Bootstrap blue `#0d6efd` — one of the most recognizable "unstyled framework" signals there is.
- Typeface is Source Sans Pro (`resources/sass/app.scss`, Google Fonts `@import`), a 2012-era Adobe font associated with older enterprise/gov UI. Bootstrap 5.3's own native `system-ui` stack is sitting **already written, just commented out** at `_variables.scss:454-459`.
- Dark mode is already fully wired (header toggle button with a moon icon, `data-coreui-theme` attribute + `localStorage` persistence — `resources/views/template/layouts/page.blade.php:101-103`, `resources/views/template/master.blade.php:35-38`) — a genuinely modern feature already in place, just not given a deliberate palette pass (see the `_custom.scss` patches above).

### Concrete levers (all `$variable`/`--cui-*` overrides — no framework ejection)

| Lever | Current state | Direction |
|---|---|---|
| Typography | Source Sans Pro webfont | `system-ui` native stack (free — already commented in the file) or Inter webfont (same `@import` mechanism, plus genuinely useful tabular figures for this app's numeric tables) |
| Primary color | Stock Bootstrap blue `#0d6efd` | A more contemporary hue (e.g. a refined indigo `~#4F46E5`) — semantic roles (success/danger/warning/info) stay as-is, only the hues within `$primary`/`$grays` move |
| Gray scale | CoreUI's stock blue-tinted grays (`$gray-base: #3c4b64`) | A more neutral scale, plus a lighter off-white page background for page-vs-card contrast without heavy borders |
| Border radius | Bootstrap default (~4px), cascades to every card/button/input/modal/dropdown/badge | ~8px base / ~12px cards / ~6px small controls — highest-leverage single change |
| Elevation | Mixed defaults: `$btn-box-shadow` still carries Bootstrap's inset-highlight bevel; cards rely on a plain border, no `$card-box-shadow` set | A deliberate fork to pick, not a default to inherit: **flat** (hairline border, no shadow) vs. **soft-elevated** (very soft ambient shadow, no border) |
| Density/spacing | `$font-size-base` already reduced to 14px with no compensating spacing increase | **Tension worth naming**: this is a finance app with dense tabular data — generic "airy SaaS" advice (bigger `$font-size-base`, lots of whitespace) actively works against scanning transaction/balance tables. Recommendation: keep 14px body text, open up structural chrome (`$card-spacer-*`) modestly rather than uniformly loosening everything |
| Dark-mode surfaces | Only bug-fixed where visibly broken | A deliberate near-black slate surface hierarchy (page vs. elevated card tone), once a light-mode direction is picked |

### Testing candidates

Three ready-to-try, **complete, build-verified** `_variables.scss` replacement files were prepared in `variable-candidates/` in this folder — each is the full file (not a delta), and each was swapped in and run through `vendor/bin/sail npm run build` successfully before being committed there (see that folder's `README.md` for the exact testing procedure):

- **Candidate A — "Flat & Native"**: system-ui font, flat elevation (hairline border, no card shadow), 8/12/6px radius scale.
- **Candidate B — "Soft & Branded"**: Inter webfont, soft ambient card shadow (no border), same radius scale as A.
- **Candidate C — "Minimal"**: palette refresh + a smaller 6px radius bump + flattened button bevel only — typography, spacing, and card elevation left untouched. A low-risk baseline to compare A/B against.

All three share the same color-palette refresh (indigo primary, neutral grays) so the comparison isolates the genuinely subjective choices (typeface, elevation style, how bold a radius change) rather than conflating them with the less controversial palette move. None of the three touch dark-mode surface colors — that's follow-up work once a light-mode direction is chosen (see `tasks.md` T-19).

### Sequencing relative to the unification tasks

This is layered underneath, not alongside, the pattern-unification work in §1-§10: a global token change (colors, radius, shadow, spacing) cascades automatically to every component through Bootstrap's CSS-variable-driven cascade, with no per-file edits required. The per-component unification tasks (T-04 through T-13 in `tasks.md`) are mostly about *which existing semantic class* a given button/icon should use — independent of what that class renders as. The exception is the handful of tasks that involve real visual/spacing judgment calls (card-header layout, card-footer layout, foldable-card polish, spinner choice) — those are more efficient to do *after* the token direction lands, so they're reviewed once against the final look rather than twice. See the dependency annotations in `tasks.md`'s T-19 and the affected tasks.

## Summary of decisions made during this review

| Divergence                           | Decision                                          | Basis                                                        |
| ------------------------------------ | ------------------------------------------------- | ------------------------------------------------------------ |
| Cancel/secondary button color        | `btn-secondary`                                   | User choice — matches Blade CRUD form majority               |
| Info icon color                      | `text-info`                                       | User choice — matches Bootstrap semantic naming              |
| Edit vs. edit+insert-instance color  | Keep distinct (`primary` / `success`)             | User choice — different actions, not drift                   |
| Delete/destroy button color          | `btn-danger`                                      | Clear majority, no ambiguity                                 |
| "New X" vs. modal "Add" submit color | `btn-success` (launcher) / `btn-primary` (submit) | Reasoned split — different semantics, not one action         |
| Icon+text button layout              | `fa me-1 {icon}`                                  | User's explicit correction to the original reference pattern |
| Create/Edit flash-redirect → toast   | Logged as a separate future phase                 | User choice — behavioral change, needs its own scoping       |
| UI modernization / design tokens     | Added to scope, not yet decided                   | User choice — three candidate palettes prepared in `variable-candidates/` for a live before/after comparison before any value is locked in |

See `tasks.md` in this folder for the actionable breakdown.
