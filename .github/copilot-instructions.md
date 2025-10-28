# YAFFA - Coding Agent Instructions

## Project Overview

**YAFFA (Yet Another Free Financial Application)** is a self-hosted personal finance web application built with **Laravel 12** (PHP 8.3) and **Vue 3**. It helps users track income/expenses, manage multiple currencies, handle investments, and perform long-term financial planning. The application includes ~7,700 lines of PHP code across Controllers, Models, Services, and more.

**Key Technologies:**
- Backend: PHP 8.3, Laravel 12 Framework
- Frontend: Vue 3, Bootstrap 5, CoreUI, jQuery, DataTables
- Build: Laravel Mix (Webpack), NPM
- Testing: PHPUnit (Unit/Feature tests), Laravel Dusk (Browser tests)
- Database: MySQL 8
- Code Quality: Laravel Pint (PSR-12), PHPStan (Level 5), ESLint

## Repository Structure

```
├── app/                    # Laravel application code
│   ├── Console/           # Artisan commands
│   ├── Http/Controllers/  # Request handlers
│   ├── Models/           # Eloquent models
│   ├── Services/         # Business logic (TransactionService, InvestmentService, etc.)
│   ├── Policies/         # Authorization logic
│   ├── Jobs/             # Queue jobs
│   └── Rules/            # Validation rules
├── resources/
│   ├── js/               # Vue 3 components and JavaScript
│   ├── views/            # Blade templates
│   └── sass/             # SCSS stylesheets
├── tests/
│   ├── Unit/             # Unit tests
│   ├── Feature/          # Feature tests
│   └── Browser/          # Laravel Dusk browser tests
├── config/               # Laravel configuration files
├── database/
│   ├── migrations/       # 43 database migration files
│   ├── factories/        # Model factories for testing
│   └── seeders/          # Database seeders (including demo.sql)
├── routes/
│   ├── web.php           # Web routes
│   ├── api.php           # API routes
│   └── breadcrumbs.php   # Breadcrumb definitions
├── docker/               # Docker deployment files
├── public/               # Public assets (CSS, JS - generated)
└── storage/              # Application storage (logs, cache)
```

**Important Configuration Files:**
- `composer.json` - PHP dependencies and scripts
- `package.json` - NPM dependencies and build scripts
- `phpunit.xml` - PHPUnit test configuration
- `phpstan.neon` - Static analysis configuration (Level 5)
- `pint.json` - Laravel Pint (PHP CS Fixer) rules (PSR-12 based)
- `.eslintrc.js` - ESLint configuration for Vue 3
- `webpack.mix.js` - Laravel Mix build configuration
- `.env.example` - Environment variable template
- `docker-compose.yml` - Local development with Laravel Sail

## Build & Development Commands

### Initial Setup

**CRITICAL: Composer install may encounter GitHub API rate limiting issues.** If you see "Could not authenticate against github.com" errors, this is due to GitHub API rate limits when downloading packages. This is a known environment limitation and may require:
- Waiting for rate limits to reset
- Using `--prefer-source` flag (slower but may bypass some issues)
- Accepting that some packages may need manual intervention

```bash
# 1. Install PHP dependencies (may take 5-10 minutes, can timeout at 300s due to GitHub rate limits)
composer install --prefer-dist --no-interaction

# 2. Install Node dependencies (~43 seconds)
npm ci

# 3. Setup environment file
cp .env.example .env
php artisan key:generate

# 4. Run database migrations (requires MySQL)
php artisan migrate
```

### Building Assets

**ALWAYS run asset builds before testing UI changes.**

```bash
# Development build (fast, for development)
npm run dev                    # ~30-40 seconds

# Production build (optimized, minified - use for deployment)
npm run production             # ~32 seconds
```

**Build Output:** Generated files go to `public/js/` and `public/css/` - these are Git-tracked.

### Linting

**Run linters before committing code to catch style and quality issues.**

```bash
# PHP linting (PSR-12 code style)
./vendor/bin/pint              # Auto-fixes style issues

# PHP static analysis (PHPStan Level 5)
./vendor/bin/phpstan analyse   # Finds type errors and bugs

# JavaScript/Vue linting
npx eslint resources/js --ext .js,.vue
```

**Note:** Pint excludes `vendor/`, `public/`, `storage/`, `bootstrap/` directories. PHPStan analyzes `app/` directory only.

### Testing

**IMPORTANT:** The automated test suite requires a MySQL database. Tests are configured in `phpunit.xml` and use environment variables from `.env.ci` or `.env.dusk.ci`.

```bash
# Run PHPUnit tests (Unit + Feature tests, NO Dusk browser tests)
vendor/bin/phpunit --testsuite AllNonDusk    # Uses testing database

# Run Dusk browser tests (requires Chrome/Selenium)
php artisan dusk:chrome-driver --detect      # First-time setup
php artisan dusk                             # Run browser tests
```

**Test Configuration:**
- PHPUnit uses a separate `testing` database (see `phpunit.xml`)
- Dusk tests use `.env.dusk.ci` for configuration
- Database credentials: user=`default`, password=`password`, database=`testing`

### Running the Application

```bash
# Start development server
php artisan serve                           # Runs on http://localhost:8000

# Or use Docker Compose (Laravel Sail)
./vendor/bin/sail up                        # Full stack with MySQL, Mailhog, Selenium

# Run development with live reload (uses concurrently)
composer dev                                # Starts: server, queue, logs, vite
```

## GitHub CI/CD Workflows

The repository has CI/CD configured in `.github/workflows/`:

