# Feature: Monthly Category Breakdown Tab

## Summary

Adds a new "Monthly breakdown" tab to the **Transactions by criteria** report (`/reports/transactions`). This tab displays a table showing expense/income amounts broken down by category and month, similar to a spreadsheet-style budget overview.

Additionally, a collapsible sidebar toggle is added to give the table more horizontal space when needed.

## Motivation

Users often want to see how their spending in each category changes month-to-month and compare it against averages. The existing tabs (Summary, List, Timeline charts, Category charts) provide aggregate or per-transaction views, but none offer a category-by-month matrix with section grouping, subtotals, and deviation highlighting.

## Features

### Monthly Breakdown Table
- Categories are **dynamically grouped by their parent category**
  - Each parent category becomes its own section with a colored header
  - Section titles come directly from parent category names stored in the database
  - Works automatically with both default and custom user-created categories
  - Categories without a parent appear in an "Other expenses" section
- Sections are sorted by total amount (descending) and assigned rotating color classes
- Each section has a subtotal row
- A grand summary shows total expenses, total income, and balance per month
- Total and average-per-month columns on the right

### Deviation Highlighting
- Cells are color-coded when they deviate from the category's monthly average:
  - **Red shades** (light to dark): 5%, 10%, 15% above average
  - **Green shades** (light to dark): 5%, 10%, 15% below average
- Only applies when there are at least 3 months of data for a category
- Zero-value months are excluded from the average calculation

### Percentage View
- A toggle switch above the table switches between absolute amounts and percentages
- Percentages show each category's share of total monthly expenses

### Clickable Drill-Down
- Every non-zero amount in the table is a clickable link
- Clicking navigates to the same report page with filters pre-set to the specific month and category
- Subtotal row links include all category IDs from that section
- Category names link to the category edit page (`/categories/{id}/edit`)

### Collapsible Sidebar
- A toggle button (`<<` / `>>`) in the card header collapses/expands the filter sidebar
- When collapsed, the content area expands to full width, giving the table more room

## Category Grouping

Categories are grouped into sections based on the **parent-child category hierarchy** already present in the database:

- The `Category` model eager-loads its parent (`protected $with = ['parent']`), so `item.category.parent` is always available in transaction data
- Category names are translated at creation time (via `__()` in `CreateDefaultAssetsForNewUser`), so the database stores localized names directly (e.g., "Salary", "Fizetés")
- Each unique parent category name becomes a section title — no translation key lookup needed
- Custom user-created parent categories automatically create their own sections
- Income vs. expense detection uses the transaction's `transaction_type_id` (deposit = income), not section titles

This approach replaces the earlier hardcoded section definitions and eliminates the need for translation key matching.

## Performance

### Two-Level Caching
- **Transaction cache** (~4MB, sessionStorage): Cached by the parent `FindTransactions` component to avoid re-fetching raw transaction data
- **Breakdown cache** (~14KB, sessionStorage): The monthly breakdown component caches its computed table data (category aggregations, section structure) separately
- The parent component exposes `hasBreakdownCache()` to check if breakdown cache exists for the current filter set — this allows skipping the expensive 4MB transaction cache parse entirely when the user returns to the tab
- Cache keys include all filter parameters: `date_from`, `date_to`, `accounts[]`, `categories[]`, `payees[]`, `tags[]`

## Technical Details

### Files Created
- `resources/js/components/ReportingWidgets/ReportingCanvas-FindTransactions-MonthlyBreakdown.vue` — New Vue 3 component

### Files Modified
- `resources/js/components/FindTransactions.vue` — Added tab, sidebar toggle, component registration, breakdown cache key helpers
- `lang/en.json` — English translation keys
- `lang/hu.json` — Hungarian translations
- `lang/fr.json` — French translations
- `lang/pl.json` — Polish translations

### No Backend Changes
All data processing happens client-side using the existing `transactions` array already fetched by the parent component. No new API endpoints or database changes are required.

### Architecture
The component follows the same pattern as existing reporting tabs:
- Receives `transactions` (Array) and `busy` (Boolean) props
- Uses Vue 3 computed properties for data aggregation
- Uses `toFormattedCurrency()` and `__()` helpers from the existing codebase
- Scoped CSS with rotating section color palette (`s-section-0` through `s-section-7`)

## Testing

1. Navigate to `/reports/transactions`
2. Set a date range spanning multiple months
3. Click "Update" to load transactions
4. Click the "Monthly breakdown" tab
5. Verify:
   - Categories are grouped into sections named after their parent categories
   - Custom user-created parent categories create their own sections
   - Categories without parents appear in "Other expenses"
   - Subtotals and grand totals are correct
   - Deviation colors appear for outlier months
   - Percentage toggle switches the display mode
   - Clicking an amount navigates to filtered transactions
   - Clicking a category name opens the category edit page
   - Sidebar collapse/expand works properly
   - Table scrolls horizontally when many months are shown
   - Returning to the tab loads from breakdown cache without re-parsing transactions
