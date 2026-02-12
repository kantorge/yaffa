# PHPUnit Group Filtering Fix

## Issue
When running `php artisan dusk --group=critical`, PHPUnit showed deprecation warnings and ran all tests instead of filtering to only critical tests.

### Error Message
```
WARN  Metadata found in doc-comment for class Tests\Browser\Pages\Accounts\AccountListTest. 
Metadata in doc-comments is deprecated and will no longer be supported in PHPUnit 12. 
Update your test code to use attributes instead.
```

## Root Cause
PHPUnit 10+ deprecated doc-comment metadata (like `@group`) in favor of PHP 8+ attributes. The test files were using the old doc-comment syntax:

```php
/**
 * @group critical
 */
class TransactionFormStandardStandaloneTest extends DuskTestCase
```

This caused PHPUnit to:
1. Show deprecation warnings
2. Not properly recognize the group metadata, resulting in all tests running

## Solution
Converted all `@group` doc-comment annotations to PHP 8+ attributes across all 25 Dusk test files.

### Before
```php
<?php

namespace Tests\Browser\Pages\Transactions;

use Tests\DuskTestCase;

/**
 * @group critical
 */
class TransactionFormStandardStandaloneTest extends DuskTestCase
{
    // Test methods...
}
```

### After
```php
<?php

namespace Tests\Browser\Pages\Transactions;

use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

#[Group('critical')]
class TransactionFormStandardStandaloneTest extends DuskTestCase
{
    // Test methods...
}
```

## Changes Made

### Test Files Updated (25 files)
**Critical Group (11 files):**
- All 7 transaction test files (forms and views, modal and standalone)
- `AccountShowTest.php`
- `FindTransactionsFilterBehaviorTest.php`
- `InvestmentPriceTest.php`
- `ScheduledInvestmentTransactionsInDataTablesTest.php`

**Extended Group (14 files):**
- Asset management: `AccountListTest`, `CategoryListTest`, `TagListTest`, `PayeeMergeTest`, `CurrencyRateManagementTest`, `InvestmentGroupListTest`, `AiDocumentsIndexTest`
- Authentication: `LoginTest`, `ProfileTest`, `GoogleDriveSettingsTest`, `AiProviderSettingsTest`
- Partials: `DataLayerTest`, `QuickActionBarTest`, `SandboxTest`

### Documentation Updated
- `TESTING.md` - Updated examples to show attribute syntax
- `OPTIMIZATION_SUMMARY.md` - Updated to reflect attribute usage

## Verification

### Expected Behavior After Fix
```bash
# Run only critical tests (11 test classes)
php artisan dusk --group=critical

# Run only extended tests (14 test classes)
php artisan dusk --group=extended

# Run all Dusk tests (25 test classes)
php artisan dusk
```

### No More Warnings
The deprecation warnings about metadata in doc-comments are eliminated.

### Proper Filtering
PHPUnit now correctly recognizes the group metadata and filters tests accordingly.

## Technical Details

### PHP Attribute Syntax
- Introduced in PHP 8.0
- Uses `#[AttributeName]` syntax before class/method/property declarations
- Recommended by PHPUnit 10+ for all metadata

### Import Required
All test files now include:
```php
use PHPUnit\Framework\Attributes\Group;
```

### Attribute Usage
```php
#[Group('critical')]  // For critical tests
#[Group('extended')]  // For extended tests
```

## Benefits
1. **No Deprecation Warnings**: Code is future-proof for PHPUnit 12
2. **Proper Test Filtering**: Groups work correctly with `--group` flag
3. **Modern PHP**: Uses PHP 8+ features as recommended by PHPUnit
4. **Better Performance**: Attributes are parsed more efficiently than doc-comments

## References
- [PHPUnit 10 Documentation - Test Groups](https://docs.phpunit.de/en/10.5/organizing-tests.html#test-groups)
- [PHP Attributes RFC](https://wiki.php.net/rfc/attributes_v2)
- [PHPUnit Attributes Guide](https://docs.phpunit.de/en/10.5/attributes.html)
