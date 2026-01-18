<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\TransactionUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessMoneyhubUpload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $importJobId;
    public ?int $accountId;

    public int $timeout = 1800;

    private array $affectedAccounts = [];

    public function __construct(int $importJobId, ?int $accountId = null)
    {
        $this->importJobId = $importJobId;
        $this->accountId = $accountId;
    }

    public function handle()
    {
        $import = ImportJob::find($this->importJobId);
        if (! $import) {
            return;
        }

        $import->update(['status' => 'started', 'started_at' => now()]);

        $filePath = storage_path('app/' . $import->file_path);
        $user = \App\Models\User::find($import->user_id);
        if (! $user) {
            $import->update(['status' => 'failed', 'errors' => ['user_not_found']]);
            return;
        }

        $service = new TransactionUploadService($user);

        $created = 0;
        $skipped = 0;
        $errors = [];

        try {
            $rows = $service->parseMoneyHubCsv($filePath);

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                try {
                    if (empty($row['amount']) || $row['amount'] === 0) {
                        $skipped++;
                        continue;
                    }

                    $date = \Carbon\Carbon::parse($row['date']);

                    // Determine account mapping
                    if ($this->accountId) {
                        $account = $user->accounts()->find($this->accountId);
                    } else {
                        // fallback: try to match account by alias
                        $accountMatch = $service->matchAccountByAlias($row['account'] ?? null);
                        $account = $user->accounts()->find($accountMatch);
                    }

                    if (! $account) {
                        $errors[] = "Row " . ($index + 1) . ": account not found";
                        continue;
                    }

                    // Check for rules
                    $rule = $service->findMatchingRule($row['description'] ?? '', $account->id);
                    if ($rule) {
                        if ($rule->action === 'skip') {
                            $skipped++;
                            continue;
                        }
                        if ($rule->action === 'convert_to_transfer' && $rule->transfer_account_id) {
                            $amount = abs((float)$row['amount']);
                            if ((float)$row['amount'] > 0) {
                                $accountFromId = $rule->transfer_account_id;
                                $accountToId = $account->id;
                            } else {
                                $accountFromId = $account->id;
                                $accountToId = $rule->transfer_account_id;
                            }
                            $transactionTypeId = $rule->transaction_type_id ?: null;
                            $categoryId = null;
                            $payeeId = null;
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

                            Log::info("Applied merge payee rule in job: {$row['description']} -> Payee ID {$payeeId}" .
                                     ($categoryId ? " (Category ID: {$categoryId})" : " (no default category)"));
                        } else {
                            // fallback to default handling
                            $rule = null;
                        }
                    }

                    if (! $rule) {
                        $categoryId = $service->matchOrCreateCategory($row['category'] ?? null, $row['category_group'] ?? null);
                        $payeeId = $service->matchOrCreatePayee($row['description'] ?? null, $categoryId);

                        $amount = (float)$row['amount'];
                        if ($amount > 0) {
                            $accountFromId = $payeeId;
                            $accountToId = $account->id;
                            $transactionTypeId = 2; // deposit
                        } else {
                            $accountFromId = $account->id;
                            $accountToId = $payeeId;
                            $amount = abs($amount);
                            $transactionTypeId = 1; // withdrawal
                        }
                    }

                    // Duplicate check similar to API controller
                    $existing = DB::table('transactions')
                        ->join('transaction_details_standard', function ($join) {
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

                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    $config = \App\Models\TransactionDetailStandard::create([
                        'account_from_id' => $accountFromId,
                        'account_to_id' => $accountToId,
                        'amount_from' => $amount,
                        'amount_to' => $amount,
                    ]);

                    $transaction = new \App\Models\Transaction([
                        'date' => $date,
                        'comment' => !empty($row['project']) ? "Project: {$row['project']}" : null,
                        'reconciled' => false,
                        'config_type' => 'standard',
                        'config_id' => $config->id,
                        'transaction_type_id' => $transactionTypeId,
                        'user_id' => $user->id,
                        'import_job_id' => $this->importJobId,
                    ]);
                    $transaction->saveQuietly();

                    // Track affected accounts for later recalculation
                    if ($accountFromId && !in_array($accountFromId, $this->affectedAccounts)) {
                        $this->affectedAccounts[] = $accountFromId;
                    }
                    if ($accountToId && !in_array($accountToId, $this->affectedAccounts)) {
                        $this->affectedAccounts[] = $accountToId;
                    }

                    // Create transaction item with the category and amount
                    // For standard transactions, we need to use the payee's default category
                    $itemCategoryId = null;

                    // Determine the payee to get its default category
                    if ($transactionTypeId === 1) {
                        // Withdrawal: payee is account_to
                        $payeeEntity = \App\Models\AccountEntity::find($accountToId);
                    } elseif ($transactionTypeId === 2) {
                        // Deposit: payee is account_from
                        $payeeEntity = \App\Models\AccountEntity::find($accountFromId);
                    }

                    // Get the payee's default category if it exists
                    if (isset($payeeEntity) && $payeeEntity->config_type === 'payee') {
                        $payeeEntity->load('config');
                        if ($payeeEntity->config && $payeeEntity->config->category_id) {
                            $itemCategoryId = $payeeEntity->config->category_id;
                        }
                    }

                    // Fall back to the matched/created category if no payee default
                    if (!$itemCategoryId && $categoryId) {
                        $itemCategoryId = $categoryId;
                    }

                    // Create the transaction item if we have a category
                    if ($itemCategoryId) {
                        \App\Models\TransactionItem::create([
                            'transaction_id' => $transaction->id,
                            'amount' => $amount,
                            'category_id' => $itemCategoryId,
                            'comment' => null,
                        ]);
                    }

                    $created++;

                    // update progress occasionally
                    if (($created + $skipped) % 50 === 0) {
                        $import->update(['processed_rows' => ($created + $skipped)]);
                    }

                } catch (Throwable $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                    Log::error('Moneyhub import row error', ['error' => $e->getMessage(), 'row' => $row]);
                }
            }

            DB::commit();

            // After all transactions are imported, trigger recalculation for affected accounts
            foreach ($this->affectedAccounts as $accountId) {
                $accountEntity = \App\Models\AccountEntity::find($accountId);
                if ($accountEntity && $accountEntity->config_type === 'account') {
                    dispatch(new CalculateAccountMonthlySummary($user, 'account_balance-fact', $accountEntity));
                }
            }

            $import->update(['status' => 'finished', 'finished_at' => now(), 'errors' => $errors, 'processed_rows' => ($created + $skipped)]);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Moneyhub import failed: ' . $e->getMessage());
            $import->update(['status' => 'failed', 'errors' => [$e->getMessage()]]);
            throw $e;
        }
    }
}
