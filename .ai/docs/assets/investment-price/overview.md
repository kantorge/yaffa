# Investment Price

## Feature Name

Investment Price History

## Feature Summary

An Investment Price in YAFFA is a dated market value attached to a specific investment. It gives the application the missing valuation context needed to answer not just what the user owns, but what that holding was worth at a given point in time and what it is worth now.

This concept is separate from the Investment itself. The investment identifies the asset and its quantity; the investment price provides the valuation layer that turns those quantities into meaningful current value, historical trend, and performance reporting.

It is also important to distinguish between a stored historical investment price and a transaction-specific price. When entering a buy or sell transaction, the user can set the per-unit transaction price directly as part of that transaction. That value represents the executed trade price for that event and does not necessarily become the investment's general daily price record.

---

## Target User

- **Primary:**
  Intermediate to advanced users who review portfolio performance over time and want their holdings to show realistic value rather than only raw share counts.

- **Secondary:**
  Detail-oriented users who occasionally correct or backfill missing price points manually when automatic retrieval is unavailable, incomplete, or not configured.

---

## User Problem

- Quantity alone does not show whether an investment is performing well or badly; the user also needs the asset's value over time
- Historical buys and sells are harder to interpret without a reliable price reference on specific dates
- Portfolio review becomes misleading if YAFFA only knows the number of units held but not their market worth
- Automatic retrieval may help, but users still need a way to inspect, trust, and correct the stored valuation history themselves

---

## User Value / Benefit

### Functional Benefits

- Stores dated price points for each investment so historical and current value can be calculated consistently
- Powers the latest price, latest owned value, and price-history chart on investment detail screens
- Supports manual add, edit, and delete actions when the user needs to maintain pricing data directly
- Allows missing prices to be loaded for configured investments without forcing the user to enter every data point by hand
- Feeds account and reporting features with a reusable valuation record instead of recalculating everything from transactions alone

### Conceptual Benefits

- **Investment prices turn holdings into understandable wealth.** A quantity of shares becomes a concrete financial value the user can reason about.
- **Historical valuation makes review more honest.** The user sees that portfolio value changes over time, rather than treating all holdings as static.
- **Price history improves confidence in planning.** Long-term review works better when the application can relate ownership to changing market conditions.
- **Manual control remains available.** Even when retrieval is automated, the user still has visibility into the stored history and can correct it when needed.

---

## Technical Description

Each investment price record belongs to a single investment and stores two key pieces of information: a date and a numeric price. Together, these records form the valuation history of the asset.

YAFFA uses this history in several ways: to display price charts, to calculate the latest known value of a holding, and to support account-level investment valuation. When price history is missing, the system can sometimes fall back to transaction price information, but the dedicated price history remains the main source for explicit valuation over time.

The transaction entry flow also allows a buy or sell to carry its own per-unit price. This transaction-level price is part of the transaction record and reflects what the user actually paid or received on that trade. It can differ from the broader daily valuation history, and it may exist even when no separate historical investment price record has been stored for that date.

Automatic retrieval and data providers are supported as helper mechanisms for maintaining this history, but they are secondary to the core concept: the stored investment price is the dated valuation record that the rest of the application can trust and reuse.

---

## Inputs

### User-provided

- **Investment** — the asset the price belongs to
- **Date** — the day this price applies to
- **Price** — the market value per unit/share on that date
- **Transaction price on buy/sell entry** — a per-unit trade price entered as part of an individual investment transaction
- **Load missing prices action** — an optional request to backfill valuation history for an investment with a configured provider

### System-provided

- Existing investment context such as currency and ownership
- Automatically retrieved price data when a provider is configured
- Transaction price data used as a fallback in some reporting and valuation cases

---

## Outputs

- Stored historical price records for an investment
- Updated latest known price used across the investment detail experience
- Price-history chart data for visual review
- Improved current-value and performance calculations for investments and related accounts
- Success, validation, or error feedback when prices are added, updated, deleted, or retrieved

---

## Domain Concepts Used

- **Investment** — the asset being valued
- **Investment Price** — a dated per-unit valuation of that asset
- **Transaction Price** — the per-unit price recorded on a specific buy or sell transaction
- **Currency** — the denomination in which the price is expressed
- **Latest Price** — the newest known price currently available to YAFFA
- **Price History** — the chronological series of stored valuation points for an investment
- **Owned Value** — the estimated value of the currently held quantity using the latest known price

---

## Core Logic / Rules

