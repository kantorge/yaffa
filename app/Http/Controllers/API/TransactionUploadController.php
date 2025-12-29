<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Services\TransactionUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TransactionUploadController extends Controller
{
    /**
     * Get user's accounts for selection in UI.
     */
    public function getAccounts(Request $request)
    {
        $service = new TransactionUploadService(Auth::user());
        $accounts = $service->getUserAccounts();

        return response()->json([
            'success' => true,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Handle MoneyHub CSV upload and return mapped preview.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs('transaction-uploads', Auth::id() . '_' . time() . '_' . $file->getClientOriginalName(), 'local');
        $fullPath = Storage::disk('local')->path($path);

        $service = new TransactionUploadService(Auth::user());
        $preview = $service->parseMoneyHubCsv($fullPath);

        Storage::disk('local')->delete($path);

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    /**
     * Import MoneyHub transactions with account mapping.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'account_id' => 'required|exists:account_entities,id',
        ]);

        $user = Auth::user();
        $service = new TransactionUploadService($user);

        // Verify account belongs to user
        $account = $user->accounts()->findOrFail($request->account_id);

        $file = $request->file('file');
        $path = $file->storeAs('transaction-uploads', Auth::id() . '_' . time() . '_' . $file->getClientOriginalName(), 'local');
        $fullPath = Storage::disk('local')->path($path);

        $rows = $service->parseMoneyHubCsv($fullPath);
        Storage::disk('local')->delete($path);

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                try {
                    // Skip if no amount
                    if (empty($row['amount']) || $row['amount'] == 0) {
                        $skipped++;
                        continue;
                    }

                    // Parse date
                    $date = Carbon::parse($row['date']);

                    // Check for import rules
                    Log::info("Checking rules for description: '{$row['description']}' on account {$account->id}");
                    $rule = $service->findMatchingRule($row['description'], $account->id);
                    
                    if ($rule) {
                        Log::info("Found matching rule: ID {$rule->id}, Action: {$rule->action}");
                        // Handle rule action
                        if ($rule->action === 'skip') {
                            $skipped++;
                            Log::info("Skipped transaction due to rule: {$row['description']}");
                            continue;
                        }
                        
                        if ($rule->action === 'convert_to_transfer' && $rule->transfer_account_id) {
                            // Convert to transfer between accounts
                            $amount = abs((float) $row['amount']);
                            
                            if ((float) $row['amount'] > 0) {
                                // Money coming in: from transfer account to this account
                                $accountFromId = $rule->transfer_account_id;
                                $accountToId = $account->id;
                            } else {
                                // Money going out: from this account to transfer account
                                $accountFromId = $account->id;
                                $accountToId = $rule->transfer_account_id;
                            }
                            
                            $transactionTypeId = $rule->transaction_type_id ?? 3; // Default to transfer type
                            $categoryId = null; // Transfers don't have categories
                            $payeeId = null; // Transfers don't have payees
                            
                            Log::info("Applied transfer rule: {$row['description']} -> Transfer between accounts");
                        } elseif ($rule->action === 'merge_payee' && $rule->merge_payee_id) {
                            // Merge to specified payee - use the payee's default category
                            $amount = (float) $row['amount'];
                            $payeeId = $rule->merge_payee_id;
                            
                            // Get the merge payee's default category instead of creating from CSV
                            $mergePayee = \App\Models\AccountEntity::with('config')->find($payeeId);
                            $categoryId = $mergePayee?->config?->category_id;
                            
                            if ($amount > 0) {
                                // Money coming in: from payee to account (deposit)
                                $accountFromId = $payeeId;
                                $accountToId = $account->id;
                                $transactionTypeId = 2; // deposit
                            } else {
                                // Money going out: from account to payee (withdrawal)
                                $accountFromId = $account->id;
                                $accountToId = $payeeId;
                                $amount = abs($amount);
                                $transactionTypeId = 1; // withdrawal
                            }
                            
                            Log::info("Applied merge payee rule: {$row['description']} -> Payee ID {$payeeId}" . 
                                     ($categoryId ? " (Category ID: {$categoryId})" : " (no default category)"));
                        } else {
                            // Default handling if rule doesn't apply properly
                            goto default_handling;
                        }
                    } else {
                        default_handling:
                        // Match or create payee
                        $categoryId = $service->matchOrCreateCategory($row['category'], $row['category_group']);
                        $payeeId = $service->matchOrCreatePayee($row['description'], $categoryId);

                        // Determine transaction direction and accounts
                        $amount = (float) $row['amount'];
                        if ($amount > 0) {
                            // Money coming in: from payee to account (deposit)
                            $accountFromId = $payeeId;
                            $accountToId = $account->id;
                            $transactionTypeId = 2; // deposit
                        } else {
                            // Money going out: from account to payee (withdrawal)
                            $accountFromId = $account->id;
                            $accountToId = $payeeId;
                            $amount = abs($amount);
                            $transactionTypeId = 1; // withdrawal
                        }
                    }

                    // Check for duplicate - simpler approach using join
                    $existingTransaction = DB::table('transactions')
                        ->join('transaction_details_standard', function($join) {
                            $join->on('transactions.config_id', '=', 'transaction_details_standard.id')
                                 ->where('transactions.config_type', '=', 'standard');
                        })
                        ->where('transactions.user_id', $user->id)
                        ->where('transactions.date', $date)
                        ->where('transaction_details_standard.account_from_id', $accountFromId)
                        ->where('transaction_details_standard.account_to_id', $accountToId)
                        ->where('transaction_details_standard.amount_from', $amount)
                        ->where('transactions.created_at', '>=', now()->subDays(60))
                        ->exists();

                    if ($existingTransaction) {
                        $skipped++;
                        continue;
                    }

                    // Create transaction config
                    $config = TransactionDetailStandard::create([
                        'account_from_id' => $accountFromId,
                        'account_to_id' => $accountToId,
                        'amount_from' => $amount,
                        'amount_to' => $amount,
                    ]);

                    // Create transaction
                    $transaction = Transaction::create([
                        'date' => $date,
                        'comment' => !empty($row['project']) ? "Project: {$row['project']}" : null,
                        'reconciled' => false,
                        'config_type' => 'standard',
                        'config_id' => $config->id,
                        'transaction_type_id' => $transactionTypeId,
                        'user_id' => $user->id,
                    ]);

                    // Create transaction item for withdrawal/deposit transactions (not transfers)
                    // Transfer transactions (type 3) should NOT have transaction items
                    if ($transactionTypeId != 3 && $categoryId) {
                        // Get the payee entity to access default category
                        $payeeEntity = \App\Models\AccountEntity::find($payeeId ?? null);
                        $defaultCategoryId = null;
                        
                        if ($payeeEntity && $payeeEntity->config_type === 'payee' && $payeeEntity->config) {
                            $defaultCategoryId = $payeeEntity->config->category_id ?? $categoryId;
                        } else {
                            $defaultCategoryId = $categoryId;
                        }
                        
                        if ($defaultCategoryId) {
                            \App\Models\TransactionItem::create([
                                'transaction_id' => $transaction->id,
                                'category_id' => $defaultCategoryId,
                                'amount' => $amount,
                                'comment' => null,
                            ]);
                        }
                    }

                    $created++;

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                    Log::error("Transaction import error for row " . ($index + 1), [
                        'error' => $e->getMessage(),
                        'row' => $row
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
                'message' => "Successfully imported {$created} transactions" . 
                             ($skipped > 0 ? " ({$skipped} skipped as duplicates or invalid)" : ""),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Transaction import failed", ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

