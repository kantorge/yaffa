# Investment

## Feature Name

Investment Tracking

## Feature Summary

An Investment in YAFFA is a tracked financial asset such as a stock, fund, bond, ETF, or similar holding that sits alongside the user's cash accounts. It combines identity details, transaction history, optional price updates, and portfolio-style reporting so the user can understand how much they own, what it is worth, and how it supports long-term financial planning.

This concept is intentionally different from an Account: an account holds cash or acts as a container, while an investment represents the asset being bought, sold, accumulated, or valued over time.

---

## Target User

- **Primary:**
  Intermediate to advanced YAFFA users who actively manage savings or brokerage activity and want to include investments in the same personal finance system as their daily cashflow. Their goal is long-term planning, portfolio awareness, and more complete net worth tracking.

- **Secondary:**
  Beginner or occasional investors who may only have a few holdings, but still want buys, sells, and dividends to appear in the same place as the rest of their financial life.

---

## User Problem

- Cash-only tracking does not show the user's full financial position; assets held as shares or units remain invisible without a dedicated concept
- Buy/sell/dividend records are difficult to interpret over time unless they are tied to a named investment with quantity and value history
- Users need a way to organize holdings by group and currency so that portfolio review stays understandable as their setup grows
- Long-term planning requires more than transaction logs — it needs a view of quantity, value, performance, and trends over time

---

## User Value / Benefit

### Functional Benefits

- Tracks the current quantity owned for each investment across historical transactions
- Shows the latest known price and estimated current value in the investment's own currency
- Summarizes buying cost, selling revenue, dividends, taxes, commissions, ROI, and annualized ROI for a selected period
- Supports optional automatic price retrieval through a selected provider, while still allowing the investment to exist without automation
- Gives users dedicated detail pages with transaction history and visual charts for price and quantity evolution
- Feeds investment value into related account summaries so wealth reporting is more complete

### Conceptual Benefits

- **Investments make non-cash wealth visible.** The user can see that part of their financial life is held as assets, not only as cash in bank accounts.
- **Holdings become understandable over time.** A named investment creates a stable reference point for reviewing past buys, sales, dividends, and accumulated units.
- **Manual awareness is preserved.** YAFFA supports automation for price lookup, but the user still decides what to track and records the underlying investment activity intentionally.
- **Long-term planning becomes more realistic.** Investment tracking helps the user understand how portfolio value contributes to future financial goals, not just present-day spending.

---

## Technical Description

An Investment is stored as its own model with user ownership, identity fields, grouping, currency, status flags, and optional provider configuration. It belongs to one user, one investment group, and one currency.

The investment is linked to investment transactions through `TransactionDetailInvestment`, which records share quantity, per-unit price, commission, tax, dividend, and the account involved. The system calculates current quantity from non-scheduled transactions and can enrich the investment with a quantity timeline that also includes scheduled projections.

Investment prices are a closely related concept but are not documented in depth here. In practice, the investment detail views combine stored price history with transaction-derived fallback data to show current assets, chart trends, and performance summaries.

---

## Inputs

### User-provided when creating or editing an investment

- **Name** — required, unique per user
- **Symbol** — required, unique per user
- **ISIN** — optional; if present it must be unique per user and exactly 12 characters
- **Comment** — optional free-text note
- **Active** — whether the investment should remain visible and usable
- **Investment group** — required organizational grouping
- **Currency** — required denomination for prices and valuation
- **Automatic update** — optional flag for automated price retrieval
- **Price provider** — optional by default, but required when automatic update is enabled
- **Provider settings** — optional provider-specific fields when automation is configured

### System inputs

- Investment transactions such as buy, sell, add shares, remove shares, dividend, and interest yield
- Scheduled investment transactions used for future quantity projection
- Investment price records and optional provider fetch results

---

## Outputs

- Created or updated investment record in the database
- Investment list entries showing status, group, symbol, quantity, latest price, and estimated value
- Investment detail page with:
  - overview information
  - current owned quantity
  - latest known price and estimated value
  - date-filtered performance summary
  - transaction history
  - price history chart
  - quantity history chart
- Data used in the investment timeline report and related account valuation calculations

---

## Domain Concepts Used

- **Investment** — a tracked financial asset represented by units or shares
- **Investment Group** — a user-defined organizational bucket for similar holdings
- **Currency** — the denomination in which the investment is priced and valued
- **Investment Transaction** — a buy, sell, dividend, interest, or share-adjustment event linked to the investment
- **Account** — the cash or brokerage container through which the investment transaction is recorded
- **Investment Price** — the known market price on a given date; referenced here but documented separately
- **Scheduled Transaction** — a planned future transaction that can affect projected quantity history

---

## Core Logic / Rules

