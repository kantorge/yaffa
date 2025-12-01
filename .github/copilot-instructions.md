# YAFFA (Yet Another Free Financial Application) - AI Coding Instructions

YAFFA is a Laravel 10 personal finance application focused on long-term financial planning with multi-currency support, investment tracking, and automated transaction processing. Self-hosted, PHP 8.3+, user-scoped multi-tenancy.

## Core Architecture

### Transaction System (Central Domain Model)
- **Transaction** is the heart of the system with polymorphic `config` relationship:
  - `TransactionDetailStandard`: deposits, withdrawals, transfers (between accounts/payees)
  - `TransactionDetailInvestment`: buy, sell, dividend, interest transactions
- Use `isStandard()` and `isInvestment()` methods to determine transaction type
- **TransactionItem** handles line items (categories, amounts, accounts, tags) - each transaction can split across multiple categories
- **TransactionSchedule** manages recurring transactions using **Recurr** library (simshaun/recurr):
  - Stores RRULE-like patterns (frequency, interval, count, end_date)
  - `next_date` field tracks next occurrence
  - `automatic_recording` flag enables job-based creation
  - Use `skipNextInstance()` to advance schedule after manual entry
- **TransactionService**: `enterScheduleInstance()` clones transaction using **Cloneable** trait for scheduled entry

### Financial Entity Hierarchy
- **AccountEntity** (polymorphic base) provides unified interface for:
  - `Account` (cash/bank accounts with opening balance, currency)
  - `Investment` (stocks/funds/bonds with symbol, auto_update flag)
- Both can serve as payees (spending targets) or transaction participants
- **AccountGroup** and **InvestmentGroup** organize entities hierarchically
- All entities are user-scoped via `user_id` foreign key

### Multi-Currency Architecture
- **CurrencyTrait** (`app/Http/Traits/CurrencyTrait.php`) provides:
  - `getBaseCurrency()`: cached per-user base currency lookup
  - `allCurrencyRatesByMonth()`: monthly averaged rates, cached 24h
  - `convertCurrency()`: conversion between currencies with automatic rate lookup
- **CurrencyRate** stores daily rates, auto-updated via Frankfurter API (free, no key)
- Each Account has a `currency_id`; Investments inherit currency from their account
- TransactionService determines transaction currency from account context

### Data Processing & Automation
- **ReceivedMail** + **ReceivedMailService**: OpenAI-powered receipt parsing from emails
  - Gmail OAuth2 support (config in `config/yaffa.php`)
  - Processes via `ProcessIncomingEmailByAi` job
- **InvestmentPriceScraper**: RoachPHP spider with CSS selector extraction
  - Falls back to AlphaVantage API (requires `ALPHA_VANTAGE_KEY`)
  - **WisealphaInvestmentPriceScraper**: extracts bond prices from JavaScript data
- **InvestmentTransactionUploader**: Bulk import system supporting YAML/CSV/JSON
  - Configurable field mapping per source
  - Duplicate detection using transaction hash
  - See `app/Services/InvestmentTransactionUploader.php`
- **StagingTransactionParser**: Handles staged transaction imports with validation

### Background Jobs & Observers
- **Jobs** (`app/Jobs/`): Queue-based async processing
  - `CalculateAccountMonthlySummary`: Updates account balance aggregates
  - `GetCurrencyRates`, `GetInvestmentPrices`: Daily data refresh
  - `RecordScheduledTransaction`: Auto-enter recurring transactions
- **Observers** (`app/Observers/`): Model event hooks
  - `TransactionObserver`: Updates account summaries on transaction changes
  - `InvestmentPriceObserver`, `CurrencyRateObserver`: Cache invalidation
- Observers registered in `EventServiceProvider`

## Development Workflows

### Build & Asset Management
```bash
npm run dev          # Laravel Mix development build with webpack
npm run production   # Minified production build
npm run watch        # Watch mode for live reloading
```
Stack: Vue 3, CoreUI (Bootstrap 5), amCharts4, DataTables, Select2, jQuery. Mix config in `webpack.mix.js`.

### Testing (PHPUnit 10 + Dusk)
```bash
php artisan test                    # All non-Dusk tests (Unit + Feature)
php artisan test --testsuite=Unit   # Unit tests only (Services, Models)
php artisan test --testsuite=Feature # HTTP integration tests
php artisan dusk                    # Browser tests (separate command, separate suite)
```
- Test suites defined in `phpunit.xml`: Unit, Feature, AllNonDusk
- Test environment uses in-memory cache/session (`array` driver), `testing` database
- Dusk tests in `tests/Browser/` require Chrome/ChromeDriver