- **A price is always tied to one investment.** It has no meaning on its own outside the asset it belongs to.
- **Date matters as much as value.** YAFFA treats price history as a timeline, not just a single current snapshot.
- **One stored value represents one day's known valuation.** This keeps the history stable and understandable for charting and reporting.
- **Transaction prices and stored price history are related but not identical.** A buy or sell can have its own execution price recorded during transaction entry, and that value does not have to match or create the standalone daily price history entry.
- **Ownership and authorization apply through the investment.** A user can only manage price data for investments they own.
- **Price history improves valuation, but investments remain usable without it.** The user can still record investment transactions even if price coverage is incomplete.
- **Manual and automatic maintenance can coexist.** Users can enter prices directly, while optional retrieval helps fill gaps.
- **Price changes affect more than the price screen.** When investment prices change, related account summaries may need recalculation because the underlying asset value has changed.

---

## User Flow (if applicable)

### Reviewing price history

1. User opens an investment and inspects the current assets and price history areas
2. If deeper review is needed, the user opens the dedicated prices screen for that investment
3. YAFFA shows the number of records, first and last available dates, latest known price, a table of entries, and a chart of historical movement

### Adding or correcting prices manually

1. User opens the dedicated price-management screen
2. User adds a new dated price or edits an existing one
3. YAFFA validates and stores the change
4. The table, overview, and chart refresh to reflect the updated history

### Entering a trade with its own price

1. User creates or edits an investment transaction such as a buy or sell
2. As part of the transaction entry, the user sets the per-unit trade price
3. YAFFA stores that price on the transaction itself for that trade event
4. This helps preserve the real execution context even if no separate daily price-history record exists for the same date

### Loading missing prices

1. User selects the action to load missing investment prices
2. If the investment has a configured provider, YAFFA attempts to fetch missing history
3. Retrieved values are stored and appear in the same overview, table, and chart workflow as manually entered prices

---

## Edge Cases / Constraints

- A newly created investment may have no price history yet; overview and chart areas degrade to a clear no-data state
- If no price provider is configured, missing prices cannot be loaded automatically from the management screen
- Incomplete price history may still allow partial valuation, especially when recent transaction price data exists
- A transaction's buy/sell price may differ from the investment's stored daily price for that same date, because the transaction reflects the specific deal that was executed
- Stored pricing is historical by date; it should not be treated as a live market feed
- Automatic retrieval can help populate the history, but the availability and completeness of external data may vary

---

## Dependencies

### Models

- `InvestmentPrice`
- `Investment`
- `Currency`
- `User`

### Controllers / Services

- `InvestmentPriceController`
- `API\InvestmentPriceApiController`
- `InvestmentService`

### Related Features

- Investment tracking and investment detail views
- Account monthly summaries that include investment value
- Optional automated price retrieval and provider configuration

### External Systems

- Optional external data providers used to fetch missing price records

---

## Frontend Interaction

### Dedicated investment price screen

- Presents a focused workspace for one investment's valuation history
- Combines overview, actions, date filtering, table management, and chart display in one place

### Overview panel

- Shows the investment name
- Shows the number of stored price records
- Highlights the first available date, last available date, and the last known price

### Actions panel

- Lets the user add a new price manually
- Lets the user request missing price retrieval when the investment is configured for it

### Price table and chart

- Lists historical records for inspection and maintenance
- Supports editing and deletion of stored entries
- Displays the trend visually so the user can understand how valuation changed over time

---

## Domain Concepts

- **Investment Price:** a dated record of what one unit of an investment was worth at a given time
- **Transaction Price:** the price per unit recorded on a specific buy or sell, representing the actual trade context rather than the general historical price series
- **Price History:** the ordered sequence of those dated values for the same investment
- **Latest Known Price:** the newest stored price that YAFFA can use for current valuation
- **Owned Value:** the product of current quantity and latest known price
- **Missing Prices:** gaps in the historical series that can be filled manually or, when available, through automated retrieval

---

## Confidence Level

**High**

---

## Assumptions

- This document focuses on the concept of investment prices as a user-facing valuation record, not on the full provider architecture.
- It was written to complement, not duplicate, the broader investment concept in [.ai/docs/assets/investment/SPECIFICATION.md](.ai/docs/assets/investment/SPECIFICATION.md).
- It also intentionally avoids repeating the low-level design material from [.ai/docs/features/investment-price-providers/SPECIFICATION.md](.ai/docs/features/investment-price-providers/SPECIFICATION.md), using that only as background context where needed.
- The feature appears mature in core user-facing behavior: price records can be viewed, maintained, charted, and used in valuation, while automated retrieval remains a supporting capability around that core.
