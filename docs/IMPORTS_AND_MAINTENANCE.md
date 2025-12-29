**Imports And Maintenance**

- **Purpose**: Documentation for the asynchronous CSV import pipeline, idempotency, and related maintenance / repair artisan commands added to the codebase.

**Quick Links**
- **Commands**: `fix:transfers-into`, `report:transfer-issues`, `debug:transaction`, `import-rules:run`
- **Import model**: `app/Models/ImportJob.php`
- **Import jobs**: `app/Jobs/ProcessMoneyhubImport.php`, `app/Jobs/ProcessMoneyhubUpload.php`
- **Import services**: `app/Services/InvestmentCsvUploadService.php`, `app/Services/TransactionUploadService.php`
- **Controller endpoints**: `app/Http/Controllers/ImportController.php`, `app/Http/Controllers/InvestmentCsvUploadController.php`
- **Audit reports**: `storage/app/reports/`
- **Idempotency table**: `database/migrations/*create_import_row_hashes_table.php`

**1. Import flow (high level)**
- **Accept file**: Controller stores the uploaded CSV to storage/app and creates an `ImportJob` record.
- **Dispatch job**: A queued job (`ProcessMoneyhubImport` or `ProcessMoneyhubUpload`) is dispatched to process the stored file asynchronously.
- **Stream & chunk**: The job streams the CSV (LazyCollection/fgetcsv), breaks it into chunks (configurable chunk size), and processes each chunk inside a DB transaction.
- **Per-row idempotency**: Each row produces a SHA1 hash that is stored in `import_row_hashes` to avoid duplicates on retries.
- **Progress & errors**: The `ImportJob` row is updated with `processed_rows`, `total_rows`, `status`, and `errors` as the job runs. A polling endpoint is available: `GET /imports/{import}/status`. Errors can be downloaded from `GET /imports/{import}/errors`.

**2. Key files & where to look**
- **ImportJob model & migration**: `app/Models/ImportJob.php` and `database/migrations/*_create_import_jobs_table.php` — stores progress, status, errors, timestamps.
- **Idempotency**: `database/migrations/*_create_import_row_hashes_table.php` — prevents re-processing the same CSV row across retries.
- **Investment CSV processing**: `app/Services/InvestmentCsvUploadService.php` — handles parsing, date handling, chunk processing, and writing transactions/investments.
- **MoneyHub / general transaction upload**: `app/Services/TransactionUploadService.php` — existing uploader used by `ProcessMoneyhubUpload` job.
- **Queued jobs**: `app/Jobs/ProcessMoneyhubImport.php` and `app/Jobs/ProcessMoneyhubUpload.php` — orchestration of streaming, chunking and reporting.
- **Controllers / endpoints**: `app/Http/Controllers/ImportController.php` (status + errors + moneyhub upload POST) and `app/Http/Controllers/InvestmentCsvUploadController.php` (upload + create `ImportJob`).

**3. Artisan maintenance & repair commands**
- **`report:transfer-issues`**: Scan the DB for transfer transactions where one or both sides are missing or not an `account` entity. Usage:

```powershell
php artisan report:transfer-issues
```

- **`debug:transaction {id}`**: Dump a transaction with relations to help debug show-page errors. Usage:

```powershell
php artisan debug:transaction 3421 --raw
```

- **`import-rules:run`**: Run configured transaction import rules across existing transactions. Supports `--user`, `--days`, and `--dry-run`.

```powershell
php artisan import-rules:run --dry-run --days=30
```

- **`fix:transfers-into {target}`**: Conservative repair command added to fix transfers where one side is a non-account (e.g., payee). It sets `account_from` to the account-side and `account_to` to the provided `target` account entity id.

Options:
- `--since=YYYY-MM-DD` : limit scanned transactions
- `--dry-run` : preview planned changes without writing
- `--confirm` : required to actually perform writes

Example (dry-run):

```powershell
php artisan fix:transfers-into 5 --dry-run
```

Example (apply):

```powershell
php artisan fix:transfers-into 5 --confirm
```

The command writes an audit JSON to `storage/app/reports/transfer_fixes_YYYYMMDD_HHMMSS.json` when run with `--confirm`.

**4. Audit and rollback guidance**
- **Audit location**: `storage/app/reports/` (files named like `transfer_fixes_YYYYMMDD_HHMMSS.json`). These contain the `old_from`, `old_to`, `new_from`, `new_to`, transaction ids and config ids.
- **Rollback approach**: Use the audit file to generate a reverse update script. Minimal example (Tinker / SQL):

```powershell
php artisan tinker
>>> $audit = json_decode(file_get_contents(storage_path('app/reports/transfer_fixes_XXXX.json')), true);
>>> foreach ($audit['changes'] as $c) { DB::table('transaction_details_standard')->where('id',$c['config_id'])->update(['account_from_id'=>$c['old_from'],'account_to_id'=>$c['old_to']]); }
```

Or generate a SQL script from the JSON and run it in your DB client if you prefer not to use Tinker.

**5. Verification checklist (post-fix)**
- Spot-check sample transactions listed in the audit: verify `account_from_id` and `account_to_id` match expectations.
- Open affected transactions in the UI (`/transactions/{id}`) to ensure pages render without errors.
- Re-run `php artisan report:transfer-issues` — expected result: `Found 0 suspicious transfer(s)` if fixes covered all candidates.
- Ensure queued workers processed any pending import jobs:

```powershell
php artisan queue:work --tries=3 --timeout=300
```

- Check account monthly summaries and balances for unexpected shifts; consider running `php artisan yaffa:fix-stuck-calculations` if available.

**6. Safety notes & best practices**
- Always run `--dry-run` first and inspect the generated plans/audit.
- Require `--confirm` for writes; this prevents accidental mass updates.
- Keep a copy of the audit file off the server (or in versioned storage) before performing any rollbacks.
- If unsure about many results, treat fixes as proposals and review a representative sample before applying.

**7. Tests & follow-ups (TODO)**
- Add unit tests for `FixTransfersIntoAccount` (dry-run result, ambiguous-skip behavior, commit behavior).
- Add feature tests for import job progress and error export endpoints.

**8. Who to contact / maintainers**
- App code lives under `app/` — reach out to the owner/maintainers in the repo for confirmation before large-scale repairs. File owners are indicated in Git history.

---

If you want, I can also:
- Generate a one-click rollback script from the audit JSON.
- Add a small admin blade view to run `fix:transfers-into` with preview/confirm buttons.
- Add automated tests for these commands.
