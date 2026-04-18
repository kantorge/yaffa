# Currency

## Feature Name

Currency Management

## Feature Summary

A Currency in YAFFA is the unit in which money is denominated for a given user. It lets the user define which monetary systems they work with, designate one base currency as the application's main reference point, and optionally enable automatic exchange-rate refreshes for non-base currencies.

This concept matters beyond simple labeling: the base currency anchors onboarding, reporting, account valuation, and cross-currency interpretation throughout the application. Exchange-rate history is closely related, but it is a separate concept: Currency defines the monetary identity and configuration, while Currency Rate records the time-based conversion values between currencies.

---

## Target User

- **Primary:**
  Any YAFFA user who needs to record finances in at least one monetary unit. Currency setup is part of the earliest application setup path, alongside account groups and accounts, because the rest of the financial model depends on it.

- **Secondary:**
  Users who actively manage finances across multiple currencies, such as maintaining foreign-currency accounts, reviewing balances in a unified base currency, or relying on automatic exchange-rate refreshes.

---

## User Problem

- Without a defined currency, financial records have no denomination, so balances, transactions, and reports cannot be interpreted reliably.
- Users who track money across multiple currencies need a consistent reference point for comparing values over time.
- The system must distinguish between the currency a value is natively stored in and the currency used for summary, reporting, and onboarding logic.
- Users should not need to manually re-enter the same currency setup rules in each account or report.

---

## User Value / Benefit

### Functional Benefits

- Establishes the monetary unit required before meaningful account and transaction tracking can happen.
- Gives the user a single base currency that other features can use for reporting, summaries, and conversions.
- Allows multiple currencies per user while preserving tenant isolation and per-user uniqueness.
- Supports optional automatic refresh of related exchange-rate data for non-base currencies.
- Shows current exchange context directly in the currency list through latest known rates relative to the base currency.

### Conceptual Benefits

- **Currency gives numeric values meaning.** An amount without currency is ambiguous; an amount tied to a currency becomes a usable financial fact.
- **The base currency becomes the user's financial frame of reference.** It is the default language in which the application explains total value, reporting, and onboarding readiness.
- **Multi-currency tracking stays coherent.** YAFFA can preserve native-currency records while still helping the user reason about their finances through a single reference currency.

---

## Technical Description

Each Currency is a user-owned record with a name, ISO code, an optional `base` flag, and an `auto_update` flag. The controller exposes standard create, update, list, and delete flows, plus a dedicated action to promote a currency to the user's base currency.

The application enforces one base currency per user at the data level and also treats the first created currency as the initial base currency. When the base currency changes, YAFFA clears cached currency data and dispatches background work to refresh exchange rates for the user's non-base currencies that are marked for automatic updates.

The currency index page is not just a static settings list: it also surfaces the latest known conversion relationship between each non-base currency and the current base currency. That display depends on the separate Currency Rate concept, but the Currency feature owns the user-facing configuration and base-currency workflow.

---

## Inputs

### User-provided

- **Name**: required; unique per user
- **ISO code**: required 3-character code; normalized to uppercase; unique per user
- **Automatic update**: boolean flag controlling whether related exchange rates should be refreshed automatically

### System-provided

- **User ownership**: the authenticated user becomes the owner of each created currency
- **Initial base assignment**: the first currency created for a user is automatically marked as base
- **Default user provisioning**: new-user setup creates a base currency automatically from onboarding context

---

## Outputs

- Created or updated `currencies` record for the authenticated user
- Currency list page populated with base-marker state and latest known rate metadata
- Success or error flash messages for create, update, delete, and base-change actions
- Cache invalidation for per-user currency collections when currencies change
- Cache invalidation for monthly aggregated currency-rate data when the base currency changes
- Background jobs dispatched to refresh rates for eligible non-base currencies after a base-currency change

---

## Domain Concepts Used

- **Currency**: a user-owned monetary unit identified by name and ISO code
- **Base Currency**: the single currency used as the application's main reference point for that user
- **Automatic Update**: a per-currency preference indicating whether related exchange-rate data should be refreshed automatically
- **Currency Rate**: a dated conversion value between two currencies; related, but documented separately from Currency itself
- **Account**: an account is denominated in one currency and depends on currencies being configured first

---

## Core Logic / Rules

- A currency belongs to exactly one user.
- Currency **name** must be unique per user.
- Currency **ISO code** must be unique per user.
- Only one currency can be marked as base for a given user; this is reinforced by a unique database index on `(base, user_id)`.
- The first currency created for a user is automatically set as base, if no other base currency exists for that user.
- Base currency cannot be deleted through the currency UI.
- Setting a new base currency clears the base flag from the user's other currencies inside a database transaction.
- Promoting a currency to base succeeds only if it is not already the current base currency.
- When the base currency changes, only non-base currencies with `auto_update = true` are queued for rate refresh.
- Currency helpers fall back to the first currency by ID if no record is explicitly flagged as base; this acts as a resilience mechanism in domain logic.
- Latest displayed rate on the currency list may be absent; the UI explicitly shows "Not available" in that case.

