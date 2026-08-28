# Upgrade guide

This document describes the breaking changes of major versions, and also includes notable upgrade notes for changes within a major version when extra operator action is recommended.

Table of contents:

- [Upgrade from YAFFA 3.x to 4.x](#upgrade-from-yaffa-3x-to-4x)
- [Upgrade within YAFFA 3.x](#upgrade-within-yaffa-3x)
- [Upgrade from YAFFA 2.x to 3.x](#upgrade-from-yaffa-2x-to-3x)
- [Upgrade from YAFFA 1.x to 2.x](#upgrade-from-yaffa-1x-to-2x)

## Upgrade from YAFFA 3.x to 4.x

This version removes the `budget` flag from `Transaction` and replaces it with a standalone `Budget` entity: a category-level spending/income target with no linked transaction. See [`.ai/docs/assets/budget/budget.md`](.ai/docs/assets/budget/budget.md) and [`.ai/docs/assets/transactions/schedules.md`](.ai/docs/assets/transactions/schedules.md) for the concept-level explanation.

A scheduled standard withdrawal/deposit's categorized items now always count toward category budget comparison — this used to require a separate `budget` flag on the transaction, which no longer exists and has no replacement opt-in/opt-out.

### Breaking Changes

- **`transactions.budget` column removed.** There is no replacement flag. A scheduled standard withdrawal/deposit with categorized items counts toward budget comparison automatically; a standalone target with no linked transaction is now a `Budget` row instead.
- **New standalone `Budget` entity and `budgets` table.** Existing transactions that were budget-only (`schedule = false, budget = true`, i.e. created via the old "Budget" checkbox with no schedule) are automatically converted to one `Budget` row per distinct category, then **hard-deleted** from `transactions` — this data migration has no downgrade path (see below).
- **`transaction_details_standard.account_from_id`/`account_to_id` are now `NOT NULL`.** These were only nullable to support the old budget-only transaction case; that case no longer exists after the conversion above.
- **API changes:**
  - `GET /api/v1/transactions/scheduled-items` — the `type` query parameter no longer accepts `budget`, `budget_only`, `both`, or `any`; only `schedule` and `none` remain meaningful. A new `includeBudgets=1` parameter merges standalone `Budget` rows into the response (used by the Schedules & Budgets report only).
  - `ReportApiController`'s budget-vs-actual chart endpoint response shape changed: each period entry now also includes a `budgetBreakdown` array listing the individual `Budget` rows (with `account_id`/`account_name`) that contributed to the total, and a `scheduleBreakdown` array for the schedule-derived side.
  - New CRUD endpoints: `GET/POST /api/v1/budgets`, `GET/PATCH/DELETE /api/v1/budgets/{budget}`.
- **UI change:** the "Budget" checkbox/section on the standard transaction form is removed. Standalone Budgets are created, edited, and deleted from the existing Schedules & Budgets report page (Reports → Schedules and Budgets) instead, alongside real schedules.
- If you have any custom integrations or scripts against the endpoints above, update them before upgrading.

### Step-by-step Guide

#### 1. Upgrade to the latest YAFFA 3.x release

Before installing YAFFA 4.x, first update to the latest available YAFFA 3.x release (3.6.0 or later). This ensures the pre-upgrade safety check command described below is available in your existing installation.

#### 2. Run the pre-upgrade safety check command (optional but recommended)

Run the following command on your current YAFFA 3.x release before installing YAFFA 4.x:

```bash
php artisan app:check:budget-migration
```

This command is read-only and reports any pre-existing data it cannot safely convert:

- a budget-only transaction with zero transaction items
- a budget-only transaction where the only non-null account side is actually a payee, not a real account
- a transfer or investment transaction with a stray `budget = true` flag (should never happen, but was never enforced at the database level)
- a budget-only transaction whose currency doesn't match its linked account's current currency

The 4.x migration **refuses to run** while any of these are reported, to avoid silently dropping or misattributing data. If the command reports issues, resolve them (edit or delete the flagged transactions) and run it again until it succeeds.

**Note:** this command is removed again once you're on 4.x — once the conversion has run, the state it checks for can no longer occur.

#### 3. Backup your database

Before running any migrations, create a complete backup of your database. There is no native downgrade path for the budget-to-`Budget` data conversion (see below), so a backup is your only way back to 3.x if something goes wrong.

```bash
# Example for MySQL/MariaDB
mysqldump -u username -p database_name > yaffa_backup_$(date +%Y%m%d).sql
```

See the [2.x to 3.x guide](#upgrade-from-yaffa-2x-to-3x) above for Docker-volume backup examples if you're running the packaged Docker setup.

#### 4. Install the new version and apply all changes

No new required environment variables are introduced by this upgrade. However, this release bumps the framework to Laravel 13, which changed the *default* values used for `CACHE_PREFIX`, `REDIS_PREFIX`, and `SESSION_COOKIE` when those are left unset (the separator used to build them from `APP_NAME` changed from `_` to `-`, e.g. `yaffa_cache_` → `yaffa-cache-`). If your `.env` doesn't already set these explicitly, add them to pin the previous values and avoid orphaning existing cache/session/queue data on deploy:

```env
CACHE_PREFIX=yaffa_cache_
REDIS_PREFIX=yaffa_database_
SESSION_COOKIE=yaffa_session
```

(Substitute `yaffa` with the slug of your own `APP_NAME` if you've customized it.)

##### Docker users

```bash
docker compose pull
docker compose stop app scheduler
docker compose up -d db
docker compose up -d app scheduler
```

The container entrypoint automatically runs migrations, clears caches, and rebuilds assets on startup.

**Caddy reverse proxy is now a Compose profile.** `docker/docker-compose.yml`'s Caddy service is no longer a commented-out block you manually uncomment — it's gated behind the `https` Compose profile, started with `docker compose --profile https up -d`. If you previously uncommented the old Caddy block by hand, pulling the new `docker-compose.yml` will conflict with (or silently discard) that edit; re-apply your Caddyfile/domain setup and switch to the `--profile https` flag instead of a manual edit. If you run with this profile, also set `APP_PORT` in your `.env` (e.g. `APP_PORT=127.0.0.1:8080`) so the `app` service doesn't also try to bind host port 80 alongside Caddy — see the comments in `docker-compose.yml` for details.

##### Source code users

```bash
git pull
composer install
php artisan migrate
php artisan config:clear
php artisan cache:clear
php artisan view:clear
npm install && npm run build
```

The migration step will:

- Create the new `budgets` table.
- Convert every remaining budget-only transaction into `Budget` row(s) (one per distinct category, summing amounts within a category), carrying over its account (only if the non-null side is a real account, not a payee), transaction type, and recurrence settings — then hard-delete the source transaction. This step refuses to proceed if step 2's check would report any issue, even if you skipped running it manually.
- Drop the `transactions.budget` column.
- Make `transaction_details_standard.account_from_id`/`account_to_id` `NOT NULL`.

**Note**: the data conversion is irreversible once the `budget` column is dropped. Ensure you have a backup (step 3) before proceeding.

#### 5. Review your converted Budgets (recommended)

After upgrading, open **Reports → Schedules and Budgets** and filter to "Budget" rows to review what was converted from your old budget-only transactions. Each converted `Budget` is account-scoped only if its original transaction had a real account attached; otherwise it's account-agnostic. No further action is required unless you want to adjust the converted targets.

## Upgrade within YAFFA 3.x

## Upgrade within YAFFA 3.x

Most 3.x upgrades do not require any special manual steps beyond the usual application update procedure for your hosting option.

### API Access Tokens & Two-Factor Authentication

This release adds two new opt-in security features, both managed from `/user/settings`:

- **Personal access tokens** — mint a scoped bearer token (`read`, `write`, and/or `settings`) for calling the `/api/v1/*` API outside the browser.
- **Two-factor authentication (TOTP)** — an optional second factor on top of your normal login.

Neither feature requires any action to upgrade, and neither is enabled by default. A few notes if you plan to use them:

- **Token abilities are enforced from day one.** A token scoped to `read` only will get a `403` on any action outside that scope — this isn't a narrowing of previously working access, since no earlier YAFFA version had a supported way to create or use a personal access token in the first place.
- **Exception:** if you previously used `php artisan tinker` (or similar direct DB/console access) to manually call `$user->createToken(...)` — an unsupported, undocumented path that happened to work because the underlying Sanctum table already existed — that token now has its `abilities` enforced like any other. Revoke it and re-create it from `/user/settings` so its scope matches what you actually intend to grant.
- New environment variables, both optional (see `.env.example` for defaults): `API_TOKEN_MAX_LIFETIME_DAYS` (maximum lifetime, in days, selectable when creating a token — default `365`) and `SCRAMBLE_PROD_AUTH` (controls who can view the auto-generated API docs at `/docs/api` outside the `local` environment — default `none`, i.e. hidden).

### API Response Precision (Decimal String Wire Format)

This release replaces float-based money/quantity arithmetic with exact decimal arithmetic (`brick/math`/`brick/money`) across transactions and investments, to eliminate float-precision drift in split/allocation totals, investment valuations, and monthly summary balances. As part of this, several `/api/v1/*` fields now serialize as **decimal strings instead of JSON numbers**:

- `transaction_items[].amount`
- `config.amount_from` / `config.amount_to`, and their computed `amount_from_base` / `amount_to_base` / `amount_in_base` counterparts (standard transactions)
- `config.price` / `config.commission` / `config.tax` / `config.dividend` / `config.quantity` (investment transactions)
- `investment_prices[].price`
- `accounts[].opening_balance`
- `currency_rates[].rate`

**Action required if you have a custom API integration**: parse these fields as strings (e.g. into a decimal type) rather than assuming a JSON number. The bundled frontend already expects this format, so no action is needed there. Report endpoints (`/api/v1/reports/*`) are unaffected — they still return JSON numbers, since their aggregates are only converted to exact decimals internally and collapsed back to a float at the response boundary.

Two more precision-related changes, transparent to a normal upgrade:

- `transaction_details_investment.price` is widened from `DECIMAL(10,4)` to `DECIMAL(20,10)` by a new migration, matching `investment_prices.price`'s existing scale. This runs automatically with the rest of the migrations and is non-destructive.
- `ext-bcmath` is now a required PHP extension (declared in `composer.json`). It's already present in the Sail dev image; if you run PHP outside Sail/Docker, confirm it's compiled in before upgrading (`php -m | grep bcmath`) — it wasn't previously listed among YAFFA's required extensions.

**Action required if you have investment transactions predating a currency change on an account or investment**: an investment transaction's `price` (in the investment's currency) and its `commission`/`tax`/`dividend` (in the account's currency) are now combined with exact `Brick\Money\Money` arithmetic, which requires both sides to share a currency. New transactions are already prevented from mismatching (`TransactionRequest`'s account/investment currency check, and the currency-change confirmation now shown in the account/investment edit forms), but a transaction recorded _before_ either guard existed — back when the account or investment's currency was later changed — may still have a mismatched pair. For such a row, `cashflow_value` is now computed as `null` instead of throwing, and a `warning`-level log entry ("Investment transaction cash flow spans mismatched currencies (legacy data)") is written with the transaction ID. Search your logs for that message after upgrading, and manually correct the identified transactions (or the account/investment currency) to restore their cash-flow value.

### Docker users switching from `mysql/mysql-server:8.0` to `mysql:8.0`

If the updated `docker-compose.yml` changes the database image from `mysql/mysql-server:8.0` to `mysql:8.0`, review the Docker notes in the 3.x upgrade section below before restarting the stack.

In particular:

- Keep the existing named database volume so the new container reuses the current data directory.
- Make sure `DB_HOST` still matches the database service name from your Docker Compose file. In the packaged YAFFA Docker setup, the service is named `db`.
- Verify that your Docker deployment does not use `DB_USERNAME=root`, because the official `mysql` image does not support initializing `MYSQL_USER=root`.
- Prefer restarting the database first, then the YAFFA application containers, to minimize user impact during the first startup on the new image.

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

- **API Changes**: Several API endpoints have been changed or removed with the intent of adopting versioning and a more consistent naming convention. If you have any custom integrations or scripts that interact with the YAFFA API, you will need to review and update them according to the new API structure.

- **Database Changes**: Some database columns were not marked as `signed` in YAFFA 2.x, even though they should be. In YAFFA 3.x, these columns are now `UNSIGNED`, which means that any negative values in these columns will cause the migration to fail. Why the app should have prevented capturing such values, this is still a risk during the migration. The pre-upgrade safety check command (see below) can help identify such issues before running the migration.

### Step-by-step Guide

#### 1. Upgrade to the latest YAFFA 2.x release

Before installing YAFFA 3.x, first update to the latest available YAFFA 2.x release. This ensures the pre-upgrade safety check command is available in your existing installation.

It is also needed to be on the latest 2.x release, as all migration files of the 2.x series are moved into a schema file, and the migration path might not be complete if you are on an older 2.x release.

#### 2. Run the pre-upgrade safety check command (optional but recommended)

Run the following command on the latest YAFFA 2.x release before installing YAFFA 3.x:

```bash
php artisan app:upgrade:check-3x
```

This command is read-only and checks for known data issues that would block the 3.x database migrations.
At the moment, it validates the following:

- presence of unsupported legacy `transaction_type_id` values (`9` or `10`)
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

# Example for Docker, backing up a named volume (Windows PowerShell):
docker run --rm -v yaffa_yaffa_db:/data -v ${PWD}:/backup alpine tar czf /backup/yaffa_db.tar.gz -C /data .

# Example for Docker, backing up YAFFA database from the MySQL container (Windows PowerShell):
docker exec yaffa-db-1 mysqldump -u<username> -p<password> yaffa_db 2>$null `  | Out-File -FilePath yaffa_sail-mysql.sql -Encoding UTF8

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

**Source code users:** After editing your `.env`, clear the config cache before continuing:

```bash
php artisan config:clear
```

Docker users can skip this — the container entrypoint handles cache clearing automatically on restart.

**Alpha Vantage Investment Price Provider (optional — only needed if you use this provider):**

- Take a note of the value of `ALPHA_VANTAGE_KEY` in your `.env` file, and you can remove this obsolete global setting. (You'll need to re-enter it on the updated UI.)

#### 5. Install the new version of YAFFA and apply all changes

From this point, the steps differ depending on your hosting option. Follow only the section that applies to you.

##### Docker users

1. **Update your `docker-compose.yml`** to reflect the infrastructure changes:
   - Decide whether to use Tesseract OCR as a local service. It is disabled by default and not needed if you only use a Vision AI model for document processing, or if you don't use document processing at all.
   - If you want to use Tesseract OCR, uncomment the relevant lines in the `depends_on` section of the `app` service and uncomment the entire `tesseract` service definition.
   - If using Tesseract in `http` mode, set `TESSERACT_HTTP_HOST` to the Docker service name (e.g., `tesseract`) and set `TESSERACT_ENABLED=true`.
   - Make sure `DB_HOST` matches the database service name in the compose file. In the default packaged Docker setup, this is `db`.
   - If you are updating to a compose file that switches the database image from `mysql/mysql-server:8.0` to `mysql:8.0`, keep the existing named database volume in place. This allows the upgraded container to reuse the current data directory instead of initializing a fresh database.
   - Before the first start on the new MySQL image, verify that your Docker deployment does not use `DB_USERNAME=root`. The official `mysql` image does not support initializing `MYSQL_USER=root`. Use a dedicated application user instead, such as the default `yaffa_user` from `.env.example`.

2. **Pull the latest image and restart your container**:

   ```bash
   docker compose pull
   docker compose stop app scheduler
   docker compose up -d db
   docker compose up -d app scheduler
   ```

   This restart order minimizes user impact during the MySQL image swap by letting the database finish its first startup on the new image before YAFFA reconnects.

   If you use different service names, adapt the commands accordingly. Avoid removing the database volume unless you intentionally want a fresh empty database.

   The container entrypoint automatically runs migrations, clears caches, and rebuilds assets on startup. No further action is required.

##### Source code users

1. **Pull the latest changes** from GitHub:

   ```bash
   git pull
   ```

2. **Install updated dependencies**:

   ```bash
   composer install
   ```

3. **Run the migrations**:

   ```bash
   php artisan migrate
   ```

   This will perform the following changes:
   - Add a new `transaction_type` ENUM column to the `transactions` table, migrate all data, and drop the legacy `transaction_type_id` column and `transaction_types` table.
   - Create new tables: `ai_documents`, `ai_document_files`, `ai_provider_configs`, `category_learning`, `google_drive_configs`, `ai_user_settings`.
   - Add an `ai_document_id` column to the `transactions` table.
   - Migrate processed `received_mails` rows into the `ai_documents` table, then drop the legacy `transaction_data`, `processed`, `handled`, and `transaction_id` columns from `received_mails`.

   **Note**: The transaction type migration is irreversible after the `transaction_types` table is dropped. Ensure you have a backup before proceeding.

4. **Clear caches**:

   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

5. **Rebuild frontend assets**:
   ```bash
   npm install && npm run build
   ```

#### 6. Configure Alpha Vantage price provider in the UI (if applicable)

If you have investments with automatic price retrieval using the Alpha Vantage provider, you need to re-enter your API key in the new provider configuration UI, and make sure to test the connection. Make this as soon as possible after the upgrade, because the scheduler will stop working for these investments until the provider config is not configured.

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
