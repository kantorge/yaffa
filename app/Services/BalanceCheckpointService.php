<?php

namespace App\Services;

use App\Models\AccountBalanceCheckpoint;
use App\Models\AccountEntity;
use App\Models\Transaction;
use Carbon\Carbon;

class BalanceCheckpointService
{
    /**
     * Check if balance checkpoint validation is enabled.
     */
    public function isEnabled(): bool
    {
        return config('yaffa.balance_checkpoint_enabled', true);
    }

    /**
     * Validate if a transaction would violate any balance checkpoints.
     *
     * @param Transaction $transaction
     * @param bool $isUpdate Whether this is an update operation
     * @return array ['valid' => bool, 'message' => string|null, 'checkpoint' => AccountBalanceCheckpoint|null]
     */
    public function validateTransaction(Transaction $transaction, bool $isUpdate = false): array
    {
        if (!$this->isEnabled()) {
            \Log::info('Balance checkpoint validation: disabled');
            return ['valid' => true, 'message' => null, 'checkpoint' => null];
        }

        // Skip validation for scheduled or budget transactions
        if ($transaction->schedule || $transaction->budget) {
            \Log::info('Balance checkpoint validation: skipping scheduled/budget transaction', ['transaction_id' => $transaction->id]);
            return ['valid' => true, 'message' => null, 'checkpoint' => null];
        }

        // Ensure transaction date is present and a Carbon instance
        if (! $transaction->date) {
            \Log::info('Balance checkpoint validation: transaction has no date, skipping', ['transaction_id' => $transaction->id]);
            return ['valid' => true, 'message' => null, 'checkpoint' => null];
        }

        $transactionDate = $transaction->date instanceof Carbon ? $transaction->date : Carbon::parse($transaction->date);

        // Get affected accounts
        $accountIds = $this->getAffectedAccounts($transaction);

        \Log::info('Balance checkpoint validation', [
            'transaction_id' => $transaction->id,
            'date' => $transaction->date,
            'is_update' => $isUpdate,
            'affected_accounts' => $accountIds,
        ]);

        if (empty($accountIds)) {
            \Log::info('No affected accounts found');
            return ['valid' => true, 'message' => null, 'checkpoint' => null];
        }

        // Check each affected account
        foreach ($accountIds as $accountId) {
            $result = $this->validateAccountBalance($accountId, $transactionDate, $transaction, $isUpdate);

            \Log::info('Account validation result', [
                'account_id' => $accountId,
                'valid' => $result['valid'],
                'message' => $result['message'] ?? 'none',
            ]);

            if (!$result['valid']) {
                return $result;
            }
        }

        return ['valid' => true, 'message' => null, 'checkpoint' => null];
    }

    /**
     * Validate if a transaction deletion would violate any balance checkpoints.
     */
    public function validateDeletion(Transaction $transaction): array
    {
        if (!$this->isEnabled()) {
            return ['valid' => true, 'message' => null, 'checkpoint' => null];
        }

        if ($transaction->schedule || $transaction->budget) {
            \Log::info('Balance checkpoint deletion validation: skipping scheduled/budget transaction', ['transaction_id' => $transaction->id]);
            return ['valid' => true, 'message' => null, 'checkpoint' => null];
        }

        if (! $transaction->date) {
            \Log::info('Balance checkpoint deletion validation: transaction has no date, skipping', ['transaction_id' => $transaction->id]);
            return ['valid' => true, 'message' => null, 'checkpoint' => null];
        }

        $transactionDate = $transaction->date instanceof Carbon ? $transaction->date : Carbon::parse($transaction->date);

        // Get affected accounts
        $accountIds = $this->getAffectedAccounts($transaction);

        if (empty($accountIds)) {
            return ['valid' => true, 'message' => null, 'checkpoint' => null];
        }

        // For deletion, we check if removing this transaction would break checkpoints
        foreach ($accountIds as $accountId) {
            // Get checkpoints on or before the transaction date
            $checkpoint = AccountBalanceCheckpoint::active()
                ->forAccount($accountId)
                ->where('checkpoint_date', '<=', $transactionDate)
                ->orderBy('checkpoint_date', 'desc')
                ->first();

            if (!$checkpoint) {
                continue;
            }

            // Calculate balance WITHOUT this transaction (simulating deletion)
            $balanceWithoutTransaction = $this->calculateBalanceAtDate($accountId, $checkpoint->checkpoint_date, $transaction->id);

            // If removing the transaction would break the checkpoint, reject
            if (abs($balanceWithoutTransaction - $checkpoint->balance) >= 0.01) {
                return [
                    'valid' => false,
                    'message' => "Deleting this transaction would violate the balance checkpoint on {$checkpoint->checkpoint_date->format('Y-m-d')}. Expected balance: {$checkpoint->balance}, would be: {$balanceWithoutTransaction}",
                    'checkpoint' => $checkpoint
                ];
            }
        }

        return ['valid' => true, 'message' => null, 'checkpoint' => null];
    }

