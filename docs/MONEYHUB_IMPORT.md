# Moneyhub Transaction Import

This guide explains how to import transactions from Moneyhub CSV exports into YAFFA.

## Overview

The Moneyhub import system uses **account aliases** to automatically map Moneyhub account names to YAFFA accounts. Once set up, you can upload Moneyhub CSV files through the web interface or API, and transactions will be automatically assigned to the correct accounts.

**New:** All imported transactions are now tagged with an `import_job_id`, allowing you to easily review and purge entire import batches if needed.

## Quick Start

```bash
# 1. Set up account mappings (one-time setup)
php artisan yaffa:import-moneyhub-mapping /path/to/mapping.csv

# 2. Upload CSV via web interface at /import/moneyhub

# 3. List recent imports
php artisan yaffa:list-imports --with-counts

# 4. Review an import before committing (optional)
php artisan yaffa:purge-import 123 --dry-run

# 5. Purge/revert if needed
php artisan yaffa:purge-import 123
```

## Setup: Import Account Mapping

### 1. Create a CSV Mapping File

Create a CSV file with two columns:
- **Column 1:** Moneyhub account name (as it appears in exports)
- **Column 2:** YAFFA account name (exact match), or "SKIP" to ignore

Example:
```csv
MoneyHub,Jaffa
Amex,American Express 54003
Halifax - International Credit Card,Halifax Credit Card
Pension,SJP Pension
Crypto Wallet,SKIP
```

### 2. Run the Import Command

```bash
php artisan yaffa:import-moneyhub-mapping /path/to/mapping.csv --user=1
```

**Options:**
- `--user=ID` - User ID to apply mappings to (default: 1)
- `--dry-run` - Preview changes without saving

**Example Output:**
```
Processing Moneyhub account mappings for user: Anth

  ✓ Mapped: Amex → American Express 54003
  ✓ Mapped: Halifax - International Credit Card → Halifax Credit Card
  ⊘ Skipping: Crypto Wallet
  ✗ Account not found: 'Old Account' for Moneyhub 'legacy-acct'

=== Summary ===
Updated: 33
Skipped: 20
Not Found: 0
```

### 3. Verify Mappings

Check that aliases were added correctly:
```sql
SELECT name, alias FROM accounts WHERE alias IS NOT NULL;
```

Or via tinker:
```bash
php artisan tinker --execute="App\Models\Account::whereNotNull('alias')->get(['name', 'alias'])"
```

## Using the Moneyhub Import

### Web Interface

1. Navigate to `/import/moneyhub`
2. Upload your Moneyhub CSV export
3. Select a default account (optional - uses aliases if not specified)
4. Click "Upload"

The import runs as a background job. Check status at `/import` page.

### Expected CSV Format

Moneyhub exports should have these columns:
- `DATE` - Transaction date (DD/MM/YYYY format)
- `AMOUNT` - Transaction amount (negative for withdrawals, positive for deposits)
- `DESCRIPTION` - Transaction description
- `CATEGORY` - Moneyhub category
- `CATEGORY GROUP` - Moneyhub category group
- `ACCOUNT` - Moneyhub account name (matched against aliases)
- `TO ACCOUNT` - Transfer destination (if applicable)
- `PROJECT` - Moneyhub project/tag
- `NOTES` - Additional notes

### How Account Matching Works

1. **Explicit account ID:** If you select an account during upload, ALL transactions go to that account
2. **Alias matching:** If no account selected, the system looks up the account by the `ACCOUNT` column value:
   - Checks each account's `alias` field (supports multiple aliases separated by newlines)
   - Case-insensitive matching
   - First match wins

### Transaction Import Rules

The import system supports **TransactionImportRule** for advanced processing:

- **Skip:** Ignore transactions matching certain patterns
- **Merge Payee:** Automatically assign transactions to specific payees
- **Convert to Transfer:** Transform transactions into transfers between accounts

See [IMPORTS_AND_MAINTENANCE.md](IMPORTS_AND_MAINTENANCE.md) for more details on rules.

