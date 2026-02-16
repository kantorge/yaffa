# Feature: Monthly Category Breakdown Tab

## Summary

Adds a new "Monthly breakdown" tab to the **Transactions by criteria** report (`/reports/transactions`). This tab displays a table showing expense/income amounts broken down by category and month, similar to a spreadsheet-style budget overview.

Additionally, a collapsible sidebar toggle is added to give the table more horizontal space when needed.

## Motivation

Users often want to see how their spending in each category changes month-to-month and compare it against averages. The existing tabs (Summary, List, Timeline charts, Category charts) provide aggregate or per-transaction views, but none offer a category-by-month matrix with section grouping, subtotals, and deviation highlighting.

## Features

### Monthly Breakdown Table
- Categories are grouped into configurable sections:
  - **Daily living expenses** (food, clothing, healthcare, fuel, entertainment, etc.)
  - **Fixed obligations** (rent, utilities, insurance, loans, taxes, etc.)
  - **Savings and investments** (mortgage overpayments, etc.)
  - **Income** (salary, bonuses, freelance, government benefits, etc.)
  - **Other** (any categories not matching the above)
- Each section has a colored header and subtotal row
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

### Collapsible Sidebar
- A toggle button (`<<` / `>>`) in the card header collapses/expands the filter sidebar
- When collapsed, the content area expands to full width, giving the table more room

## Category Matching

Categories are matched to sections using translation keys from `default_assets.categories.*`. This means:
- Default yaffa categories (created during user registration) are automatically matched regardless of language
- Custom user-defined categories that don't match any known key appear in the "Other" section
- No hardcoded category names in any specific language

## Technical Details

### Files Created
- `resources/js/components/ReportingWidgets/ReportingCanvas-FindTransactions-MonthlyBreakdown.vue` - New Vue 3 component

### Files Modified
- `resources/js/components/FindTransactions.vue` - Added tab, sidebar toggle, component registration
- `lang/en.json` - English translation keys
- `lang/hu.json` - Hungarian translations
- `lang/fr.json` - French translations

### No Backend Changes
All data processing happens client-side using the existing `transactions` array already fetched by the parent component. No new API endpoints or database changes are required.

### Architecture
The component follows the same pattern as existing reporting tabs:
- Receives `transactions` (Array) and `busy` (Boolean) props
- Uses Vue 3 computed properties for data aggregation
- Uses `toFormattedCurrency()` and `__()` helpers from the existing codebase
- Scoped CSS for styling

## Screenshots

_To be added after testing_

## Testing

1. Navigate to `/reports/transactions`
2. Set a date range spanning multiple months
3. Click "Update" to load transactions
4. Click the "Monthly breakdown" tab
5. Verify:
   - Categories are grouped into sections with colored headers
   - Subtotals and grand totals are correct
   - Deviation colors appear for outlier months
   - Percentage toggle switches the display mode
   - Clicking an amount navigates to filtered transactions
   - Sidebar collapse/expand works properly
   - Table scrolls horizontally when many months are shown
