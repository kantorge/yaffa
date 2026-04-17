# Currency Rate

## Feature Name

Currency Rate Management

## Feature Summary

A Currency Rate in YAFFA is a dated conversion value from one user-owned currency to another, typically from a foreign currency to that user's base currency. It gives the application the historical conversion context needed to compare balances, transactions, and reports across multiple currencies without rewriting the original native-currency records.

This concept is separate from Currency itself. Currency defines what monetary units a user works with; Currency Rate defines how those units related to each other on specific dates, and provides both manual and automated workflows for maintaining that history.

It's very important to note, that Currency Rate primarily represents the above mentioned historical conversion values, but it can be different from the conversion rate applicable to a specific transfer transaction on the same date.

---

## Target User

- **Primary:**
  Intermediate user tracking finances in multiple currencies who needs reliable conversion history for review, reporting, and account interpretation. This is often a periodic-review user rather than a first-day setup user.

- **Secondary:**
  Technical or detail-oriented self-hosting user who wants to inspect, correct, or backfill exchange-rate history manually when imported or scheduled data is missing.

---

## User Problem

- Multi-currency finance tracking is hard to interpret unless amounts can be translated into a common reference currency.
- The user needs historical conversion values, not just a single latest rate, because reports and transactions span time.
- Automatically fetched rate data may be incomplete or unavailable for some dates, so users need a way to review and correct gaps.
- Reporting and account views need a stable conversion layer that can be reused efficiently instead of recalculating from scratch each time.

---

## User Value / Benefit

### Functional Benefits

- Preserves dated exchange-rate history for specific currency pairs instead of relying only on a current snapshot.
- Lets users manually add, edit, and delete rates for a currency pair when corrections are needed.
- Supports on-demand backfill of missing rates directly from the rate-management screen.
- Supports daily automated rate retrieval for eligible currencies, reducing manual maintenance.
- Feeds cached monthly average rates into reports and transaction APIs so cross-currency views remain performant.

### Conceptual Benefits

- **Currency rates let YAFFA compare unlike amounts without erasing their original meaning.** A transaction can stay in its native currency while still contributing to a base-currency summary.
- **Historical rates make time-based review honest.** The user is not just seeing today's exchange context projected backward over old transactions.
- **Rate history turns multi-currency data into one coherent financial picture.** It bridges the gap between local accuracy and global understanding.

---

## Technical Description

Each Currency Rate record stores a `from` currency, a `to` currency, a date, and a positive numeric rate. The web feature centers on a currency-pair page that loads a Vue-based manager with an overview card, actions panel, date filtering, editable table, and line chart.

YAFFA exposes API endpoints for listing, creating, updating, and deleting rates for a pair, plus a dedicated endpoint to retrieve missing rates for a given source currency relative to its base currency. Separately, a scheduled console command queues background jobs that retrieve missing rates daily for all non-base currencies with automatic updates enabled.

The feature is tightly integrated with reporting and transaction views through a cached monthly-average rate map. Rate changes invalidate that cache automatically so later base-currency summaries use fresh conversion data.

---

## Inputs

### User-provided

- **From currency**: selected implicitly by the page context or supplied to the API
- **To currency**: selected implicitly by the page context or supplied to the API
- **Date**: required date for the rate record
- **Rate**: required positive numeric conversion value
- **Load missing rates action**: explicit user request to backfill missing data for the selected source currency

### System-provided

- **Authenticated user ownership context**: both currencies must belong to the same user
- **Scheduled retrieval**: daily command selects eligible currencies automatically
- **External exchange-rate provider data**: used when fetching missing historical rates
- **Base-currency relationship**: automatic retrieval is oriented around rates from a currency to that user's base currency

---

## Outputs

- Created, updated, or deleted `currency_rates` records
- Currency-pair management page with overview metrics, table rows, and charted history
- JSON API responses containing rate collections or updated rate payloads
- Validation errors for duplicate dates, invalid currencies, invalid values, or missing fields
- Background jobs queued for missing-rate retrieval
- Invalidated monthly currency-rate cache for affected users
- Derived monthly average rate map used by reports and transaction conversion logic

---

## Domain Concepts Used

- **Currency Rate**: a dated conversion value between two user-owned currencies
- **From Currency**: the currency being converted from
- **To Currency**: the currency being converted to, usually the user's base currency in automated workflows
- **Base Currency**: the reference currency used for most summaries and conversions across YAFFA
- **Missing Rates**: historical dates for which no stored conversion record exists yet
- **Monthly Average Rate Map**: a cached aggregation used by reporting and transaction features for efficient date-based conversion

---

## Core Logic / Rules

- A rate belongs to a specific currency pair and date.
- The combination of `date + from_id + to_id` must be unique.
- Both `from_id` and `to_id` must reference currencies owned by the authenticated user.
- The rate value must be numeric and greater than zero.
- The validated lower bound is `0.0000000001`; the validated upper bound is `9999999999.9999999999`.
- Automatic retrieval does not run for base currencies.
- Automatic retrieval does not run for currencies where `auto_update` is disabled.
- Manual pair management is broader than automatic retrieval: the UI and CRUD API can work with any authorized pair, while the retrieval endpoint and background jobs are specifically tied to a currency's base-currency relationship.
- Missing-rate retrieval fails when the source currency is the same as the base currency, when the base currency cannot be resolved, when the external provider does not support one of the currencies, when no data is returned, or when returned values are outside the accepted range.
- Rate records are stored without timestamps; the important dimension is historical date, not record modification time.
- Creating, updating, or deleting any rate invalidates the affected user's cached monthly conversion map.