## Managing Imports

### List Recent Imports

View all recent import jobs with their status:

```bash
# List all imports
php artisan yaffa:list-imports

# With transaction counts (slower)
php artisan yaffa:list-imports --with-counts

# Filter by user or status
php artisan yaffa:list-imports --user=1 --status=completed

# Show more results
php artisan yaffa:list-imports --limit=50
```


### Transactions Not Being Created

If upload succeeds but transaction count is 0:

1. **Check for duplicates:** The system skips transactions that look like duplicates (same date, accounts, amount within 60 days)
2. **Check import rules:** A rule with `action='skip'` might be matching
3. **Check dry-run output:** Review what the purge command shows for that import
4. **Check logs:** Look in `storage/logs/laravel.log` for errors

### Import Job Stuck

If an import shows "started" status but never completes:

```bash
# Check stuck jobs
php artisan jobs:analyze --days=1

# Fix stuck jobs
php artisan jobs:analyze --fix-stuck
```
**Output:**
```
+-----+------+-------------------------+----------+------+------------------+--------------+
| ID  | User | File                    | Status   | Rows | Created          | Transactions |
+-----+------+-------------------------+----------+------+------------------+--------------+
| 234 | 1    | moneyhub-2026-01.csv   | completed| 156  | 2026-01-04 11:30 | 142          |
| 233 | 1    | moneyhub-2025-12.csv   | completed| 203  | 2025-12-29 13:33 | 187          |
+-----+------+-------------------------+----------+------+------------------+--------------+
```

### Review an Import (Dry Run)

Before committing to an import, you can review what was imported:

```bash
php artisan yaffa:purge-import 234 --dry-run
```

This shows:
- Import details (file, date, status)
- Transaction count and date range
- Affected accounts
- Sample transactions (first 5)

**No changes are made** in dry-run mode.

### Purge/Revert an Import

If you need to remove an entire import batch:

```bash
# With confirmation prompt
php artisan yaffa:purge-import 234

# Skip confirmation (be careful!)
php artisan yaffa:purge-import 234 --force
```

This will:
1. Delete all transactions tagged with this `import_job_id`
2. Delete related transaction items and configs
3. Mark the import job as "purged"
4. Show affected accounts that may need recalculation

**Important:** This is permanent! Use `--dry-run` first to verify.

### Why Purge an Import?

Common scenarios:
- **Testing:** Uploaded test data and want to clean it up
- **Wrong mapping:** Realized account aliases were incorrect
- **Duplicate upload:** Accidentally uploaded the same file twice
- **Data errors:** Moneyhub export had issues you didn't catch initially

## Import Tracking

All transactions created after 2026-01-04 are automatically tagged with their `import_job_id`. This allows:

- **Batch management:** Review/delete entire imports at once
- **Audit trail:** Know which transactions came from which file
- **Easy rollback:** Revert bad imports without affecting other data
- **Testing safety:** Confidently test imports knowing you can purge them

### Check Transaction Import Source

Via database:
```sql
SELECT id, date, import_job_id, comment 
FROM transactions 
WHERE import_job_id = 234 
LIMIT 10;
```

Via tinker:
```php
Transaction::where('import_job_id', 234)->count();
```

## Troubleshooting

### "Account not found" Errors

If accounts aren't being matched:

1. **Check exact name match:**
   ```bash
   php artisan tinker --execute="echo App\Models\AccountEntity::where('name', 'Your Account Name')->exists() ? 'Found' : 'Not Found'"
   ```

2. **List all account names:**
   ```bash
   php artisan tinker --execute="App\Models\AccountEntity::all()->pluck('name')->each(fn(\$n) => print(\$n . PHP_EOL))"
   ```

3. **Re-run mapping with correct names:**
   ```bash
   php artisan yaffa:import-moneyhub-mapping /path/to/corrected-mapping.csv --dry-run
   ```

### Updating Aliases

