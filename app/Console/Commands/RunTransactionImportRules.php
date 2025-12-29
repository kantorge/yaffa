<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TransactionImportRule;
use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

class RunTransactionImportRules extends Command
{
    protected $signature = 'import-rules:run {--user= : User ID to run rules for (default: all users)} {--days= : Limit transactions to the last N days (default: all)} {--dry-run : Do not save changes, just report}';

    protected $description = 'Run transaction import rules across transactions and auto-apply matching corrections';

    public function handle()
    {
        $userId = $this->option('user');
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');

        $usersQuery = \App\Models\User::query();
        if ($userId) {
            $usersQuery->where('id', $userId);
        }

        $users = $usersQuery->get();
        if ($users->isEmpty()) {
            $this->info('No users found for processing.');
            return 0;
        }

        foreach ($users as $user) {
            $this->info("Processing user {$user->id} ({$user->email})");

            $rules = TransactionImportRule::where('user_id', $user->id)
                ->where('active', true)
                ->orderBy('priority')
                ->get();

            if ($rules->isEmpty()) {
                $this->info('  No active rules for this user.');
                continue;
            }

            $txQuery = $user->transactions()
                ->with(['transactionItems', 'config', 'transactionType'])
                ->where('config_type', 'standard')
                ->where('schedule', false)
                ->where('budget', false)
                ->orderBy('date', 'desc');

            if ($days) {
                $txQuery->where('date', '>=', now()->subDays((int)$days));
            }

            $processed = 0;
            $corrected = 0;
            $errors = 0;

            foreach ($txQuery->cursor() as $transaction) {
                $processed++;

                // Determine description for matching
                $description = null;
                $config = $transaction->config;
                if ($config) {
                    $accountFrom = $config->accountFrom ?? null;
                    $accountTo = $config->accountTo ?? null;
                    if ($accountFrom && $accountFrom->config_type === 'payee') {
                        $description = $accountFrom->name;
                    } elseif ($accountTo && $accountTo->config_type === 'payee') {
                        $description = $accountTo->name;
                    }
                }
                if (empty($description)) {
                    $description = $transaction->comment;
                }
                if (empty($description) && $transaction->transactionItems->isNotEmpty()) {
                    $description = $transaction->transactionItems->first()->comment;
                }

                if (empty($description)) {
                    continue;
                }

                // Find first matching rule
                $matchedRule = null;
                foreach ($rules as $rule) {
                    // check account applicability
                    if ($rule->account_id) {
                        $cfg = $transaction->config;
                        $accountFromId = $cfg->account_from_id ?? null;
                        $accountToId = $cfg->account_to_id ?? null;
                        if ($accountFromId != $rule->account_id && $accountToId != $rule->account_id) {
                            continue;
                        }
                    }

                    if ($rule->matches($description)) {
                        $matchedRule = $rule;
                        break;
                    }
                }

                if (! $matchedRule) {
                    continue;
                }

                // Apply rule
                try {
                    if ($dryRun) {
                        $this->line("  [DRY] Would apply rule {$matchedRule->id} to transaction {$transaction->id}");
                        $corrected++;
                        continue;
                    }

                    DB::transaction(function() use ($transaction, $matchedRule, &$corrected) {
                        switch ($matchedRule->action) {
                            case 'convert_to_transfer':
                                if (! $matchedRule->transfer_account_id) {
                                    throw new \Exception('Transfer account not specified');
                                }
                                $cfg = $transaction->config;
                                
                                // Determine which account is the payee that needs replacing
                                $accountFromIsPayee = $cfg->accountFrom && $cfg->accountFrom->config_type === 'payee';
                                $accountToIsPayee = $cfg->accountTo && $cfg->accountTo->config_type === 'payee';
                                
                                if ($accountFromIsPayee) {
                                    // Withdrawal: payee is FROM, real account is TO
                                    // Replace the payee with the transfer account
                                    $cfg->account_from_id = $matchedRule->transfer_account_id;
                                } elseif ($accountToIsPayee) {
                                    // Deposit: real account is FROM, payee is TO
                                    // Replace the payee with the transfer account
                                    $cfg->account_to_id = $matchedRule->transfer_account_id;
                                } elseif ($cfg->account_from_id && !$cfg->account_to_id) {
                                    // Only FROM set, set TO to transfer account
                                    $cfg->account_to_id = $matchedRule->transfer_account_id;
                                } elseif ($cfg->account_to_id && !$cfg->account_from_id) {
                                    // Only TO set, set FROM to transfer account
                                    $cfg->account_from_id = $matchedRule->transfer_account_id;
                                }
                                
                                $transferType = TransactionType::where('name', 'transfer')->first();
                                if ($transferType) {
                                    $transaction->transaction_type_id = $transferType->id;
                                    $transaction->save();
                                }
                                $cfg->save();
                                break;
                            case 'skip':
                                // Only mark if not already marked
                                if (!str_contains($transaction->comment ?? '', '[MARKED FOR SKIP]')) {
                                    $transaction->comment = '[MARKED FOR SKIP] ' . ($transaction->comment ?? '');
                                    $transaction->save();
                                }
                                break;
                            case 'modify':
                                if ($matchedRule->transaction_type_id) {
                                    $transaction->transaction_type_id = $matchedRule->transaction_type_id;
                                    $transaction->save();
                                }
                                break;
                            case 'merge_payee':
                                if (! $matchedRule->merge_payee_id) {
                                    throw new \Exception('Merge payee not specified');
                                }
                                
                                $cfg = $transaction->config;
                                $originalPayeeName = null;
                                $currentPayeeId = null;
                                
                                if ($cfg->accountFrom && $cfg->accountFrom->config_type === 'payee') {
                                    $currentPayeeId = $cfg->account_from_id;
                                    $originalPayeeName = $cfg->accountFrom->name;
                                } elseif ($cfg->accountTo && $cfg->accountTo->config_type === 'payee') {
                                    $currentPayeeId = $cfg->account_to_id;
                                    $originalPayeeName = $cfg->accountTo->name;
                                }

                                // Skip if payee already matches the target
                                if ($currentPayeeId == $matchedRule->merge_payee_id) {
                                    break;
                                }

                                // Apply the merge
                                if ($cfg->accountFrom && $cfg->accountFrom->config_type === 'payee') {
                                    $cfg->account_from_id = $matchedRule->merge_payee_id;
                                } elseif ($cfg->accountTo && $cfg->accountTo->config_type === 'payee') {
                                    $cfg->account_to_id = $matchedRule->merge_payee_id;
                                }
                                
                                // Append original payee name to comment if requested
                                if ($matchedRule->append_original_to_comment && $originalPayeeName) {
                                    $currentComment = $transaction->comment ?? '';
                                    $appendText = "Original: {$originalPayeeName}";
                                    
                                    if (empty($currentComment)) {
                                        $transaction->comment = $appendText;
                                    } else {
                                        $transaction->comment = $currentComment . ' | ' . $appendText;
                                    }
                                    $transaction->save();
                                }
                                
                                $cfg->save();
                                break;
                        }
                        $corrected++;
                    });
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("Error applying rule to transaction {$transaction->id}: {$e->getMessage()}");
                }
            }

            $this->info("User {$user->id}: processed={$processed}, corrected={$corrected}, errors={$errors}");
        }

        return 0;
    }
}
