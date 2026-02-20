# Upgrade guide for major versions

This document describes the breaking changes of major versions, and the steps that need to be performed to get you migrated.

Table of contents:

- [Upgrade from YAFFA 2.x to 3.x](#upgrade-from-yaffa-2x-to-3x)
- [Upgrade from YAFFA 1.x to 2.x](#upgrade-from-yaffa-1x-to-2x)

## Upgrade from YAFFA 2.x to 3.x

The main reason for increasing the version is the refactoring of transaction types from a database table to PHP enums. This change improves type safety and performance but requires database migration.

### Breaking Changes

- **Transaction Types Refactored**: The `transaction_types` database table has been removed and replaced with a PHP enum (`App\Enums\TransactionType`).
  - The `transactions` table now uses an `transaction_type` ENUM column instead of a foreign key to `transaction_types`.
  - The `TransactionTypeServiceProvider` has been removed as transaction types are no longer cached in config.
  - Transaction types are now passed to JavaScript via `JavaScriptConfigVariablesComposer` instead of an API endpoint.

- **Data Migration**: All existing transactions will be automatically migrated from `transaction_type_id` to the new `transaction_type` enum column.
  - IDs 1-8 and 11 map to the active transaction types.
  - IDs 9-10 (previously unused) drop support
  - **WARNING**: If you have transactions with IDs 9 or 10, the migration will fail. You must either delete these transactions or reassign them to a valid type before running the migration.

- **API Changes**: The `/api/transaction-types` endpoint has been removed. Transaction types are now available via JavaScript config variables.

### Step-by-step Guide

#### 1. Backup your database

Before running any migrations, create a complete backup of your database.

```bash
# Example for MySQL/MariaDB
# On Linux/macOS:
mysqldump -u username -p database_name > yaffa_backup_$(date +%Y%m%d).sql

# On Windows (PowerShell):
# mysqldump -u username -p database_name > "yaffa_backup_$(Get-Date -Format 'yyyyMMdd').sql"
```

#### 2. Run the migrations

```bash
php artisan migrate
```

This will:

- Add a new `transaction_type` ENUM column to the `transactions` table
- Migrate all data from `transaction_type_id` to `transaction_type`
- Remove the `transaction_type_id` column
- Drop the `transaction_types` table

**Note**: This migration is irreversible after the `transaction_types` table is dropped. Ensure you have a backup before proceeding.

#### 3. Clear caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

#### 4. Rebuild frontend assets (if running from source)

```bash
npm run build
```

### Note for Docker users

You'll need to adjust your `docker-compose.yml` to reflect the changes in the app infrastructure.

- Decide if you want to use Tesseract OCR as a local service or not.

````bash

## Upgrade from YAFFA 1.x to 2.x

The main reason for increasing the version is the migration of the framework from Laravel 10 to Laravel 12.

### Breaking Changes

- Some of the environment variable names used by YAFFA were changed, and you need to update them as part of your migration.

### Step-by-step Guide

#### 1. Update your `.env` file with the following changes

- The broadcast driver key has been renamed. As YAFFA is not using this Laravel feature, the actual impact is minimal.

```diff
- BROADCAST_DRIVER=#your_value#
+ BROADCAST_CONNECTION=#your_value#
````

- The key for the cache driver has been renamed. Some YAFFA features rely on caching so you need to make this change.

```diff
- CACHE_DRIVER=#your_value#
+ CACHE_STORE=#your_value#
```

- The mail encryption environment variable has beeen renamed to be more generic, as not all mail schemes are encryption.
- Make sure to double-check the list of accepted values, and update if necessary. E.g. earlier `ssl` value should be changed to `smtp` or `smtps`, based on your server configuration.

```diff
- MAIL_ENCRYPTION=#your_value#
+ MAIL_SCHEME=#your_value#
```

- Add the following keys and default values that were introduced by Laravel 11. Customize them, if needed.

```diff
-
+ LOG_STACK=single
+ SESSION_ENCRYPT=false
+ SESSION_PATH=/
+ APP_MAINTENANCE_DRIVER=file
+ APP_MAINTENANCE_STORE=database
+ BCRYPT_ROUNDS=12
```

- Even though the language of the UI is controlled by user preferences, Laravel 11 introduced some environment variables related to locale, which should be added as default values for the application

```diff
-
+ APP_LOCALE=en
+ APP_FALLBACK_LOCALE=en
+ APP_FAKER_LOCALE=en_US
```

- Laravel Telescope is now installed in production, but disabled by default. Verify that the `TELESCOPE_ENABLED` flag is in a state as you need it.

#### 2. Run the actual update steps per your hosting option

- Update the code base from Packagist or GitHub. Make sure to install updated dependencies, run migrations, and clear cached assets.
- Pull the latest YAFFA image from Docker Hub, and restart your container. The entrypoint will take care of running the migrations and clearing various caches.
