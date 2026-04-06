# Upgrade guide for major versions

This document describes the breaking changes of major versions, and the steps that need to be performed to get you migrated.

Table of contents:

- [Upgrade from YAFFA 2.x to 3.x](#upgrade-from-yaffa-2x-to-3x)
- [Upgrade from YAFFA 1.x to 2.x](#upgrade-from-yaffa-1x-to-2x)

## Upgrade from YAFFA 2.x to 3.x

This version introduces several significant changes:

- **Transaction Types Refactored** — The `transaction_types` database table has been replaced with a PHP enum, which requires a database migration.
- **AI Document Processing** — A fully new feature for uploading and AI-processing documents (PDFs, images, emails) into draft transactions. This is optional, but brings new environment variables and new database tables.
- **Email Processing Migrated** — The former dedicated email-receipts feature has been refactored into the AI document processing pipeline. The `received_mails` table schema is changed and legacy data is partly migrated.
- **Google Drive Integration** — A new optional feature for automatically importing documents from a Google Drive folder.
- **Category Learning** — A new feature for storing and reusing AI-suggested category mappings to enhance transaction categorization.

### Breaking Changes

- **Transaction Types Refactored**: The `transaction_types` database table has been removed and replaced with a PHP enum (`App\Enums\TransactionType`).
  - The `transactions` table now uses a `transaction_type` ENUM column instead of a foreign key to the `transaction_types` table.
  - This change cannot be automatically reversed by Laravel migrations, so a backup of your database is essential before proceeding with the migration.

- **Data Migration**: All existing transactions will be automatically migrated from `transaction_type_id` to the new `transaction_type` enum column.
  - IDs 1-8 and 11 map to the active transaction types.
  - IDs 9-10 (previously unused) drop support.
  - **WARNING**: If you have transactions with IDs 9 or 10, the migration will fail. You must either delete these transactions or reassign them to a valid type before running the migration.

- **Email Processing Refactored**: The `received_mails` table has been restructured. The columns `transaction_data`, `processed`, `handled`, and `transaction_id` are dropped.
  - All previously processed received mails (where `processed = true`) are automatically migrated to the new `ai_documents` table.
  - Unprocessed mails are intentionally not converted and will be discarded.
  - The dedicated email processing pages and routes have been removed; email-sourced receipts are now accessible under **AI Documents**.

- **Investment Price Providers Refactored**: The `investment_provider_configs` table has been introduced to store user-specific credentials and settings for investment price providers. Instead of global .env settings, users can now configure providers individually, and the scheduler checks for config availability before dispatching jobs.

### Step-by-step Guide

#### 1. Upgrade to the latest YAFFA 2.x release

Before installing YAFFA 3.x, first update to the latest available YAFFA 2.x release.
This ensures the pre-upgrade safety check command is available in your existing installation.

#### 2. Run the pre-upgrade safety check command (optional but recommended)

Run the following command on the latest YAFFA 2.x release before installing YAFFA 3.x:

```bash
php artisan app:upgrade:check-3x
```

This command is read-only and checks for known data issues that would block the 3.x database migrations.
At the moment, it validates at least the following:

- unsupported legacy `transaction_type_id` values (`9` or `10`)
- negative values in decimal columns that will become `UNSIGNED` in YAFFA 3.x

If the command reports any issues, fix them first, then run the command again until it succeeds.

#### 3. Backup your database

Before running any migrations, create a complete backup of your database.
This is crucial in case anything goes wrong during the migration process, allowing you to restore your data to its previous state.
Additionally, there's no native downgrade path for this migration, so a backup is your safety net if you need to revert for any reason to version 2.x.

```bash
# Example for MySQL/MariaDB
# On Linux/macOS:
mysqldump -u username -p database_name > yaffa_backup_$(date +%Y%m%d).sql

# On Windows (PowerShell):
mysqldump -u username -p database_name > "yaffa_backup_$(Get-Date -Format 'yyyyMMdd').sql"
```

#### 4. Update your `.env` file

Add the following new environment variables to your `.env` file before running migrations, so that the configuration is picked up correctly during the migration and at runtime.

**AI Document Processing (required if using the feature):**

```env
# File upload limits for manual document submission
AI_DOCUMENT_MAX_FILES_PER_SUBMISSION=3
AI_DOCUMENT_MAX_FILE_SIZE_MB=20
AI_DOCUMENT_ALLOWED_TYPES=pdf,jpg,jpeg,png,txt

# Optional file retention (cleanup job is planned, not yet implemented)
# Set to 0 or a negative value to disable
AI_DOCUMENT_FILE_RETENTION_DAYS=90
```

**Tesseract OCR (optional — only needed if you want to process images without a Vision AI model):**

```env
TESSERACT_ENABLED=false
TESSERACT_MODE=binary
TESSERACT_PATH=/usr/bin/tesseract
TESSERACT_HTTP_HOST=localhost
TESSERACT_HTTP_PORT=8888
TESSERACT_HTTP_TIMEOUT=30
```

| Variable                 | Default              | Description                                                        |
| ------------------------ | -------------------- | ------------------------------------------------------------------ |
| `TESSERACT_ENABLED`      | `false`              | Enable Tesseract OCR for image processing                          |
| `TESSERACT_MODE`         | `binary`             | Mode: `binary` (local executable) or `http` (sidecar)              |
| `TESSERACT_PATH`         | `/usr/bin/tesseract` | Path to the tesseract binary (binary mode only)                    |
| `TESSERACT_HTTP_HOST`    | `localhost`          | Tesseract sidecar hostname (http mode; use service name in Docker) |
| `TESSERACT_HTTP_PORT`    | `8888`               | Tesseract sidecar port (http mode only)                            |
| `TESSERACT_HTTP_TIMEOUT` | `30`                 | Request timeout in seconds (http mode only)                        |

After editing your `.env`, clear the config cache:

```bash
php artisan config:clear
```

**Alpha Vantage Investment Price Provider (optional — only needed if you use this provider):**

- Take a note of the value of `ALPHA_VANTAGE_KEY` in your `.env` file, and you can remove this obsolete global setting. (You'll need to re-enter it on the updated UI.)

#### 5. Install the new version of YAFFA

- If you're using the source code, pull the latest changes from GitHub and run `composer install` to update dependencies.
- If you're using Docker, pull the latest image from Docker Hub. See also the **Note for Docker users** section below before restarting your container.

#### 6. Run the migrations

```bash
php artisan migrate
```

This will perform the following changes:

- Add a new `transaction_type` ENUM column to the `transactions` table, migrate all data, and drop the legacy `transaction_type_id` column and `transaction_types` table.
- Create new tables: `ai_documents`, `ai_document_files`, `ai_provider_configs`, `category_learning`, `google_drive_configs`, `ai_user_settings`.
- Add an `ai_document_id` column to the `transactions` table.
- Migrate processed `received_mails` rows into the `ai_documents` table, then drop the legacy `transaction_data`, `processed`, `handled`, and `transaction_id` columns from `received_mails`.

**Note**: The transaction type migration is irreversible after the `transaction_types` table is dropped. Ensure you have a backup before proceeding.

#### 7. Clear caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

#### 8. Rebuild frontend assets (if running from source)

```bash
npm install && npm run build
```

#### 9. Configure Alpha Vantage price provider in the UI (if applicable)

If you have investments with automatic price retrieval using the Alpha Vantage provider, you need to re-enter your API key in the new provider configuration UI, and make sure to test the connection. Make this as soon as possible after the upgrade, because the scheduler will stop working for these investments until the provider config is not configured.

### Note for Docker users

You'll need to adjust your `docker-compose.yml` to reflect the changes in the app infrastructure.

- Decide if you want to use Tesseract OCR as a local service or not. Tesseract is disabled by default, and not needed if you only use Vision AI for document processing or if you don't use document processing at all.
- If you want to use Tesseract OCR, make sure to uncomment the relevant lines in the `depends_on` section of the `app` service, and uncomment the entire `tesseract` service definition as well.
- If using Tesseract in http mode, set `TESSERACT_HTTP_HOST` to the Docker service name (e.g., `tesseract`) and set `TESSERACT_ENABLED=true`.

Make sure to pull the updated images and rebuild your containers after making these changes.

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
```

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
