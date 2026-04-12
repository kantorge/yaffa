# Account

## Feature Name

Account Management

## Feature Summary

An **Account** is a named financial container that represents a real-world holding — a wallet, a bank account, savings account, credit card, or brokerage account. Each account tracks an opening balance, is denominated in a specific currency, belongs to a user-defined group, and accumulates transactions over time. Together, accounts form the structural backbone of YAFFA: every transaction must flow from or into one, and the system derives the user's financial position from the aggregate of all account balances.

---

## Target User

- **Primary:**
  Any YAFFA user at any experience level. Accounts are required before any transaction can be recorded — they are part of the minimum setup (alongside currencies and account groups). A new user setting up YAFFA will encounter account creation as one of the first mandatory steps. Even casual users who simply record daily spending must have at least one account.

- **Secondary:**
  An intermediate to advanced user who actively manages account structure — organizing accounts into groups, enabling or disabling accounts over time, reviewing balances across currencies, and using account-level analytics (history view, cashflow reports, investment tracking).

---

## User Problem

- Without accounts, there is no anchor for financial tracking — the user cannot say "where is my money?" or "what can I spend from my savings?"
- Most people hold money in several different places (wallet, bank, savings, brokerage, credit card) that need to be tracked separately with different currencies and different balance histories
- Importing transactions from banks depends on stable account identification; without a consistent, aliased account name, imported data is ambiguous
- Understanding whether an account balance includes only liquid cash or also illiquid investments requires explicit separation into two metrics
- Reviewing years of transactions every time a user opens an account page can be slow; users need control over the default date window

---

## User Value / Benefit

### Functional Benefits

- Centralized view of all accounts in one list: currency, opening balance, account group, transaction count, and import alias — all scannable at a glance
- Active/inactive toggle directly in the index table (without leaving the page), enabling users to hide closed accounts without deleting their history
- Account detail page shows a live running balance: opening balance, current cash value, and current balance including investments, calculated in both native and base currency when applicable
- Date range filter on the account detail page is configurable per account (independent of the global setting), reducing page load time for accounts with long histories
- Inline quick-create for new transactions and investment transactions directly from the account page, without navigating away
- Account history page supports an optional forecast overlay (scheduled transactions rendered as future entries with a running total projection)
- Reconciliation flag is togglable per transaction directly from the account view, providing a lightweight bank-reconciliation workflow

### Conceptual Benefits

- **Accounts make wealth visible.** An account is the answer to "where is my money right now?" — moving from an abstract sum to a named, real-world container the user recognises.
- **The account is the unit of financial trust.** By separating accounts, the user can verify one account against a bank statement without touching others, building confidence in the accuracy of their records.
- **Accounts bridge cash and investment value.** The distinction between "current cash value" and "current balance with investments" gives users an honest, non-misleading view of their total position — liquid money is shown separately from market-valued holdings.
- **Account groups enable mental categorisation.** Grouping accounts (e.g., "Daily banking", "Savings", "Investments") mirrors how users already think about their money, making the list navigable and the hierarchy meaningful.

---

## Technical Description

An Account is modelled as two linked records:

1. **`account_entities`** — the shared identity layer. Stores the account's name, active flag, import alias, user ownership (`user_id`), and a polymorphic reference to the type-specific config (`config_type = 'account'`, `config_id`). This table is shared with Payees.

2. **`accounts`** — the account-specific configuration layer. Stores the opening balance, the assigned account group, the assigned currency, and the optional default date range preset.

This two-table structure allows shared query and permission logic to operate over both accounts and payees (e.g., name uniqueness scoped to `config_type + user_id`, a single policy class, a single controller).

The balance visible in the account detail view is not calculated live from transactions — it is derived from **`account_monthly_summaries`**, a cached summary table updated by a background job (`CalculateAccountMonthlySummariesJob`). The balance API returns a "busy" state if the recalculation job is still running, and the UI polls automatically until data is available.

The `account.history` route (`MainController::account_details`) loads all transactions for an account in a single server-side pass and sends pre-processed data to the frontend. This contrasts with the `account-entity.show` route, which loads the account detail shell and fetches transaction data dynamically via the API (`/api/v1/transactions`) with date range parameters.

---

## Inputs

### User-provided when creating or editing an account:

- **Name** — required, unique per user + type, 3–255 characters
- **Active** — boolean toggle; defaults to true
- **Opening balance** — numeric, fits in signed `DECIMAL(30,10)`; represents the account value at the start of tracking
- **Account group** — must be one of the user's own account groups (pre-condition: at least one group must exist)
- **Currency** — must be one of the user's own currencies (pre-condition: at least one currency must exist)
- **Import alias** — optional free-text field; newline-separated values matched against AI document processing account strings
- **Default date range** — optional preset controlling how far back the account detail view loads by default; overrides the global user setting if set; can also be set to "Don't load data by default"

### System inputs:

- Transaction records flowing through `TransactionDetailStandard` (as `account_from_id` or `account_to_id`) and `TransactionDetailInvestment` (as `account_id`)
- Monthly summary cache data in `account_monthly_summaries`

---

## Outputs

- Created or updated `account_entities` + `accounts` rows
- Account list view (index) populated via server-rendered DataTable with client-side JavaScript data
- Account detail page (show) with live balance fetch, filterable history, and scheduled transaction panel
- Account history page (history) with full pre-loaded transaction list, running total, and optional forecast overlay
- Inline active-state toggle reflected immediately in the index DataTable without a full page reload
- Balance API response (`/api/v1/accounts/{accountEntity}/balance`) with: opening balance, current cash value, current balance with investments — in base currency and, if applicable, also in native foreign currency

---

## Domain Concepts Used

- **AccountEntity** — the shared identity record; distinguishes accounts from payees by `config_type`
- **Account** — the type-specific config record; holds financial properties of the account
- **AccountGroup** — a user-defined label that organises accounts into logical buckets (e.g., "Daily", "Savings"). Must exist before an account can be created.
- **Currency** — defines the denomination of the account and controls exchange rate conversion for balance reporting. Must exist before an account can be created.
- **Opening balance** — the account's starting value at the time the user began tracking it in YAFFA; serves as the base for running total calculations
- **Transaction** — a financial event linked to an account; may be a standard (withdrawal/deposit/transfer) or investment transaction
- **AccountMonthlySummary** — a cached aggregation of account balance data, split into `account_balance` (cash) and `investment_value` (market-valued investments)
- **Reconciled** — a per-transaction flag indicating whether the transaction has been verified against an external source (e.g., bank statement)

---

## Core Logic / Rules

- **Name uniqueness** is enforced per user + `config_type` (i.e., an account and a payee can share the same name, but two accounts cannot)
- **Account group and currency are mandatory** for accounts; their absence blocks account creation with redirects to the relevant setup screens
- **Account deletion is not exposed** through the web UI — the controller explicitly excludes the `destroy` action; it is handled only via the API (presumably to enforce safety checks on accounts with transaction history)
- **Active flag** is soft-togglable directly in the index table without a form submit; persisted via a `PATCH` API call; no transactions are affected
- **Balance is calculated from the cached summary**, not from raw transactions. If a recalculation job is running (detectable via the `job_batches` table), the endpoint returns a `busy` result and the UI retries after a 5-second delay
- **Running total on the history page** is computed server-side by sorting transactions chronologically and cumulatively summing the opening balance + all cash flows; forecast items (from scheduled transactions) are appended at the end when the forecast mode is enabled
- **Cash flow direction** for transfers is determined by comparing the transaction's `account_from_id` with the current account's ID: if the account is the source, the flow is negative; if it is the destination, the flow is positive
- **Foreign currency accounts** have their balances reported in both native currency and the user's base currency (using the stored exchange rate)

---

## User Flow

### Creating an Account

1. User navigates to the Accounts index (`/account-entity?type=account`)
2. User clicks "New account"
3. If no account groups exist, user is redirected to create one first (with a flash message)
4. If no currencies exist, user is redirected to create one first (with a flash message)
5. User fills in: name, active flag, opening balance, account group, currency, import alias (optional), default date range (optional)
6. User submits; account is created and the user is returned to the accounts list

### Viewing Account Details (Dynamic View)

1. User clicks an account name in the index list
2. Account detail page loads (`account-entity.show`); balance panel shows spinners
3. Balance is fetched asynchronously via `/api/v1/accounts/{id}/balance`; if a background job is running, spinners rotate for up to 5 seconds per retry until data arrives
4. User selects a date range using the date range filter card (a Vue component)
5. Transaction history table fetches matching transactions via `/api/v1/transactions`
6. Scheduled transactions are fetched separately from `/api/v1/transactions/scheduled-items`
7. User can create new transactions inline (standard or investment) directly from this page
8. User can reconcile/unreconcile individual transactions by clicking the reconcile icon
9. User can skip, edit, clone, or replace scheduled transactions from the scheduled panel

### Viewing Account History (Legacy Static View)

1. User clicks "Load account transaction history" on the account detail page
2. All transactions (and optionally scheduled items) are loaded server-side at once and rendered to the `history.blade.php` view
3. User can toggle forecast mode via the URL parameter (`withForecast`), adding forward projections from scheduled transactions with a running total
4. User can filter by reconciled status via a toggle button group
5. Each transaction row shows: date, reconciled icon, payee, category, withdrawal, deposit, running total, comment, tags, and action buttons

---

## Edge Cases / Constraints

