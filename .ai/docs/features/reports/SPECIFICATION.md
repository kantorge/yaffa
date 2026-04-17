# Reports

## Feature Name

Reports and Financial Analysis

## Feature Summary

YAFFA Reports is the analytical layer of the application. It turns transaction, account, schedule, budget, and investment data into reviewable views that help users understand what already happened, what is planned, and where their finances appear to be heading.

This is where the effort of recording financial details starts to create visible value. Daily entries, categorization, recurring planning, and investment tracking become meaningful here because the user can finally interpret the past, question the present, and gain insights to prepare for the future.

This is best treated as one umbrella feature with multiple report lenses rather than one isolated screen. The underlying domain concepts such as transactions, accounts, payees, investments, and categories are documented elsewhere; this documentation focuses on how those building blocks are combined for review, interpretation, and planning.

## Target User

- Primary:
  Intermediate to advanced personal finance users performing regular weekly, monthly, or quarterly reviews and wanting more than a raw transaction list.

- Secondary:
  Detail-oriented self-hosting users who inspect schedules, validate imported or manually entered data, and monitor long-term portfolio development.

## User Problem

- Users need to understand their financial history without manually inspecting every transaction.
- Users need one place to compare current results with plans such as budgets and recurring schedules.
- Users need a practical way to review cash movement and investment progress over time.
- Users need report views that support reflection and decision-making, not just storage of records.
- Users need their past recording effort and future planning effort to translate into actionable insight.

## User Value / Benefit

### Functional Benefits

- Reduces manual effort when searching for relevant transactions or patterns.
- Makes monthly cash movement and cumulative account trends visible at a glance.
- Shows actual versus budgeted outcomes over time for selected categories.
- Consolidates recurring schedules and budgeted entries into a reviewable maintenance view.
- Gives a visual timeline of investment positions for periodic portfolio review.

### Conceptual Benefits

- Reinforces YAFFA's philosophy of conscious financial tracking rather than invisible automation.
- Helps users connect daily entries with longer-term habits and planning outcomes.
- Improves financial clarity by turning isolated records into understandable trends.
- Gives meaning and payoff to the user's effort of recording both history and future intentions.

## When to Use Reports

Use the Reports area when the question is no longer What did I enter, but What does it mean?

This area is especially useful when the user wants to:

- review a month or quarter after regular bookkeeping,
- check whether recurring plans and budgets still fit reality,
- see whether forecasted obligations might outgrow available funds,
- understand whether free cash is accumulating fast enough to save or invest,
- inspect the trend behind a feeling such as I spend too much or I should be saving more.

## Business Questions This Area Answers

- What actually happened in my finances over the selected period?
- Which categories, payees, or accounts are driving the result?
- Are my plans and recurring commitments still realistic?
- Will my upcoming financial pattern fit my available funds?
- Am I building enough monthly surplus to save or invest confidently?
- Which investment positions are still open and how is my portfolio evolving over time?

## Report Map

The current report area is composed of five closely related subfeatures:

- Find Transactions: interactive investigation workspace for transaction history
- Cash Flow: monthly trend view for balances, running total, and optional forecast-aware data
- Category-Based Budget History and Forward Review: analytical plan-versus-reality view for selected categories and account scope
- Schedules and Budgets: simple table-driven maintenance view for recurring and planned items
- Investment Timeline: time-based view of investment positions and estimated end value

These are best documented separately while remaining part of one Reports concept.

## Technical Description

- The feature is exposed through a dedicated Reports section in the main navigation.
- Each subfeature has its own page and often its own API endpoint or data source.
- Backend controllers aggregate data into chart-friendly or table-friendly structures.
- Frontend views use filters, tabs, trees, and charts rather than edit forms.
- Multi-currency values are normalized against the user's base currency where comparison across assets is needed.
- Localization is applied to chart labels, dates, and currency rendering.
- Some report data is intentionally pre-aggregated or cached by month because recalculating every chart directly from all transactions and all schedule instances on every request would not scale well.

## Inputs

- Existing standard and investment transactions
- Categories and their parent-child hierarchy
- Account and account entity selection
- Date ranges or year-month periods
- Scheduled and budgeted transactions
- Precomputed monthly summaries
- Investment positions and investment groups
- User settings such as locale, end date, and base currency

## Outputs