To add more aliases to an existing account:
```php
$account = s
- `app/Console/Commands/ImportMoneyhubAccountMapping.php` - Import account aliases from CSV
- `app/Console/Commands/ListImports.php` - List recent import jobs with stats
- `app/Console/Commands/PurgeImport.php` - Purge/revert import batches

### Services
- `app/Services/TransactionUploadService.php` - Core parsing logic
  - `parseMoneyHubCsv()` - Parse Moneyhub CSV format
  - `matchAccountByAlias()` - Match account by alias
  - `matchOrCreatePayee()` - Handle payee matching
  - `findMatchingRule()` - Apply import rules

### Jobs
- `app/Jobs/ProcessMoneyhubUpload.php` - Background import processing
  - Now tags transactions with `import_job_id`

### Controllers
- `app/Http/Controllers/ImportController.php` - Web interface handlers
  - `moneyhubUpload()` - Show upload form
  - `handleMoneyhubUpload()` - Process upload

### Models
- `app/Models/Transaction.php` - Now includes `import_job_id` field
- `app/Models/ImportJob.php` - Tracks import jobs and their status

### Routes
- `GET /import/moneyhub` - Upload form
- `POST /import/moneyhub` - Handle upload
- `GET /import` - View import history

### Database
- Migration: `2026_01_04_115103_add_import_job_id_to_transactions_table.php`
- Migration: `2026_01_04_114542_add_alias_to_accounts_and_investments_tables.php`
- Tables: 
  - `transactions.import_job_id` (nullable, indexed, with FK to import_jobs)
  -rvices/TransactionUploadService.php` - Core parsing logic
  - `parseMoneyHubCsv()` - Parse Moneyhub CSV format
  - `matchAccountByAlias()` - Match account by alias
  - `matchOrCreatePayee()` - Handle payee matching
  - `findMatchingRule()` - Apply import rules

### Jobs
- `app/Jobs/ProcessMoneyhubUpload.php` - Background import processing

### Controllers
- `app/Http/Controllers/ImportController.php` - Web interface handlers
  - `moneyhubUpload()` - Show upload form
  - `handleMoneyhubUpload()` - Process upload

### Routes
- `GET /import/moneyhub` - Upload form
- `POST /import/moneyhub` - Handle upload
- `GET /import` - View import history

5. **Test first:** Use `--dry-run` flags to test both mapping imports and transaction purges before committing
6. **Track imports:** Check `yaffa:list-imports --with-counts` after each upload to verify transaction count
7. **Safe experimentation:** With import tracking, you can confidently test different import configurations knowing you can always purge and retry

## Example Workflow

```bash
# 1. Export transactions from Moneyhub (CSV format)
# 2. Save to: ~/Downloads/moneyhub-export-2026-01.csv

# 3. Import mapping (first time setup or when adding new accounts)
php artisan yaffa:import-moneyhub-mapping ~/Downloads/account-mapping.csv

# 4. Upload via web interface at https://jaffa.test/import/moneyhub
#    Or use the API endpoint

# 5. List imports to get the ID
php artisan yaffa:list-imports --with-counts

# 6. Review the import (optional but recommended for first upload)
php artisan yaffa:purge-import 234 --dry-run

# 7. If everything looks good, you're done!
#    If something's wrong, purge and retry:
php artisan yaffa:purge-import 234 --force

# 8. Fix any issues (update mapping, rules, etc.) and upload again

# 9. Monitor queue processing
php artisan jobs:analyze --days=1
# 3. Import mapping (first time setup)
php artisan yaffa:import-moneyhub-mapping ~/Downloads/account-mapping.csv

# 4. Upload via web interface at https://jaffa.test/import/moneyhub
#    Or use the API endpoint

# 5. Monitor import progress
php artisan jobs:analyze --days=1

# 6. Check results at https://jaffa.test/import
```

## Advanced: API Upload

```bash
# Upload CSV via API
curl -X POST https://jaffa.test/api/transaction-upload \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@moneyhub-export.csv" \
  -F "format=moneyhub"
```

See [API documentation](../routes/api.php) for more details.