    /**
     * Get affected account IDs from a transaction.
     */
    protected function getAffectedAccounts(Transaction $transaction): array
    {
        $accountIds = [];

        // Load config if not already loaded
        if (!$transaction->relationLoaded('config')) {
            $transaction->load('config');
        }

        if ($transaction->isStandard()) {
            $config = $transaction->config;

            if ($config && $config->account_from_id) {
                $accountFrom = AccountEntity::find($config->account_from_id);
                if ($accountFrom && $accountFrom->isAccount()) {
                    $accountIds[] = $config->account_from_id;
                }
            }

            if ($config && $config->account_to_id) {
                $accountTo = AccountEntity::find($config->account_to_id);
                if ($accountTo && $accountTo->isAccount()) {
                    $accountIds[] = $config->account_to_id;
                }
            }
        } elseif ($transaction->isInvestment()) {
            $config = $transaction->config;
            if ($config && $config->account_id) {
                $accountIds[] = $config->account_id;
            }
        }

        return array_unique($accountIds);
    }

    /**
     * Validate account balance against checkpoints.
     */
    protected function validateAccountBalance(int $accountId, Carbon $transactionDate, Transaction $transaction, bool $isUpdate = false): array
    {
        // Get the most recent active checkpoint on or before the transaction date
        $checkpoint = AccountBalanceCheckpoint::active()
            ->forAccount($accountId)
            ->where('checkpoint_date', '<=', $transactionDate)
            ->orderBy('checkpoint_date', 'desc')
            ->first();

        if (!$checkpoint) {
            \Log::info('No checkpoint found for account', ['account_id' => $accountId]);
            return ['valid' => true, 'message' => null, 'checkpoint' => null];
        }

        \Log::info('Checkpoint found', [
            'checkpoint_id' => $checkpoint->id,
            'checkpoint_date' => $checkpoint->checkpoint_date->format('Y-m-d'),
            'checkpoint_balance' => $checkpoint->balance,
        ]);

        // Calculate the current balance at the checkpoint date (excluding this transaction if it's an update)
        $currentBalance = $this->calculateBalanceAtDate($accountId, $checkpoint->checkpoint_date, $isUpdate ? $transaction->id : null);

        \Log::info('Current balance calculated', [
            'current_balance' => $currentBalance,
            'checkpoint_balance' => $checkpoint->balance,
            'difference' => abs($currentBalance - $checkpoint->balance),
        ]);

        // Determine whether the checkpoint currently matches the computed balance
        $matched = abs($currentBalance - $checkpoint->balance) < 0.01;

        if (! $matched) {
            // If the checkpoint does not match the computed balance, allow changes
            \Log::warning('Checkpoint mismatch: allowing modifications to try to resolve', [
                'checkpoint_date' => $checkpoint->checkpoint_date->format('Y-m-d'),
                'checkpoint_balance' => $checkpoint->balance,
                'current_balance' => $currentBalance,
                'difference' => abs($currentBalance - $checkpoint->balance),
            ]);

            return ['valid' => true, 'message' => null, 'checkpoint' => $checkpoint];
        }

        // Check what the balance would be WITH this transaction (only matters when checkpoint is matched)
        $newBalance = $this->calculateBalanceWithTransaction($accountId, $checkpoint->checkpoint_date, $transaction, $isUpdate);

        \Log::info('New balance with transaction', [
            'new_balance' => $newBalance,
            'checkpoint_balance' => $checkpoint->balance,
            'difference' => abs($newBalance - $checkpoint->balance),
        ]);

        if (abs($newBalance - $checkpoint->balance) >= 0.01) {
            \Log::warning('Transaction would violate checkpoint');
            return [
                'valid' => false,
                'message' => "This transaction would violate the balance checkpoint on {$checkpoint->checkpoint_date->format('Y-m-d')}. Expected balance: {$checkpoint->balance}, would be: {$newBalance}",
                'checkpoint' => $checkpoint
            ];
        }

        \Log::info('Transaction passes validation');
        return ['valid' => true, 'message' => null, 'checkpoint' => $checkpoint];
    }