- An account **cannot be deleted** if it has transactions (enforced via API only, not exposed in the web UI)
- If the monthly summary recalculation job is running, balance data is unavailable; the UI degrades gracefully to a warning icon and auto-retries
- The `default_date_range` field on the account accepts a controlled vocabulary defined in `config/yaffa.php` under `account_date_presets`; it can also be set to `none` (don't load any data) or left null (inherit the user's global setting)
- Accounts belonging to a foreign currency always show balance in **both** the native currency and the user's base currency in the detail view; the conversion uses the exchange rate stored in the `currencies` table
- Opening balance is stored with 10 decimal places (`DECIMAL(30,10)`), but display precision is governed by locale formatting
- The account group and currency are protected by `ON DELETE RESTRICT` foreign keys — they cannot be deleted while accounts reference them

---

## Dependencies

### Models:

- `Account` — type-specific configuration
- `AccountEntity` — shared identity wrapper
- `AccountGroup` — required grouping entity
- `Currency` — required denomination entity
- `Transaction` — financial events flowing through the account
- `TransactionDetailStandard` — standard transaction configuration (references account as `account_from_id` or `account_to_id`)
- `TransactionDetailInvestment` — investment transaction configuration (references account as `account_id`)

### Controllers:

- `AccountEntityController` — handles web CRUD (index, create, store, edit, update, show)
- `MainController::account_details` — serves the legacy history view
- `AccountApiController::getAccountBalance` — API endpoint for live balance data
- `AccountApiController::recalculateMonthlySummary` — triggers the background recalculation job

### Services / Jobs:

- `CalculateAccountMonthlySummariesJob` — background job that populates `account_monthly_summaries`

### Policies:

- `AccountEntityPolicy` — ownership-scoped `view`, `create`, `update`, `forceDelete`

### External systems:

- None

---

## Frontend Interaction

### Account Index (`resources/views/accounts/index.blade.php` + `resources/js/account/index.js`)

- DataTable rendered client-side from JSON data passed via `window.accounts`
- Columns: Name (linked), Active icon (togglable), Currency, Opening balance, Transaction count (linked to reports), Account group, Import alias
- Active toggle fires a `PATCH` API call directly from the table cell, with icon replaced by a spinner during request
- Context menu (right-click or hover icon): Show details, Edit, Show transactions, Delete
- Sidebar filters: active toggle, text search

### Account Form (`resources/views/accounts/form.blade.php`)

- Standard HTML form for both create and edit
- Shared view: detects presence of `$account` to switch between create (`POST`) and update (`PATCH`) mode
- Fields: name, active checkbox, opening balance (text input), account group (select), currency (select), import alias (textarea), default date range (grouped select from config)

### Account Detail — Dynamic View (`resources/views/accounts/show.blade.php` + `resources/js/account/show.js`)

- Overview card: active status, currency, opening balance, current cash value, current balance with investments (all loaded async)
- Date range filter: a Vue 3 component (`DateRangeFilterCard`) that emits events driving the history table AJAX reload
- Transaction history table: AJAX-loaded, filtered by date range and account; columns include date, reconciled, payee, category, amount, comment, tags, actions
- Scheduled transactions table: AJAX-loaded; overdue items highlighted red, today's items highlighted yellow
- Inline transaction creation: standard and investment modals launched from the page header buttons or from the scheduled draft "Adjust and enter instance" button
- Transaction delete propagates a balance refresh (with a 15-second delay to allow the background summary recalculation to complete)

### Account History — Legacy Static View (`resources/views/accounts/history.blade.php` + `resources/js/account/history.js`)

- All transactions loaded server-side at page load; no AJAX
- Withdrawal and Deposit are shown in separate columns with color coding
- Running total column tracks cumulative balance from the opening balance onward
- Scheduled / forecast items are muted (greyed out) and do not show a running total contribution
- Reconcile toggle fires a `PATCH` API call from the status icon
- Forecast toggle via URL (`withForecast` parameter): adds projected instances of scheduled transactions with a forward-looking running total

---

## Confidence Level

**High** — all core behaviors are directly visible in the code. The balance calculation pipeline (monthly summaries + background jobs) is confirmed by API controller code and the `account_monthly_summaries` schema.

---

## Assumptions

- The `cash` value in the balance response represents the sum of all standard transactions plus the opening balance (inferred from `account_balance` summary data type and the `openingBalance()` method on the `Account` model). This is not explicitly commented in the balance API — the interpretation is inferred from the combination of data types, the formula, and the UI labels.
- The `investment_value` summary represents the current market value of investments held in the account (most recent monthly entry), not a historical sum. This is inferred from the `MAX(date)` logic in the balance query.
- Account deletion safety (blocking accounts with transactions) is implemented in the API controller, not the web controller. The web `destroy` route is explicitly excluded. The exact enforcement logic was not read in full — this is an informed inference.
- The two account detail views (`show` and `history`) appear to coexist intentionally: the `history` route is linked from the `show` page as a separate "Load account transaction history" action. The `show` view is the primary interactive interface; `history` is a legacy or alternative full-load view.
