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
# This is used only for the `main` branch as part of releasing new versions.
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

**Triggers:** Push to any branch EXCEPT `main`, only when code files change (`.php`, `.js`, `.vue`, `.scss`, `.json`, `.lock`, etc.)

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
**Solution:** ALWAYS run `npm run dev` after making JavaScript/Vue/SCSS changes.

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

- **Unit Tests:** `tests/Unit/` - ~70 test files total
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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.6
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/telescope (TELESCOPE) - v5
- tightenco/ziggy (ZIGGY) - v2
- larastan/larastan (LARASTAN) - v3
- laravel/dusk (DUSK) - v8
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- vue (VUE) - v3
- eslint (ESLINT) - v9

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit <name>` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>
