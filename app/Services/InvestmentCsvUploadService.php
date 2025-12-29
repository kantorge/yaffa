<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\AccountEntity;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class InvestmentCsvUploadService
{
    protected $apiKey;
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function process($file, $userId)
    {
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('trim', $rows[0]);
        $data = array_slice($rows, 1);
        $created = 0;
        $updated = 0;
        $errors = [];
        foreach ($data as $row) {
            $row = array_combine($header, $row);
            $investmentName = trim($row['Investment Name']);
            $legalName = trim($row['Legal Name']);
            $groupName = trim($row['Group']);
            $fundName = trim($row['Fund']);
            $accountName = 'Fuel Ventures - ' . $fundName;
            $quantity = floatval(str_replace(',', '', $row['Shares or units']));
            $price = floatval(preg_replace('/[^\d.]/', '', $row['Price']));
            $fees = floatval(preg_replace('/[^\d.]/', '', $row['Fees']));
            // Parse date from CSV. Support common formats like d/m/Y and Y-m-d
            $dateString = trim($row['Investment date'] ?? '');
            $date = null;
            if ($dateString === '') {
                $errors[] = "Missing Investment date for {$investmentName}";
                continue;
            }
            try {
                // Try explicit dd/mm/YYYY
                $date = Carbon::createFromFormat('d/m/Y', $dateString);
            } catch (\Exception $e1) {
                try {
                    // Try ISO format
                    $date = Carbon::createFromFormat('Y-m-d', $dateString);
                } catch (\Exception $e2) {
                    try {
                        // Last resort: let Carbon try to parse automatically
                        $date = Carbon::parse($dateString);
                    } catch (\Exception $e3) {
                        $errors[] = "Invalid date format for {$investmentName}: {$dateString}";
                        continue;
                    }
                }
            }
            // Lookup company ID from Companies House
            $symbol = $this->lookupCompanyId($legalName);
            // Find or create group
            $group = InvestmentGroup::firstOrCreate(['name' => $groupName, 'user_id' => $userId]);
            
            // Find existing AccountEntity by name for this user
            $accountEntity = AccountEntity::where('name', $accountName)
                ->where('user_id', $userId)
                ->where('config_type', 'account')
                ->first();
            
            // Get currency from account, default to GBP
            $currencyId = 1;
            if ($accountEntity && $accountEntity->config) {
                $currencyId = $accountEntity->config->currency_id ?? 1;
            }
            
            // Find or create investment
            $investment = Investment::firstOrCreate([
                'name' => $investmentName,
                'user_id' => $userId,
            ], [
                'symbol' => $symbol,
                'investment_group_id' => $group->id,
                'currency_id' => $currencyId,
            ]);

            if ($accountEntity) {
                $account = Account::find($accountEntity->config_id);
            } else {
                // Ensure there's an account group for the user; use the first or create a default
                $accountGroupId = null;
                $existingGroup = \App\Models\AccountGroup::where('user_id', $userId)->first();
                if ($existingGroup) {
                    $accountGroupId = $existingGroup->id;
                } else {
                    $newGroup = \App\Models\AccountGroup::create(['name' => 'Imported', 'user_id' => $userId]);
                    $accountGroupId = $newGroup->id;
                }

                // Create the account config (accounts table does not store name)
                $account = Account::create([
                    'opening_balance' => 0,
                    'account_group_id' => $accountGroupId,
                    'currency_id' => 1,
                ]);

                // Create the AccountEntity which holds the name and links to the account config
                $accountEntity = AccountEntity::create([
                    'name' => $accountName,
                    'active' => true,
                    'config_type' => 'account',
                    'config_id' => $account->id,
                    'user_id' => $userId,
                ]);
            }
            // Create buy transaction
            $tdi = TransactionDetailInvestment::create([
                'account_id' => $accountEntity->id,
                'investment_id' => $investment->id,
                'price' => $price,
                'quantity' => $quantity,
                'commission' => $fees,
            ]);
            $buyTransaction = Transaction::create([
                'user_id' => $userId,
                'config_type' => 'investment',
                'config_id' => $tdi->id,
                'transaction_type_id' => 4, // Buy
                'date' => $date,
            ]);

            // Set currency and cashflow_value after creation (fields not fillable via mass assignment)
            $currencyId = $account->currency_id ?? 1;
            $commissionAmount = $tdi->commission ?? 0;
            $cashflow = -1 * ($price * $quantity) - $commissionAmount;
            $buyTransaction->currency_id = $currencyId;
            $buyTransaction->cashflow_value = $cashflow;
            $buyTransaction->saveQuietly();
            $created++;
        }
        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Process a stored CSV file path in streaming/chunked mode.
     * $import may be an ImportJob model instance to receive progress updates.
     */
    public function processFromStoredFile(string $filePath, $userId, $import = null)
    {
        $errors = [];
        $created = 0;
        $updated = 0;
        $processed = 0;

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            $errors[] = 'unable_to_open_file';
            return ['processed' => 0, 'errors' => $errors];
        }

        // Read header
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            $errors[] = 'empty_or_invalid_csv';
            return ['processed' => 0, 'errors' => $errors];
        }
        $header = array_map('trim', $header);

        $chunkSize = 200;
        $chunk = [];

        while (($row = fgetcsv($handle)) !== false) {
            $chunk[] = $row;
            if (count($chunk) >= $chunkSize) {
                $res = $this->processChunk($chunk, $header, $userId, $import);
                $processed += $res['processed'];
                $created += $res['created'];
                $updated += $res['updated'];
                $errors = array_merge($errors, $res['errors']);
                if ($import) {
                    $import->increment('processed_rows', $res['processed']);
                    $import->save();
                }
                $chunk = [];
            }
        }

        // final chunk
        if (count($chunk) > 0) {
            $res = $this->processChunk($chunk, $header, $userId, $import);
            $processed += $res['processed'];
            $created += $res['created'];
            $updated += $res['updated'];
            $errors = array_merge($errors, $res['errors']);
            if ($import) {
                $import->increment('processed_rows', $res['processed']);
                $import->save();
            }
        }

        fclose($handle);
        return ['processed' => $processed, 'created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    protected function processChunk(array $rows, array $header, $userId, $import = null)
    {
        $created = 0;
        $updated = 0;
        $errors = [];
        $processed = 0;

        DB::transaction(function() use ($rows, $header, $userId, $import, &$created, &$updated, &$errors, &$processed) {
            foreach ($rows as $rowIdx => $row) {
                $processed++;
                try {
                    $row = array_combine($header, $row);
                } catch (\Throwable $e) {
                    $errors[] = ['row' => $processed, 'error' => 'malformed_row'];
                    continue;
                }

                // Idempotency: compute a per-row hash and skip if already imported for this user
                $rowHash = sha1(json_encode($row));
                $exists = DB::table('import_row_hashes')
                    ->where('user_id', $userId)
                    ->where('row_hash', $rowHash)
                    ->exists();
                if ($exists) {
                    // already processed -> skip
                    continue;
                }

                $investmentName = trim($row['Investment Name'] ?? '');
                $legalName = trim($row['Legal Name'] ?? '');
                $groupName = trim($row['Group'] ?? '');
                $fundName = trim($row['Fund'] ?? '');
                $accountName = 'Fuel Ventures - ' . $fundName;
                $quantity = floatval(str_replace(',', '', $row['Shares or units'] ?? 0));
                $price = floatval(preg_replace('/[^\d.]/', '', $row['Price'] ?? 0));
                $fees = floatval(preg_replace('/[^\d.]/', '', $row['Fees'] ?? 0));

                $dateString = trim($row['Investment date'] ?? '');
                $date = null;
                if ($dateString === '') {
                    $errors[] = "Missing Investment date for {$investmentName}";
                    continue;
                }
                try {
                    $date = Carbon::createFromFormat('d/m/Y', $dateString);
                } catch (\Exception $e1) {
                    try {
                        $date = Carbon::createFromFormat('Y-m-d', $dateString);
                    } catch (\Exception $e2) {
                        try {
                            $date = Carbon::parse($dateString);
                                } catch (\Exception $e3) {
                                    $errors[] = ['row' => $processed, 'error' => "Invalid date format for {$investmentName}: {$dateString}"];
                                    continue;
                                }
                    }
                }

                $symbol = $this->lookupCompanyId($legalName);
                $group = InvestmentGroup::firstOrCreate(['name' => $groupName, 'user_id' => $userId]);
                
                // If the import explicitly specifies an account_entity_id, prefer it
                $accountEntity = null;
                if ($import && isset($import->account_entity_id) && $import->account_entity_id) {
                    $accountEntity = AccountEntity::where('id', $import->account_entity_id)
                        ->where('user_id', $userId)
                        ->where('config_type', 'account')
                        ->first();
                }

                if (! $accountEntity) {
                    $accountEntity = AccountEntity::where('name', $accountName)
                        ->where('user_id', $userId)
                        ->where('config_type', 'account')
                        ->first();
                }
                
                // Get currency from account, default to GBP
                $currencyId = 1;
                if ($accountEntity && $accountEntity->config) {
                    $currencyId = $accountEntity->config->currency_id ?? 1;
                }
                
                // Create investment after we have the account and currency
                $investment = Investment::firstOrCreate([
                    'name' => $investmentName,
                    'user_id' => $userId,
                ], [
                    'symbol' => $symbol,
                    'investment_group_id' => $group->id,
                    'currency_id' => $currencyId,
                ]);

                if ($accountEntity) {
                    $account = Account::find($accountEntity->config_id);
                } else {
                    $accountGroupId = null;
                    $existingGroup = \App\Models\AccountGroup::where('user_id', $userId)->first();
                    if ($existingGroup) {
                        $accountGroupId = $existingGroup->id;
                    } else {
                        $newGroup = \App\Models\AccountGroup::create(['name' => 'Imported', 'user_id' => $userId]);
                        $accountGroupId = $newGroup->id;
                    }

                    $account = Account::create([
                        'opening_balance' => 0,
                        'account_group_id' => $accountGroupId,
                        'currency_id' => 1,
                    ]);

                    $accountEntity = AccountEntity::create([
                        'name' => $accountName,
                        'active' => true,
                        'config_type' => 'account',
                        'config_id' => $account->id,
                        'user_id' => $userId,
                    ]);
                }

                $tdi = TransactionDetailInvestment::create([
                    'account_id' => $accountEntity->id,
                    'investment_id' => $investment->id,
                    'price' => $price,
                    'quantity' => $quantity,
                    'commission' => $fees,
                ]);
                $buyTransaction = Transaction::create([
                    'user_id' => $userId,
                    'config_type' => 'investment',
                    'config_id' => $tdi->id,
                    'transaction_type_id' => 4,
                    'date' => $date,
                ]);

                $currencyId = $account->currency_id ?? 1;
                $commissionAmount = $tdi->commission ?? 0;
                $cashflow = -1 * ($price * $quantity) - $commissionAmount;
                $buyTransaction->currency_id = $currencyId;
                $buyTransaction->cashflow_value = $cashflow;
                $buyTransaction->saveQuietly();

                // record row-hash after successful creation to prevent duplicates on retry
                try {
                    DB::table('import_row_hashes')->insert([
                        'user_id' => $userId,
                        'row_hash' => $rowHash,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // ignore unique constraint race
                }

                $created++;
            }
        });

        return ['processed' => $processed, 'created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    protected function lookupCompanyId($legalName)
    {
        if (!$this->apiKey || !$legalName) return null;
        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
        ])->get('https://api.company-information.service.gov.uk/search/companies', [
            'q' => $legalName,
        ]);
        if ($response->ok() && isset($response['items'][0]['company_number'])) {
            return $response['items'][0]['company_number'];
        }
        return null;
    }

    /**
     * Process a WiseAlpha exported CSV file stored on disk.
     * This file contains a small metadata section followed by a transaction table
     * with headers similar to: Date,Action,Amount,Price,Av. Purchase Price,Principal,...
     */
    public function processWiseAlphaStoredFile(string $filePath, $userId, $import = null)
    {
        $errors = [];
        $processed = 0;
        $created = 0;

        $lines = file($filePath);
        if ($lines === false) {
            return ['processed' => 0, 'created' => 0, 'errors' => ['unable_to_open_file']];
        }

        // Parse metadata header (key,value) pairs until we find the transaction header row starting with 'Date'
        $meta = [];
        $headerRowIndex = null;
        foreach ($lines as $idx => $line) {
            $row = str_getcsv(trim($line));
            if (! $row || count($row) === 0) continue;
            $first = trim($row[0] ?? '');
            if (strtolower($first) === 'date') {
                $headerRowIndex = $idx;
                $tableHeader = array_map('trim', $row);
                break;
            }
            // metadata rows like "Company,AMC"
            if (isset($row[1])) {
                $metaKey = trim($row[0]);
                $metaVal = trim($row[1]);
                if ($metaKey !== '') $meta[$metaKey] = $metaVal;
            }
        }

        if ($headerRowIndex === null) {
            return ['processed' => 0, 'created' => 0, 'errors' => ['no_transaction_table_found']];
        }

        // Determine investment name and symbol from metadata
        $investmentName = $meta['Company'] ?? ($meta['Ticker'] ?? 'WiseAlpha Investment');
        $investmentSymbol = $meta['Ticker'] ?? null;

        // Get currency from the account being uploaded to
        $currencyId = 1; // default fallback
        if ($import && isset($import->account_entity_id) && $import->account_entity_id) {
            $accountEntity = AccountEntity::where('id', $import->account_entity_id)->where('user_id', $userId)->first();
            if ($accountEntity && $accountEntity->config) {
                $currencyId = $accountEntity->config->currency_id ?? 1;
            }
        }

        // Find or create investment group and investment record
        $group = \App\Models\InvestmentGroup::firstOrCreate(
            ['name' => 'WiseAlpha', 'user_id' => $userId]
        );
        $investment = Investment::firstOrCreate([
            'symbol' => $investmentSymbol,
            'user_id' => $userId,
        ], [
            'name' => $investmentName,
            'investment_group_id' => $group->id,
            'currency_id' => $currencyId,
        ]);

        // Walk the table rows after header
        for ($i = $headerRowIndex + 1; $i < count($lines); $i++) {
            $row = str_getcsv(trim($lines[$i]));
            if (! $row || count(array_filter($row, fn($c)=>trim((string)$c) !== '')) === 0) continue;
            $rowAssoc = [];
            foreach ($tableHeader as $hIdx => $hName) {
                $rowAssoc[$hName] = $row[$hIdx] ?? null;
            }

            $processed++;

            // Parse date: support dd-mm-yyyy and dd/mm/yyyy and ISO
            $dateString = trim($rowAssoc['Date'] ?? '');
            if ($dateString === '') {
                $errors[] = ['row' => $processed, 'error' => 'missing_date'];
                continue;
            }
            try {
                $date = Carbon::createFromFormat('d-m-Y', $dateString);
            } catch (\Exception $e1) {
                try {
                    $date = Carbon::createFromFormat('d/m/Y', $dateString);
                } catch (\Exception $e2) {
                    try {
                        $date = Carbon::parse($dateString);
                    } catch (\Exception $e3) {
                        $errors[] = ['row' => $processed, 'error' => 'invalid_date', 'value' => $dateString];
                        continue;
                    }
                }
            }

            $action = strtolower(trim($rowAssoc['Action'] ?? ''));
            $amount = floatval(str_replace(',', '', $rowAssoc['Amount'] ?? 0));
            $principal = floatval(str_replace(',', '', $rowAssoc['Principal'] ?? 0));
            $price = isset($rowAssoc['Price']) ? floatval(preg_replace('/[^0-9\.\-]/','', $rowAssoc['Price'])) / 100 : 0;
            $serviceFee = isset($rowAssoc['Service Fee']) ? floatval(preg_replace('/[^0-9\.\-]/','', $rowAssoc['Service Fee'])) : 0;
            $saleFee = isset($rowAssoc['Sale Fee']) ? floatval(preg_replace('/[^0-9\.\-]/','', $rowAssoc['Sale Fee'])) : 0;
            $accruedPaidFor = isset($rowAssoc['Accrued Paid For']) ? floatval(preg_replace('/[^0-9\.\-]/','', $rowAssoc['Accrued Paid For'])) : 0;
            $interestPayment = isset($rowAssoc['Interest Payment']) ? floatval(preg_replace('/[^0-9\.\-]/','', $rowAssoc['Interest Payment'])) : 0;

            // Decide how to map actions to transactions
            if (in_array($action, ['purchase', 'buy'])) {
                // quantity is Principal (actual bond quantity), price is Price, commission from Service Fee
                $quantity = $principal;
                $commission = $serviceFee;
                // account selection: prefer import->account_entity_id
                $accountEntity = null;
                if ($import && isset($import->account_entity_id) && $import->account_entity_id) {
                    $accountEntity = AccountEntity::where('id', $import->account_entity_id)->where('user_id', $userId)->where('config_type','account')->first();
                }
                if (! $accountEntity) {
                    // fallback to first account of user
                    $accountEntity = AccountEntity::where('user_id', $userId)->where('config_type','account')->first();
                }
                if (! $accountEntity) {
                    $errors[] = ['row' => $processed, 'error' => 'no_account_for_user'];
                    continue;
                }

                // Create transaction detail and transaction
                $tdi = TransactionDetailInvestment::create([
                    'account_id' => $accountEntity->id,
                    'investment_id' => $investment->id,
                    'price' => $price,
                    'quantity' => $quantity,
                    'commission' => $commission,
                ]);

                $buyTypeId = \App\Models\TransactionType::whereRaw('lower(name) = ?', ['buy'])->value('id') ?: 4;
                $txn = Transaction::create([
                    'user_id' => $userId,
                    'config_type' => 'investment',
                    'config_id' => $tdi->id,
                    'transaction_type_id' => $buyTypeId,
                    'date' => $date,
                ]);
                $currencyId = $accountEntity->config->currency_id ?? 1;
                $cashflow = -1 * ($price * $quantity) - ($commission ?? 0);
                $txn->currency_id = $currencyId;
                $txn->cashflow_value = $cashflow;
                $txn->saveQuietly();

                $created++;
                
                // Create negative dividend for accrued interest paid
                if ($accruedPaidFor > 0) {
                    $tdiAccrued = TransactionDetailInvestment::create([
                        'account_id' => $accountEntity->id,
                        'investment_id' => $investment->id,
                        'dividend' => $accruedPaidFor * -1,
                        'tax' => 0,
                    ]);
                    
                    $dividendTypeId = \App\Models\TransactionType::whereRaw('lower(name) = ?', ['dividend'])->value('id') ?: 6;
                    $txnAccrued = Transaction::create([
                        'user_id' => $userId,
                        'config_type' => 'investment',
                        'config_id' => $tdiAccrued->id,
                        'transaction_type_id' => $dividendTypeId,
                        'date' => $date,
                        'comment' => 'Accrued interest paid on purchase',
                    ]);
                    $txnAccrued->currency_id = $currencyId;
                    $txnAccrued->cashflow_value = -1 * $accruedPaidFor;
                    $txnAccrued->saveQuietly();
                    
                    $created++;
                }

            } elseif (in_array($action, ['payment'])) {
                // Interest/dividend payment
                $accountEntity = null;
                if ($import && isset($import->account_entity_id) && $import->account_entity_id) {
                    $accountEntity = AccountEntity::where('id', $import->account_entity_id)->where('user_id', $userId)->where('config_type','account')->first();
                }
                if (! $accountEntity) {
                    $accountEntity = AccountEntity::where('user_id', $userId)->where('config_type','account')->first();
                }
                if (! $accountEntity) {
                    $errors[] = ['row' => $processed, 'error' => 'no_account_for_user'];
                    continue;
                }
                
                $tdi = TransactionDetailInvestment::create([
                    'account_id' => $accountEntity->id,
                    'investment_id' => $investment->id,
                    'dividend' => $interestPayment,
                    'tax' => 0,
                    'commission' => $serviceFee,
                ]);
                
                $dividendTypeId = \App\Models\TransactionType::whereRaw('lower(name) = ?', ['dividend'])->value('id') ?: 6;
                $txn = Transaction::create([
                    'user_id' => $userId,
                    'config_type' => 'investment',
                    'config_id' => $tdi->id,
                    'transaction_type_id' => $dividendTypeId,
                    'date' => $date,
                ]);
                $currencyId = $accountEntity->config->currency_id ?? 1;
                $cashflow = $interestPayment - $serviceFee;
                $txn->currency_id = $currencyId;
                $txn->cashflow_value = $cashflow;
                $txn->saveQuietly();
                
                $created++;

            } elseif (in_array($action, ['exchange', 'sale', 'sell'])) {
                // treat as sell: quantity = Amount, price = Price, commission = Sale Fee or Service Fee
                $quantity = $amount;
                $commission = $saleFee ?: $serviceFee;

                $accountEntity = null;
                if ($import && isset($import->account_entity_id) && $import->account_entity_id) {
                    $accountEntity = AccountEntity::where('id', $import->account_entity_id)->where('user_id', $userId)->where('config_type','account')->first();
                }
                if (! $accountEntity) {
                    $accountEntity = AccountEntity::where('user_id', $userId)->where('config_type','account')->first();
                }
                if (! $accountEntity) {
                    $errors[] = ['row' => $processed, 'error' => 'no_account_for_user'];
                    continue;
                }

                $tdi = TransactionDetailInvestment::create([
                    'account_id' => $accountEntity->id,
                    'investment_id' => $investment->id,
                    'price' => $price,
                    'quantity' => $quantity,
                    'commission' => $commission,
                ]);

                $sellTypeId = \App\Models\TransactionType::whereRaw('lower(name) = ?', ['sell'])->value('id') ?: 5;
                $txn = Transaction::create([
                    'user_id' => $userId,
                    'config_type' => 'investment',
                    'config_id' => $tdi->id,
                    'transaction_type_id' => $sellTypeId,
                    'date' => $date,
                ]);
                $currencyId = $accountEntity->config->currency_id ?? 1;
                $cashflow = ($price * $quantity) - ($commission ?? 0);
                $txn->currency_id = $currencyId;
                $txn->cashflow_value = $cashflow;
                $txn->saveQuietly();

                $created++;
            } else {
                // For other actions (Payment, Interest etc) skip for now — collect as info
                // Could be implemented later to create dividend/interest entries
                continue;
            }
        }

        // After all transactions are created, trigger a single recalculation for the account
        if ($created > 0 && $import && $import->account_entity_id) {
            $accountEntity = AccountEntity::find($import->account_entity_id);
            if ($accountEntity) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    // Dispatch jobs to recalculate investment summaries for this account
                    dispatch(new \App\Jobs\CalculateAccountMonthlySummary($user, 'investment_value-fact', $accountEntity));
                    dispatch(new \App\Jobs\CalculateAccountMonthlySummary($user, 'investment_value-forecast', $accountEntity));
                }
            }
        }

        return ['processed' => $processed, 'created' => $created, 'updated' => 0, 'errors' => $errors];
    }
}