    /**
     * Calculate account balance at a specific date.
     */
    public function calculateBalanceAtDate(int $accountId, Carbon $date, ?int $excludeTransactionId = null): float
    {
        $account = AccountEntity::with('config')->findOrFail($accountId);

        if (!$account->isAccount()) {
            return 0;
        }

        // Start with opening balance
        $balance = $account->config->opening_balance ?? 0;

        // Add all transactions up to and including the checkpoint date
        $transactions = Transaction::where('date', '<=', $date)
            ->where('schedule', false)
            ->where('budget', false)
            ->when($excludeTransactionId, fn ($query) => $query->where('id', '!=', $excludeTransactionId))
            ->with('config')
            ->get();

        foreach ($transactions as $transaction) {
            if ($transaction->isStandard()) {
                $config = $transaction->config;

                // Money coming in (to this account)
                if ($config->account_to_id === $accountId) {
                    $balance += $config->amount_to ?? 0;
                }

                // Money going out (from this account)
                if ($config->account_from_id === $accountId) {
                    $balance -= $config->amount_from ?? 0;
                }
            }
        }

        return $balance;
    }

    /**
     * Calculate what the account balance would be at a specific date WITH a given transaction.
     * This is used for validation before the transaction is saved to the database.
     */
    protected function calculateBalanceWithTransaction(int $accountId, Carbon $date, Transaction $transaction, bool $isUpdate = false): float
    {
        // Start with the current balance (excluding the transaction if it's an update)
        $balance = $this->calculateBalanceAtDate($accountId, $date, $isUpdate ? $transaction->id : null);

        // Now add the effect of THIS transaction if it's on or before the checkpoint date
        if ($transaction->date && $transaction->date <= $date && !$transaction->schedule && !$transaction->budget) {
            if ($transaction->isStandard()) {
                $config = $transaction->config;

                if ($config) {
                    // Money coming in (to this account)
                    if ($config->account_to_id === $accountId) {
                        $balance += $config->amount_to ?? 0;
                    }

                    // Money going out (from this account)
                    if ($config->account_from_id === $accountId) {
                        $balance -= $config->amount_from ?? 0;
                    }
                }
            }
        }

        return $balance;
    }

    /**
     * Create a new balance checkpoint.
     */
    public function createCheckpoint(int $userId, int $accountEntityId, Carbon $date, float $balance, ?string $note = null): AccountBalanceCheckpoint
    {
        // Deactivate any existing checkpoint for this account on this date
        AccountBalanceCheckpoint::where('account_entity_id', $accountEntityId)
            ->where('checkpoint_date', $date)
            ->update(['active' => false]);

        return AccountBalanceCheckpoint::create([
            'user_id' => $userId,
            'account_entity_id' => $accountEntityId,
            'checkpoint_date' => $date,
            'balance' => $balance,
            'note' => $note,
            'active' => true,
        ]);
    }

    /**
     * Reconcile a transaction.
     */
    public function reconcileTransaction(Transaction $transaction, int $userId): void
    {
        $transaction->update([
            'reconciled' => true,
            'reconciled_at' => now(),
            'reconciled_by' => $userId,
        ]);
    }

    /**
     * Unreconcile a transaction.
     */
    public function unreconcileTransaction(Transaction $transaction): void
    {
        $transaction->update([
            'reconciled' => false,
            'reconciled_at' => null,
            'reconciled_by' => null,
        ]);
    }

    /**
     * Check if a transaction can be edited or deleted.
     */
    public function canModifyTransaction(Transaction $transaction, string $action = 'modify'): array
    {
        if ($transaction->reconciled) {
            $message = match($action) {
                'delete' => 'Cannot remove reconciled transaction',
                'update' => 'Cannot modify reconciled transaction',
                default => 'This transaction is reconciled and cannot be modified'
            };
            
            return [
                'can_modify' => false,
                'reason' => $message
            ];
        }

        return ['can_modify' => true, 'reason' => null];
    }
}
