# Testing Strategy

This document describes the testing strategy for YAFFA and how to run tests efficiently.

## Test Structure

The test suite is organized into three layers:

### 1. Unit Tests (`tests/Unit/`)
- **Purpose**: Test individual classes and methods in isolation
- **Focus**: Services, Models, Helpers, Jobs
- **Execution Time**: Fast (~1-2 minutes)
- **Coverage**: Business logic, calculations, data transformations

### 2. Feature Tests (`tests/Feature/`)
- **Purpose**: Test HTTP endpoints, API routes, and controller behavior
- **Focus**: Request/response cycles, validation, authorization, database interactions
- **Execution Time**: Medium (~2-4 minutes)
- **Coverage**: Controller methods, API endpoints, form submissions

### 3. Dusk Tests (`tests/Browser/`)
- **Purpose**: Test end-to-end user flows and UI interactions
- **Focus**: Critical user journeys and UI behavior
- **Execution Time**: Slow (~10-15 minutes for full suite)
- **Coverage**: Browser-based interactions, JavaScript behavior, complete user workflows

## Test Groups

Dusk tests are categorized into two groups:

### Critical Tests (`@group critical`)
Tests covering the core user journey:
- Transaction creation (standard & investment, modal & standalone)
- Transaction viewing
- Account history with currency conversion
- Investment price management
- Report generation (Find Transactions)
- Scheduled investment transactions

**Run on**: Every PR and push to non-main branches

### Extended Tests (`@group extended`)
Tests covering important but less frequently used features:
- Asset management (accounts, categories, tags, payees, currency rates, investment groups)
- Authentication and settings (login, profile, Google Drive, AI provider)
- UI components (data layer, quick action bar, sandbox mode)
- AI document processing

**Run on**: Only when merging to `develop` branch or manually triggered

## Running Tests

### Locally

#### All Unit Tests
```bash
vendor/bin/phpunit --testsuite Unit
# or with Sail
./vendor/bin/sail artisan test --testsuite=Unit
```

#### All Feature Tests
```bash
vendor/bin/phpunit --testsuite Feature
# or with Sail
./vendor/bin/sail artisan test --testsuite=Feature
```

#### All Unit + Feature Tests
```bash
vendor/bin/phpunit --testsuite AllNonDusk
# or with Sail
./vendor/bin/sail artisan test --testsuite=AllNonDusk
```

#### Critical Dusk Tests Only
```bash
php artisan dusk --group=critical
# or with Sail
./vendor/bin/sail dusk --group=critical
```

#### Extended Dusk Tests Only
```bash
php artisan dusk --group=extended
# or with Sail
./vendor/bin/sail dusk --group=extended
```

#### All Dusk Tests
```bash
php artisan dusk
# or with Sail
./vendor/bin/sail dusk
```

#### All Tests (Unit + Feature + Dusk)
```bash
vendor/bin/phpunit --testsuite AllNonDusk && php artisan dusk
# or with Sail
./vendor/bin/sail test --testsuite=AllNonDusk && ./vendor/bin/sail dusk
```

### CI/CD Workflows

The project uses multiple GitHub Actions workflows for efficient testing:

#### 1. Unit Tests Workflow (`test-unit.yml`)
- **Triggers**: Push to non-main branches, PRs (when Unit tests or app code changes)
- **Runs**: Unit tests only
- **Duration**: ~2-3 minutes

#### 2. Feature Tests Workflow (`test-feature.yml`)
- **Triggers**: Push to non-main branches, PRs (when Feature tests, routes, or app code changes)
- **Runs**: Feature tests only
- **Duration**: ~3-5 minutes

#### 3. Critical Dusk Tests Workflow (`test-dusk-critical.yml`)
- **Triggers**: Push to non-main branches, PRs (when Browser tests, resources, or app code changes)
- **Runs**: Critical Dusk tests only (`#[Group('critical')]`)
- **Duration**: ~5-8 minutes

#### 4. Extended Dusk Tests Workflow (`test-dusk-extended.yml`)
- **Triggers**: Push to `develop` branch only
- **Runs**: Extended Dusk tests only (`#[Group('extended')]`)
- **Duration**: ~7-10 minutes

#### 5. Legacy Automated Tests Workflow (`automated-tests.yml`)
- **Status**: Deprecated in favor of separate workflows
- **Note**: May be removed in future versions

## Best Practices

1. **Write Unit tests first**: Before creating Feature or Dusk tests, ensure business logic is covered by Unit tests
2. **Prefer lower-level tests**: Feature tests are faster than Dusk tests, Unit tests are faster than Feature tests
3. **Keep Dusk tests focused**: Test UI interactions and user journeys, not backend validation
4. **Use appropriate test groups**: Mark Dusk tests with `#[Group('critical')]` or `#[Group('extended')]` attributes
5. **Run tests incrementally**: Don't run the full suite on every change; run only relevant test groups
6. **Validate before committing**: Run at least Unit and Feature tests locally before pushing

## Adding New Tests

### Adding a Unit Test
1. Create test file in `tests/Unit/` matching the class structure
2. Extend `Tests\TestCase`
3. Use `RefreshDatabase` trait if needed
4. Run: `vendor/bin/phpunit tests/Unit/YourNewTest.php`

### Adding a Feature Test
1. Create test file in `tests/Feature/`
2. Extend `Tests\TestCase`
3. Use `RefreshDatabase` trait
4. Run: `vendor/bin/phpunit tests/Feature/YourNewTest.php`

### Adding a Dusk Test
1. Create test file in `tests/Browser/Pages/`
2. Extend `Tests\DuskTestCase`
3. Add `#[Group('critical')]` or `#[Group('extended')]` attribute to class
4. Import the Group attribute: `use PHPUnit\Framework\Attributes\Group;`
5. Run: `php artisan dusk tests/Browser/Pages/YourNewTest.php`

Example:
```php
<?php

namespace Tests\Browser\Pages\YourFeature;

use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

#[Group('critical')]
class YourFeatureTest extends DuskTestCase
{
    public function test_user_can_do_something(): void
    {
        // Test implementation
    }
}
```

## Test Coverage

Current test coverage:
- **Unit Tests**: ~30 test files, covering Services, Models, Jobs, Helpers
- **Feature Tests**: ~31 test files, covering Controllers, API endpoints, Policies
- **Dusk Tests**: 25 test files (11 critical, 14 extended)

Target coverage:
- Critical business logic: 90%+
- API endpoints: 80%+
- UI flows: 100% of critical journeys

## Troubleshooting

### Dusk Tests Failing Locally
- Ensure Chrome/Chromium is installed
- Run: `php artisan dusk:chrome-driver --detect`
- Check that assets are built: `npm run build`

### Database Issues
- Verify MySQL is running
- Check `.env.ci` or `.env.dusk.ci` credentials
- Ensure database migrations are up to date

### Timeout Issues
- Increase timeout in specific tests if needed
- Consider breaking long tests into smaller ones
- Use `@group extended` for slow, non-critical tests

## Migration Notes

This testing strategy was introduced to:
1. Reduce Dusk test execution time from 10-15 minutes to ~5 minutes for critical tests
2. Enable selective test execution based on code changes
3. Provide comprehensive coverage through Unit and Feature tests
4. Maintain backward compatibility with existing test commands