- **Investment group and currency are prerequisites.** If the user has none configured yet, the create flow redirects them to set those up first.
- **Identity is protected per user.** Name and symbol must be unique for each user; ISIN is optional but also unique when provided.
- **Active and automatic update are separate concerns.** An investment can be active without automatic price retrieval, and automation can be turned on or off independently.
- **Automatic update needs a provider.** If the user enables auto-update, a valid price provider selection becomes mandatory.
- **Current quantity is transaction-driven.** The owned amount is calculated from non-scheduled investment transactions using quantity multipliers:
  - buy / add shares → increase quantity
  - sell / remove shares → decrease quantity
  - dividend / interest yield → affect cash result, not owned quantity
- **Scheduled items are used for projection, not current holdings.** On the detail page, YAFFA distinguishes between actual historical quantity and the extended schedule-based projection.
- **Deletion is blocked once the investment is in use.** If related investment transaction details exist, the investment cannot be deleted.
- **Provider-related settings are handled conservatively.** Existing provider settings are preserved unless the user explicitly replaces them.

---

## User Flow (if applicable)

### Creating and configuring an investment

1. User opens the Investments section
2. If needed, the system first requires at least one investment group and one currency
3. User enters the investment's identity and classification details
4. Optionally, the user enables automatic updates and selects a price provider
5. The investment is saved and appears in the list view

### Recording and reviewing investment activity

1. User adds investment transactions such as buys, sells, dividends, or share adjustments
2. YAFFA aggregates those transactions into a current quantity and historical timeline
3. The user opens the investment detail page to review:
   - current assets
   - transaction history
   - quantity history
   - price history
   - performance summary for a selected date range

### Ongoing maintenance

1. User can toggle the investment's active state from the list view
2. User can edit identity or provider settings later
3. If the investment is no longer relevant and has never been used in transactions, it can be deleted

---

## Edge Cases / Constraints

- An investment without any price history can still exist and be transacted against; some value-focused views may have limited information until a price is known
- ISIN and comment are optional and may be absent in the detail view
- Scheduled transaction projections only use active scheduled items
- The UI hides deletion actions when the investment already has transaction usage
- Price-provider automation is an evolving supporting capability; the investment concept itself remains usable even without it

---

## Dependencies

### Models:

- `Investment`
- `InvestmentGroup`
- `Currency`
- `InvestmentPrice`
- `Transaction`
- `TransactionDetailInvestment`
- `AccountEntity`
- `User`

### Services:

- `InvestmentService`
- `InvestmentProviderSettingsResolver`
- `InvestmentPriceProviderContextResolver`

### Controllers:

- `InvestmentController`
- `App\Http\Controllers\API\InvestmentApiController`
- related price-management and reporting controllers

### External systems (if any):

- Optional external price providers for automated market price retrieval

---

## Frontend Interaction (if applicable)

### Investment list

- Displays investments in a DataTable-style summary view
- Shows name, active state, group, symbol, ISIN, quantity, latest price, and estimated value
- Supports quick actions such as view details, edit, add transaction, view prices, and toggle active state

### Investment form

- Uses a shared create/edit form
- Collects core identity and classification fields
- Adds a Vue-powered provider section that reveals provider-specific configuration only when needed
- Includes a test-fetch action when a price provider is selected

### Investment detail view

- Composes multiple cards into a single investment dashboard
- Separates static identity details from live holding/value information
- Shows transaction history with actions for edit, clone, delete, or scheduled-instance handling
- Provides price and quantity charts for visual review over time
- Lets the user narrow the analysis to a selected date range to inspect investment performance

### Investment timeline report

- Provides a broader cross-investment visualization showing how holdings span over time
- Supports filtering by active state, open status, search text, and investment group

---

## Domain Concepts

- **Investment:** a user-owned financial asset tracked by units or shares, not a cash container
- **Owned quantity:** the number of units currently held based on recorded investment transactions
- **Latest owned value:** the estimated present value of the holding using the most recent known price
- **Investment group:** a user-defined structure for organizing holdings into meaningful buckets such as retirement, brokerage, or speculative assets
- **Automatic update:** optional automation that refreshes market prices from a configured provider
- **Results period:** the user-selected date window used to summarize performance metrics such as ROI and annualized ROI

---

## Confidence Level

**High**

---

## Assumptions (IMPORTANT)

- This specification uses [.ai/docs/product-context.md](.ai/docs/product-context.md) as a framing reference for user value and long-term planning goals. That context aligns with the code reviewed.
- This specification also referred to [.ai/docs/features/investment-price-providers/SPECIFICATION.md](.ai/docs/features/investment-price-providers/SPECIFICATION.md). It is consistent with the current implementation, so it is referenced rather than repeated here.
- Investment prices are intentionally treated as a supporting concept in this document. They are tightly related to investments, but the purpose of this specification is to document the investment asset itself rather than the full pricing subsystem.
- The concept appears **mostly complete but still evolving**: core tracking, reporting, and organization are clearly implemented, while provider-driven pricing automation continues to expand around it.