- Filtered transaction lists and summaries
- Monthly cashflow chart series
- Actual versus budget comparison data by period
- Category-level waterfall summaries
- Review tables for scheduled and budgeted transactions
- Investment timeline visualizations with quantity and estimated value context

## Domain Concepts Used

- Transaction: the basic financial record being searched, grouped, or summarized
- Category: the classification used for spending and income analysis
- Schedule: a recurring definition that can generate expected future instances
- Budget: a planned amount represented through scheduled transaction data
- Account entity: the financial context used to scope summaries and filters
- Investment group: a grouping used to organize portfolio timeline views
- Base currency: the common comparison currency for cross-currency reporting

## Core Logic / Rules

- Reports are primarily read-focused analytical views; they summarize existing data rather than serving as the main place to create new records.
- Access is limited to authenticated and verified users.
- Currency conversion is applied when different currencies need to be compared in the same chart.
- Cashflow can optionally include forecast-aware summary values if they are available.
- Cashflow may temporarily return a busy state while monthly summary jobs are still running.
- Budget comparison expands recurring budget definitions into monthly instances up to the user's configured planning horizon.
- Forecast-oriented behavior in YAFFA is tightly connected to schedules and budgets, so the quality of forward-looking views depends on those planned items being maintained accurately.
- Some report pages include actions that link back to transaction editing, cloning, replacing, or skipping schedule instances.
- Some reporting behavior is still evolving; for example, one waterfall endpoint includes a planned data type parameter whose behavior appears only partially differentiated today.

## User Flow

1. User opens the Reports navigation group.
2. User chooses the relevant analysis view based on the question they want answered.
3. User narrows the dataset using filters such as dates, categories, accounts, payees, tags, or investment groups.
4. YAFFA fetches and aggregates the relevant records.
5. The user reviews charts or tables and may drill down into more detailed views.
6. If needed, the user follows linked actions back to underlying transactions or schedules.

## Frontend Interaction

- Reports favor filters, charts, and tabbed canvases over full-page forms.
- Some views remember state through URL parameters, which supports sharing context or returning to a prior view.
- Some interactions use lightweight session-based caching to avoid repeated loading for the same filter set.
- Data-heavy views provide loading states, placeholders, and focused tooltips to keep the review process understandable.

## Cross-Feature Patterns

- Monthly aggregation is a recurring pattern across cashflow, budget, and some category summaries.
- Current-month highlighting is used in chart-based time views to anchor the user in the present.
- Account scope and category selection are important for narrowing broad financial histories.
- Existing domain actions are reused rather than duplicated inside report-specific code.
- Forecast-oriented reporting is grounded in recurring schedules and budget definitions rather than in opaque automation.

## Relationship to Dashboard

The dashboard should usually be documented separately.

The dashboard is an at-a-glance operational overview with lightweight report-like widgets, while the Reports area is the dedicated place for deeper analysis. Dashboard widgets such as balances, waterfalls, or upcoming schedules should be described as summary entry points that connect to the richer reporting workflows documented here.

## Edge Cases / Constraints

- Reporting quality depends on the quality and completeness of underlying transaction data.
- Budget analysis is strongest when categories and recurring plans are consistently maintained.
- Cashflow forecasting depends on summary data that may still be computing in the background.
- Reports provide practical planning insight, but they are not strict accounting or tax-reporting outputs.
- Some views are highly interactive and may be less useful when data volume is very small or filters are too broad.

## Dependencies

- Models:
  Transaction, TransactionItem, TransactionDetailStandard, account and investment related models, and precomputed summary tables

- Services:
  CategoryService, currency helpers, schedule expansion helpers, shared data-table and transaction transformation helpers

- External systems:
  Client-side charting libraries, select widgets, tree selectors, and table components

## Documentation Scope Guidance

This reports folder should focus on analytical usage and user value. It should not duplicate detailed model-level documentation for transactions, accounts, payees, or investments. Those existing documents should remain the source of truth for the underlying domain objects.

## Confidence Level

High

## Assumptions

- Existing documentation for transactions, investments, accounts, and payees already covers those entities in depth.
- This specification intentionally focuses on reporting behavior and relationships instead of repeating domain-object documentation.
- Product context describes advanced reporting as evolving, which aligns with the current codebase showing both mature and still-growing report behaviors.