### Key Artisan Commands
```bash
php artisan yaffa:update-currency-rates    # Fetch daily rates from Frankfurter
php artisan yaffa:update-investment-prices # Scrape/API investment prices
php artisan yaffa:process-received-mails   # Parse receipts via OpenAI
php artisan yaffa:fix-stuck-calculations   # Clear stalled account summary batches
php artisan queue:work                     # Process background jobs
```
Custom commands in `app/Console/Commands/`. Queue required for observers' batch jobs.

### Deployment
Uses **Deployer** (`deploy.php`) with Laravel recipe:
```bash
dep yaffa  # Deploys to host defined by SSH_HOST, SSH_USER, DEPLOY_PATH env vars
```
- Targets `main` branch, keeps 5 releases
- Restarts queue workers after deploy (`artisan:queue:restart`)
- Private key at `~/.ssh/private_key`

## Coding Patterns

### Model Conventions
- **Cloneable trait** (bkwld/cloner): Use for duplicating transactions with relationships
  - Set `$cloneable_relations` array to clone related models
  - Example: `Transaction::duplicate()` clones config + transactionItems
- **CurrencyTrait**: Mix into models needing currency conversion utilities
- All models define explicit `$fillable`, `$casts`, `$appends`, `$hidden` arrays
- Polymorphic relationships use `*_type` + `*_id` columns:
  - `Transaction->config`: TransactionDetailStandard|TransactionDetailInvestment
  - `AccountEntity->config`: Account|Investment (labeled 'accountable' in DB)

### Service Layer Pattern
- Services in `app/Services/` handle complex business logic, keep controllers thin
- Key services:
  - **TransactionService**: Transaction lifecycle (scheduling, currency resolution, cash flow calculation)
  - **AccountGroupService**, **InvestmentGroupService**: Entity hierarchy management
  - **ReceivedMailService**: AI-powered receipt parsing orchestration
  - **TransactionUploadService**, **InvestmentTransactionUploader**: Bulk imports
- Controllers in `app/Http/Controllers/` focus on HTTP concerns (validation, responses)

### External Integrations
- **Frankfurter API**: Currency rates (free, public, no registration) - `kantorge/laravel-currency-exchange-rates` package
- **Alpha Vantage**: Investment prices (free tier, requires `ALPHA_VANTAGE_KEY` in env)
- **OpenAI**: Receipt parsing (optional, requires key + `openai-php/laravel` package)
- **reCAPTCHA**: Form protection (biscolab/laravel-recaptcha, requires site/secret keys)
- **Gmail API**: OAuth2 email fetching (optional, config in `config/yaffa.php`)

### Configuration & Environment
- `config/yaffa.php`: App-specific settings
  - Reads version from `composer.json` dynamically
  - Sandbox mode, user limits, email verification toggles
  - Gmail OAuth2 credentials + whitelist
- All financial entities user-scoped via `user_id` - multi-tenant architecture
- Key env vars: `ALPHA_VANTAGE_KEY`, `OPENAI_API_KEY`, `RECAPTCHA_SITE_KEY`, `GMAIL_CLIENT_ID`

### Route Organization
- `routes/web.php`: Main application routes (resourceful + custom)
- `routes/breadcrumbs.php`: Diglactic breadcrumbs navigation definitions
- `routes/channels.php`: Broadcasting authorization (not actively used)
- Routes use standard Laravel resource patterns with custom actions:
  - `GET /transactions/create/{type}`: Type-specific transaction forms
  - `PATCH /transactions/{transaction}/skip`: Skip schedule instance
  - `POST /transactions/create-from-draft`: Convert draft to real transaction

## File Structure
- `app/Components/`: Laravel view components (Blade components for UI widgets)
- `app/Observers/`: Eloquent model event handlers (cache invalidation, cascade updates)
- `app/Jobs/`: Queue-based background jobs (calculations, API calls, scheduled tasks)
- `app/Spiders/`: RoachPHP web scraping spiders for price data
- `app/Services/`: Business logic services (separated from controllers)
- `app/Http/Traits/`: Reusable model traits (CurrencyTrait for currency operations)

## Testing Strategy
- **Feature tests** (`tests/Feature/`): HTTP workflows, controller integration, auth flows
- **Unit tests** (`tests/Unit/`): Business logic in Services, model methods, isolated components
- **Dusk tests** (`tests/Browser/`): Complex UI interactions, JavaScript-dependent features
- Run Dusk separately (`php artisan dusk`) - requires Chrome/ChromeDriver setup
- Test database (`DB_DATABASE=testing`) uses separate connection with seeded data
- Use factories (database/factories/) for test data generation