---

## User Flow

### Creating a Currency

1. User opens the currency form.
2. User enters name, ISO code, and optional automatic-update preference.
3. On submit, the currency is created under the authenticated user.
4. If it is the user's first currency and no other base currency exists for that user, YAFFA automatically treats it as base.
5. User is redirected back to the currencies list with a success message.

### Reviewing Currencies

1. User opens the currencies list.
2. YAFFA loads all currencies owned by the user.
3. For each currency, YAFFA attaches the latest known rate relative to the current base currency.
4. The UI shows name, ISO code, automatic-update status, latest rate from base, latest rate to base, and available actions.
5. The base currency is visually highlighted and cannot be deleted or re-promoted.

### Changing the Base Currency

1. User clicks "Set as default" on a non-base currency.
2. The UI asks for confirmation.
3. YAFFA updates base flags transactionally so that only the selected currency remains base.
4. YAFFA clears currency-related caches.
5. YAFFA queues automatic rate refreshes for the user's eligible non-base currencies.
6. User returns to the previous page with a success or failure message.

### Deleting a Currency

1. User triggers delete from the currencies list.
2. If the currency is base, deletion is blocked immediately.
3. If the currency is referenced elsewhere, database-level integrity may block deletion.
4. YAFFA returns a specific "in use" error for foreign-key failures.

---

## Edge Cases / Constraints

- A user can have currencies without rate data; the currency remains valid, but conversions are not available yet.
- A currency marked as base has no "latest rate" relative to itself, so those list cells are intentionally empty.
- The user-facing base-currency lookup is slightly stricter in some places than the shared trait fallback: `User::baseCurrency()` returns only explicitly flagged base currencies, while `CurrencyTrait::getBaseCurrency()` falls back to the first currency if none is flagged.
- Automatic rate updates do not apply to the base currency itself.
- Deletion can fail even for non-base currencies when related records still depend on them.
- Authorization is ownership-scoped through policies and middleware; users can only manage their own currencies.

---

## Dependencies

### Models

- `Currency`
- `CurrencyRate` for latest-rate lookups and base-related conversions
- `User`

### Controllers / Requests / Traits

- `CurrencyController`
- `CurrencyRequest`
- `CurrencyTrait`

### Jobs

- `GetCurrencyRates` job, dispatched after base-currency changes for eligible currencies

### Related Features

- Account management depends on currencies because every account is denominated in one currency
- Reporting and transaction APIs rely on the base currency and cached rate maps for cross-currency summaries
- Onboarding checks whether the user has at least one currency and a base currency configured
- Currency Rate management is a separate but tightly coupled feature covering rate history retrieval, storage, editing, and API access

### External Systems

- Currency exchange-rate provider via `kantorge/currency-exchange-rates` facade, used indirectly through Currency methods and jobs

---

## Frontend Interaction

### Currency List

- Rendered as a DataTable from server-provided currency data
- Base currency is shown with a star icon and bold text
- Automatic-update state is rendered as a boolean icon
- Two rate columns display the latest known conversion from and to the base currency
- Row actions include edit, delete, rate history access, and "Set as default" for non-base currencies

### Currency Form

- Standard server-rendered HTML form for create and edit
- Fields exposed to the user: name, ISO code, automatic update
- No explicit "base" toggle is shown in the form; base assignment is implicit on first create and explicit through the dedicated "Set as default" action

### Confirmation Behavior

- Changing the base currency uses a browser confirmation dialog before navigation proceeds
- Deletion uses the shared delete-confirmation pattern used elsewhere in the application

---

## Domain Concepts

- **Currency:** the monetary identity attached to accounts, transactions, and value interpretation for a single user.
- **Base Currency:** the user's primary reference currency for system-wide comparison and reporting.
- **Foreign Currency:** any user currency that is not the current base currency.
- **Automatic Update:** an opt-in behavior for non-base currencies indicating that rate data should be refreshed automatically when relevant workflows run.
- **Currency Rate:** a historical conversion record between two currencies on a specific date. This is a separate concept that supports Currency but is not the same thing.

---

## Confidence Level

**High**

---

## Assumptions

- This document intentionally covers the **Currency** concept and configuration workflow, not the full **Currency Rate** feature; rate CRUD, charting, and historical editing should be documented separately.
- The business meaning of base currency is inferred not only from currency files but also from onboarding, account, reporting, and transaction-related usages.
- Automatic rate refresh appears to be event-driven around base-currency changes and background jobs; any scheduled or cron-based refresh workflows, if they exist elsewhere, are outside the scope of this document.
