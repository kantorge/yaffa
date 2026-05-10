# Investment Timeline

## Feature Name

Investment Position Timeline Review

## Feature Summary

This report gives users a time-based view of their investments so they can review when positions were active, how long they remained open, and what their latest quantity and estimated value look like. It acts as the reporting bridge between everyday portfolio maintenance and long-term financial planning.

Similarly, if planned or scheduled transactions are captured in the transaction domain, then this report returns a timeline-oriented view of investment positions based on the same underlying data.

The feature belongs under Reports because it is review-focused and analytical. It does not manage investment definitions or pricing configuration directly.

## Target User

- Primary:
  Advanced or detail-oriented users reviewing portfolio structure over time and looking for long-duration or currently open positions.

- Secondary:
  Users who organize holdings into investment groups and want a filtered visual overview rather than a plain asset list.

## User Problem

- Investment lists show holdings, but they do not clearly communicate planned duration or time overlap.
- Users need to know which positions are still open, which are historical, and how different groups compare over time.
- Users need a quick visual way to review quantity and estimated end value together.

## User Value / Benefit

### Functional Benefits

- Shows investment positions on a timeline instead of only in list form.
- Supports filtering by active state, open state, investment group, and free-text search.
- Combines timeline position with quantity and estimated end value in tooltips.

### Conceptual Benefits

- Helps users think about investments as part of a longer financial journey, not only as current balances.
- Makes holding duration and portfolio evolution easier to understand during review.

## Technical Description

- The frontend fetches a timeline-oriented investment dataset from the investment API rather than a dedicated report-specific controller method.
- Each item is rendered in a Gantt-like chart where the position spans from its start date to its end date.
- Estimated value is derived client-side from quantity multiplied by the latest known price.
- The chart is localized for dates and number formatting and uses the user's end date as the maximum visible planning horizon.

## Inputs

- Investment timeline dataset
- Investment groups
- Active and open toggle filters
- Search text entered by the user
- Localization and user end-date settings

## Outputs

- Timeline chart of investment positions
- Group-filtered portfolio overview
- Tooltip details showing quantity and estimated end value

## Core Logic / Rules

- Active filtering narrows the dataset to currently active or inactive investments.
- Open filtering compares the end date with today to distinguish open versus closed positions.
- Investment group filtering uses the selected group-tree nodes as the allowed set.
- Free-text search matches against the investment name.
- The current month is visually highlighted to anchor the user's attention in the present.

## User Flow

1. User opens Investment Timeline from the Reports menu.
2. YAFFA loads the timeline data and renders the chart.
3. User filters by active state, open status, investment group, or search text.
4. User hovers over timeline items to inspect quantity and estimated end value.
5. User reviews portfolio evolution and open positions in a time-based context.

## Edge Cases / Constraints

- The view is only as accurate as the underlying investment timeline and last-price data.
- Estimated value is an approximation based on latest known price rather than a full valuation engine.
- This view is analytical and may not answer detailed performance questions such as return attribution on its own.

## Dependencies

- Models and data:
  Investments, investment groups, quantity data, latest price data, and timeline dates

- Services and helpers:
  Investment timeline API, investment group tree helper, localization helpers, and chart rendering utilities

- Frontend components:
  Filter sidebar, group tree, search field, chart placeholder, and amCharts timeline rendering

## Frontend Interaction

- The report starts with a loading placeholder until chart data is ready.
- Users refine the view through radio toggles, a searchable filter box, and a group tree.
- The output is visual-first and optimized for scanning holdings across time.

## Documentation Boundary

This page describes the reporting view of investments only. Investment setup, provider configuration, price retrieval, and core lifecycle behavior belong in the existing investment documentation.

## Confidence Level

High

## Assumptions

- The investment timeline endpoint already provides the necessary start and end dates for rendering the position bars.
- Estimated end value is intended as a review aid rather than a formal performance metric.