---

## User Flow

### Reviewing a Currency Pair

1. User opens the currency-rate page for a selected pair.
2. YAFFA authorizes access to both currencies.
3. The page loads overview data, the rate table, and a chart of the pair's history.
4. User can inspect first available date, last available date, last known rate, and total number of records.

### Adding a Rate Manually

1. User clicks "Add new rate".
2. A modal opens for the current currency pair.
3. User enters date and rate value.
4. YAFFA validates the payload and stores the new record through the API.
5. The table and chart update in place, and the user sees a success toast.

### Editing a Rate

1. User clicks the edit action on an existing rate row.
2. The modal opens prefilled with the rate's current date and value.
3. User updates the value or date.
4. YAFFA validates uniqueness and persists the change.
5. The updated rate replaces the old one in the in-page table and chart.

### Deleting a Rate

1. User clicks the delete action on an existing row.
2. YAFFA asks for confirmation.
3. On confirmation, the API deletes the record.
4. The row disappears from the table and the chart refreshes.

### Loading Missing Rates On Demand

1. User clicks "Load missing rates" for the selected source currency.
2. YAFFA calls the retrieve-missing API endpoint for that source currency.
3. The application fetches missing historical values against the source currency's base currency.
4. Newly retrieved records are stored, the page reloads data, and the user sees a success or error toast.

### Automatic Daily Retrieval

1. The scheduler runs the currency-rate retrieval command daily.
2. YAFFA selects all non-base currencies with automatic updates enabled.
3. A queue job is dispatched per eligible currency.
4. Each job retrieves missing rates against that currency's base currency.

---

## Edge Cases / Constraints

- A user can open a currency-pair page that currently has no stored data; the overview and chart degrade to "No data" states.
- The manual CRUD API allows authorized pairs in general, but the missing-rate retrieval endpoint only takes a single currency and assumes the target is that currency's base currency.
- Duplicate rate entries for the same date and pair are blocked at validation and database uniqueness levels.
- If the external provider returns unsupported or invalid data, YAFFA surfaces a conversion error instead of silently storing bad values.
- Cached reporting conversions are based on monthly averages, so downstream reports intentionally consume aggregated rate history rather than individual daily records directly.
- The chart and filtered table are client-side views over the loaded dataset; changing date range narrows what the user sees without altering stored data.

---

## Dependencies

### Models

- `CurrencyRate`
- `Currency`
- `User`

### Controllers / Requests / Services

- `CurrencyRateController`
- `API\CurrencyRateApiController`
- `CurrencyRateRequest`
- `CurrencyRateService`
- `CurrencyTrait` for monthly aggregation and date-based lookup behavior used by other features

### Jobs / Commands / Scheduling

- `GetCurrencyRates` job
- `GetCurrencyRates` console command
- Daily scheduler entry in [routes/console.php](routes/console.php#L24)

### Related Features

- Currency management links into rate management from the currencies list
- Account, transaction, and reporting features depend on rate data for base-currency interpretation
- On-demand missing-rate retrieval complements the currency feature's automatic-update setting

### External Systems

- External exchange-rate provider accessed through `kantorge/currency-exchange-rates` package

---

## Frontend Interaction

### Currency Pair Page

- Server-rendered entry page mounts a Vue application for one specific pair
- Header identifies the pair in `FROM → TO` format
- The page combines overview, actions, date-range selection, editable table, and chart in one workspace

### Overview Panel

- Shows source currency, target currency, number of records, first/last available data, and last known rate
- Displays formatted monetary examples using the current locale and target currency formatting

### Actions Panel

- "Add new rate" opens the create modal
- "Load missing rates" triggers API-based backfill and disables the button while loading

### Rate Table

- Uses DataTables for sortable, scrollable historical entries
- Each row exposes edit and delete actions
- Date-range filtering updates the visible rows client-side

### Rate Modal

- Shared modal for create and edit
- Fields: date and numeric rate
- Validation errors are shown inline in the form

### Rate Chart

- Displays historical values as a line chart
- Shows loading and empty states when appropriate
- Updates after CRUD changes without a full page reload

---

## Domain Concepts

- **Currency Rate:** a historical record expressing how much of the target currency equals one unit of the source currency on a specific date.
- **Conversion History:** the sequence of stored rates for a currency pair over time.
- **Missing Rate Retrieval:** the process of fetching and persisting historical dates that are not yet present in the local database.
- **Monthly Average Rate Map:** a reporting-oriented cache that compresses daily rate history into monthly averages for efficient reuse.
- **Currency Pair:** the ordered relationship between a source currency and a target currency; rates are directional rather than generic.

---

## Confidence Level

**High**

---

## Assumptions

- This document covers the Currency Rate feature and concept, not the broader Currency setup workflow already documented separately.
- The main user-facing management flow is the pair page under `/currencyrates/{from}/{to}`; no broader global rate index was found.
- Automatic retrieval is documented as daily because that is what the scheduler and README currently state; the exact external provider behavior and coverage window depend on the provider implementation rather than this feature alone.
- Reporting consumers of rate data use monthly averages by design; this document does not attempt to document every downstream report that reads those cached values.
