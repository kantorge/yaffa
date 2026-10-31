# UI conventions: standard actions, icons and colors

Use these for new UI and align existing UI when touching it. Icons are Font Awesome (`fa fa-*`); colors are Bootstrap/CoreUI button variants. Buttons always carry a `title` (and `aria-label` when icon-only). Translation keys are the English label.

| Action | Label | Icon | Variant | Notes |
| --- | --- | --- | --- | --- |
| Create (new X) | "New X" | `fa-plus` (specific icon allowed for entity: `fa-cart-plus` transaction, `fa-line-chart` investment transaction) | `success` | |
| Edit | "Edit" | `fa-edit` | `primary` | |
| Clone | "Clone" | `fa-clone` | `primary` | |
| Save as template | "Save as template" | `fa-bookmark` | `primary` | |
| Delete | "Delete" | `fa-trash` | `danger` | Always behind `confirmDelete()` |
| Open / view details | "Open" / "View details" | `fa-search` (modal "Open"), `fa-info-circle` (more details tab) | `success` / `outline-primary` | |
| Mark as checked / confirm | "Mark ... as checked" | `fa-check` (`fa-check-double` for all) | `success` | |
| Skip schedule instance | "Skip instance" | `fa-fast-forward` | `warning` | |
| Enter schedule instance | "Enter instance" | `fa-calendar-plus` | `success` | |
| Reprocess | "Reprocess ..." | `fa-repeat` (`fa-spin` while busy) | `warning` | |
| Dismiss | "Dismiss ..." | `fa-ban` | `secondary` | |
| Reload / update | "Reload" / "Update" | `fa-refresh` / `fa-sync-alt` | `primary` | |
| Upload | "Upload ..." | `fa-upload` | `outline-success` | |
| Close (modal) | "Close" | none | `secondary` | |

## Patterns

- **Action cards**: one list row per action, label on the left, icon button on the right. The whole row is clickable (see `.list-group-item:has(> .btn:last-child)` in `resources/sass/_custom.scss`), so keep exactly one trailing `.btn` per row.
- **Responsive buttons**: below `md` show only the icon; the text label is `<span class="d-none d-md-inline">` (see `ActionButtonBar.vue`).
- **Missing data**: use the global `.not-set` class (muted + italic) with the "Not set" / "Not available" text. Do not combine `text-muted` and italic utilities by hand.
- **Mobile**: avoid repeating labels for self-explanatory values; use an icon (e.g. `fa-tag`, `fa-comment`) instead of a `dt` label.

## Known inconsistencies

- Some "new" buttons use `btn-outline-*` (documents list) while the rest use solid variants.
