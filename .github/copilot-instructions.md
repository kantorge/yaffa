# YAFFA (Yet Another Free Financial Application) - AI Coding Instructions

YAFFA is a Laravel-based personal finance application focused on long-term financial planning with multi-currency support, investment tracking, and automated transaction processing.

## Core Architecture

### Transaction System
- **Transaction** is the central entity with polymorphic `config` relationship to either `TransactionDetailStandard` or `TransactionDetailInvestment`
- Use `isStandard()` and `isInvestment()` methods to determine transaction type
- **TransactionItem** handles individual line items within transactions (categories, amounts, accounts)
- **TransactionSchedule** manages recurring transactions with automatic scheduling using Recurr library
- **TransactionService** provides business logic for schedule processing and currency handling

### Financial Entity Hierarchy
- **AccountEntity** (polymorphic base) → **Account** (cash/bank accounts) or **Investment** (stocks/funds)
- **AccountGroup** and **InvestmentGroup** organize entities
- Multi-currency support with automatic rate updates via Frankfurter API (no API key needed)
- **CurrencyTrait** provides conversion utilities across models

### Data Processing Features
- **ReceivedMail** + **ReceivedMailService**: OpenAI-powered receipt parsing from emails
- **InvestmentPriceScraper**: RoachPHP spider for price data collection with AlphaVantage fallback
- **WisealphaInvestmentPriceScraper**: Specialized spider for WiseAlpha bond prices, extracts buyPrice from JavaScript
- **InvestmentTransactionUploader**: YAML/CSV-based bulk import system with flexible field mapping and duplicate detection
- **StagingTransactionParser**: Handles bulk transaction imports

## Development Workflows

### Build & Asset Management
```bash
npm run dev          # Development build with webpack
npm run production   # Production build
npm run watch        # Watch mode for development
```
Assets use Laravel Mix with Vue 3, CoreUI framework, and amCharts for financial visualizations.

### Testing
```bash
php artisan test                    # Run all tests
php artisan test --testsuite=Unit   # Unit tests only
php artisan dusk                    # Browser tests
```
Separate PHPUnit suites: Unit, Feature, AllNonDusk. Dusk tests require separate command.

### Key Artisan Commands
```bash
php artisan yaffa:update-currency-rates    # Daily currency updates
php artisan yaffa:update-investment-prices # Investment price updates
php artisan yaffa:process-received-mails   # Parse receipt emails
php artisan yaffa:fix-stuck-calculations   # Fix stuck account summary job batches
php artisan queue:work                     # Process background jobs
```

### Deployment
Uses Deployer with recipe/laravel.php. Deploy with environment variables:
```bash
dep yaffa  # Deploys to host defined by SSH_HOST, SSH_USER, DEPLOY_PATH
```

## Coding Patterns

### Model Conventions
- Use **Cloneable** trait (bkwld/cloner) for duplicating transactions with relationships
- **CurrencyTrait** provides `convertCurrency()` and related currency utilities
- Models use explicit `$fillable`, `$casts`, and `$appends` definitions
- Polymorphic relationships: Transaction→config, AccountEntity→accountable

### Service Layer
- Services in `app/Services/` handle complex business logic
- **TransactionService**: `enterScheduleInstance()` for schedule processing
- **AccountGroupService**, **InvestmentService**: Entity management
- Controllers focus on HTTP concerns, delegate business logic to services

### External Integrations
- **Alpha Vantage**: Investment prices (requires API key in `ALPHA_VANTAGE_KEY`)
- **OpenAI**: Receipt parsing (requires key, optional feature)
- **reCAPTCHA**: Form protection via biscolab/laravel-recaptcha
- **Frankfurter**: Currency rates (free, no registration)

### Configuration
- `config/yaffa.php` contains app-specific settings including version extraction from composer.json
- Environment variables for API keys and optional features (sandbox mode, user limits)
- Multi-tenancy support via user scoping on all financial entities

## File Structure Notes
- **Components/**: Laravel components for UI elements
- **Observers/**: Model event handlers
- **Jobs/**: Queue-based background processing
- **Spiders/**: Web scraping classes using RoachPHP
- Routes organized by function: breadcrumbs.php for navigation, channels.php for broadcasting

## Testing Strategy
- Feature tests for HTTP workflows
- Unit tests for business logic in Services
- Dusk tests for complex UI interactions (separate test suite)
- Test database uses 'testing' connection with in-memory arrays for caching/sessions