### 1. Automated Tests (`automated-tests.yml`)
**Triggers:** Push to any branch EXCEPT `main` and `other/sandbox`, only when code files change (`.php`, `.js`, `.vue`, `.scss`, `.json`, `.lock`, etc.)

**Jobs:**
- **phpunit**: Runs PHPUnit tests against MySQL 8
  - PHP 8.3 with extensions: mbstring, dom, fileinfo, mysql, xdebug
  - Uses `.env.ci` for configuration
  - Runs migrations before tests
  - Uploads coverage to Codacy
  
- **dusk-php**: Runs Laravel Dusk browser tests
  - Sets up Chrome Driver
  - Builds frontend assets with `npm run dev`
  - Runs Laravel server in background
  - Uploads screenshots/logs on failure

**Critical Steps for Reproducing CI Locally:**
```bash
# For PHPUnit tests (mimicking CI)
ln -f -s .env.ci .env
php artisan config:clear
php artisan migrate -v
vendor/bin/phpunit --testsuite AllNonDusk

# For Dusk tests (mimicking CI)
cp .env.dusk.ci .env
php artisan key:generate
npm run dev                           # Build assets first!
php artisan dusk:chrome-driver --detect
./vendor/laravel/dusk/bin/chromedriver-linux --port=9515 &
php artisan serve --no-reload &
php artisan dusk
```

### 2. Docker Build (`docker-build.yml`)
**Triggers:** Push to tags matching `v*.*.*` or manual workflow dispatch

Builds multi-platform Docker images (linux/amd64, linux/arm64) and pushes to Docker Hub.

## Common Pitfalls & Workarounds

### Composer Install Issues
**Problem:** GitHub API rate limiting causes "Could not authenticate" errors during `composer install`.
**Workaround:** This is an environment limitation. The install may partially complete. If vendor directory exists with most packages, you may be able to proceed with testing. For full install, wait for rate limits to reset or use a GitHub token.

### Asset Build Timing
**Problem:** Forgetting to build assets before testing UI changes.
**Solution:** ALWAYS run `npm run dev` or `npm run production` after making JavaScript/Vue/SCSS changes.

### Database Issues
**Problem:** Tests fail with database connection errors.
**Solution:** Ensure MySQL is running and credentials match those in `phpunit.xml` (user=`default`, password=`password`, database=`testing`).

### Environment File Confusion
**Problem:** Multiple `.env` files exist (`.env.example`, `.env.ci`, `.env.dusk.ci`).
**Solution:** 
- Use `.env.example` as base for local development
- CI uses `.env.ci` for PHPUnit tests
- CI uses `.env.dusk.ci` for Dusk browser tests

### Artisan Command Failures
**Problem:** Running `php artisan` commands fails with vendor/autoload.php not found.
**Solution:** Always run `composer install` first to generate the autoloader.

## Code Style & Standards

### PHP (Laravel)
- **Style:** PSR-12 (enforced by Laravel Pint in `pint.json`)
- **Static Analysis:** PHPStan Level 5
- **Key Rules:** Strict comparison (`===`), arrow functions preferred, logical operators instead of symbolic, no Yoda conditions
- **Namespaces:** PSR-4 autoloading, `App\` namespace for application code

### JavaScript/Vue
- **Style:** ESLint with Vue 3 recommended rules + Prettier
- **Indentation:** 2 spaces
- **Framework:** Vue 3 Options API (see webpack config)

### Testing
- **Unit Tests:** `tests/Unit/` - 68 test files total
- **Feature Tests:** `tests/Feature/` - Test HTTP endpoints and integrations
- **Browser Tests:** `tests/Browser/` - Laravel Dusk for E2E testing
- **Factories:** Use model factories in `database/factories/` for test data

## Important Notes

1. **Trust These Instructions:** Only perform additional searches if information here is incomplete or incorrect. This document is designed to minimize exploration time.

2. **Build Before Test:** Always build assets (`npm run dev`) before running Dusk tests or testing UI changes.

3. **Database Required:** Most tests require MySQL. Use Docker Compose or local MySQL instance.

4. **Timeout Considerations:** 
   - Composer install: May take 5-10 minutes, can timeout at 300s
   - NPM install: ~43 seconds
   - NPM production build: ~32 seconds
   - PHPUnit tests: Variable, depends on test count

5. **Known TODOs in Codebase:** The codebase contains TODO comments (found in `resources/js/` files) indicating future improvements. These are informational and don't block development.

6. **Migration Path:** If upgrading from YAFFA 1.x to 2.x, see `UPGRADE.md` for breaking changes related to Laravel 10→12 migration and environment variable renames.

7. **Docker Deployment:** Production Docker image is built from `docker/Dockerfile` with Caddy web server. Local development uses Laravel Sail with `docker-compose.yml`.

8. **Queue System:** Application uses Laravel queues for background jobs. Start queue worker with `php artisan queue:work` or use `composer dev` for development.

## Validation Checklist

Before submitting changes:
- [ ] Run `./vendor/bin/pint` to fix PHP code style
- [ ] Run `./vendor/bin/phpstan analyse` to catch type errors
- [ ] Run `npx eslint resources/js --ext .js,.vue` for frontend linting
- [ ] Build assets: `npm run production` or `npm run dev`
- [ ] Run PHPUnit tests: `vendor/bin/phpunit --testsuite AllNonDusk`
- [ ] If UI changes, consider running Dusk tests: `php artisan dusk`
- [ ] Check `.gitignore` - don't commit `vendor/`, `node_modules/`, `.env`, or `storage/` (except structure)
- [ ] Verify build artifacts in `public/js/` and `public/css/` are updated and committed
