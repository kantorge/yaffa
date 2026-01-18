<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionType;
use App\Services\TransactionService;
use App\Services\BalanceCheckpointService;
use Illuminate\Validation\ValidationException;

class TransactionObserver
{
    /**
     * Flag to prevent infinite loops when creating transactions from Interest ReInvest
     */
    private static bool $processingReInvest = false;

    /**
     * Handle the Transaction "creating" event (before save).
     */
    public function creating(Transaction $transaction): bool
    {
        \Log::info('TransactionObserver creating fired', [
            'id' => $transaction->id,
            'date' => $transaction->date,
            'has_config' => $transaction->config !== null,
            'config_type' => $transaction->config ? get_class($transaction->config) : null,
        ]);

        // Check reconciliation status - transactions cannot be created if they affect reconciled balances
        $balanceService = new BalanceCheckpointService();
        $modifyCheck = $balanceService->canModifyTransaction($transaction);

        if (!$modifyCheck['can_modify']) {
            \Log::warning('Transaction creation blocked: reconciled');
            throw ValidationException::withMessages([
                'transaction' => [$modifyCheck['reason']]
            ]);
        }

        // Validate against balance checkpoints
        $validationResult = $balanceService->validateTransaction($transaction, false);

        \Log::info('Balance checkpoint validation result', [
            'valid' => $validationResult['valid'],
            'message' => $validationResult['message'] ?? 'none',
        ]);

        if (!$validationResult['valid']) {
            \Log::warning('Transaction creation blocked: checkpoint violation');
            throw ValidationException::withMessages([
                'date' => [$validationResult['message']]
            ]);
        }

        // Auto-remap negative Dividend (type 8) transactions based on account
        if ($transaction->transaction_type_id === 8 && $transaction->cashflow_value < 0) {
            // Load the config relationship to get the account
            $transaction->loadMissing('config.account');

            if ($transaction->config && method_exists($transaction->config, 'account')) {
                $account = $transaction->config->account;

                if ($account) {
                    // If account name contains "WiseAlpha", use type 12 (Purchased Interest)
                    // Otherwise use type 14 (Product Fee)
                    $transaction->transaction_type_id = str_contains($account->name, 'WiseAlpha') ? 12 : 14;
                }
            }
        }

        // Observer not needed for Interest ReInvest anymore - handled in API controller
        return true; // Allow all transactions to proceed
    }

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        // No longer needed - handled in creating()
    }

    /**
     * Handle the Transaction "updating" event (before save).
     */
    public function updating(Transaction $transaction): bool
    {
        // Check if transaction was previously reconciled
        if ($transaction->getOriginal('reconciled')) {
            // Allow ONLY unreconciling (changing reconciled from true to false)
            // Get all changed attributes
            $dirty = $transaction->getDirty();
            
            // If the only change is reconciled flag going to false, allow it
            if (count($dirty) === 1 && isset($dirty['reconciled']) && $dirty['reconciled'] == false) {
                return true;
            }
            
            // Any other changes are blocked
            throw ValidationException::withMessages([
                'transaction' => ['Cannot modify reconciled transaction']
            ]);
        }

        // Validate against balance checkpoints
        $balanceService = new BalanceCheckpointService();
        $validationResult = $balanceService->validateTransaction($transaction, true);

        if (!$validationResult['valid']) {
            throw ValidationException::withMessages([
                'date' => [$validationResult['message']]
            ]);
        }

        return true;
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        // If cashflow_value is null after update, recalculate it
        // BUT only if the transaction type is supposed to have a cashflow
        // (Skip types with amount_multiplier = null, like "Add shares" or "Interest ReInvest")
        if ($transaction->cashflow_value === null
            && $transaction->transactionType
            && $transaction->transactionType->amount_multiplier !== null) {

            $transactionService = new TransactionService();
            $transaction->currency_id = $transactionService->getTransactionCurrencyId($transaction);
            $transaction->cashflow_value = $transactionService->getTransactionCashFlow($transaction);
            $transaction->saveQuietly();

            // Also recalculate monthly summaries
            $transactionService->recalculateMonthlySummaries($transaction);
        }
    }

    /**
     * Handle the Transaction "deleting" event (before delete).
     */
    public function deleting(Transaction $transaction): bool
    {
        // Check if transaction is reconciled
        $balanceService = new BalanceCheckpointService();
        $modifyCheck = $balanceService->canModifyTransaction($transaction, 'delete');

        if (!$modifyCheck['can_modify']) {
            throw ValidationException::withMessages([
                'transaction' => [$modifyCheck['reason']]
            ]);
        }

        // Validate that deletion won't violate balance checkpoints
        $validationResult = $balanceService->validateDeletion($transaction);

        if (!$validationResult['valid']) {
            throw ValidationException::withMessages([
                'transaction' => [$validationResult['message']]
            ]);
        }

        return true;
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {

    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {

    }

    /**
     * Handle the Transaction "force deleted" event.
     */
    public function forceDeleted(Transaction $transaction): void
    {

    }

    /**
     * When an "Interest ReInvest" transaction is being created, intercept it and create two transactions instead:
     * 1. Interest yield transaction (for the dividend/interest income)
     * 2. Buy transaction (for reinvesting at price 1)
     *
     * The original Interest ReInvest transaction is NOT saved.
     */
    private function handleInterestReInvest(Transaction $transaction): void
    {
        $config = $transaction->config;

        // Get the transaction type IDs
        $interestYieldType = TransactionType::where('name', 'Interest yield')->first();
        $buyType = TransactionType::where('name', 'Buy')->first();

        if (!$interestYieldType || !$buyType) {
            return;
        }

        // Set flag to prevent infinite loops
        self::$processingReInvest = true;

        try {
            $transactionService = new TransactionService();

            // Create the Interest Yield transaction config
            $interestYieldConfig = TransactionDetailInvestment::create([
                'account_id' => $config->account_id,
                'investment_id' => $config->investment_id,
                'price' => null,
                'quantity' => null,
                'commission' => $config->commission ?? 0,
                'tax' => $config->tax ?? 0,
                'dividend' => $config->dividend,
            ]);

            // Get the currency_id from the account
            $account = \App\Models\Account::find($config->account_id);
            $currencyId = $account->config->currency_id;

            // Calculate Interest Yield cashflow manually
            // For Interest yield: cashflow = dividend - tax - commission
            $interestCashflow = $config->dividend - ($config->tax ?? 0) - ($config->commission ?? 0);

            $interestTransaction = new Transaction([
                'user_id' => $transaction->user_id,
                'date' => $transaction->date,
                'transaction_type_id' => $interestYieldType->id,
                'config_type' => 'investment',
                'config_id' => $interestYieldConfig->id,
                'schedule' => $transaction->schedule,
                'budget' => $transaction->budget,
                'reconciled' => $transaction->reconciled,
                'comment' => $transaction->comment ? $transaction->comment . ' (Interest)' : 'Interest from ReInvest',
                'currency_id' => $currencyId,
                'cashflow_value' => $interestCashflow,
            ]);
            $interestTransaction->saveQuietly();
            $transactionService->recalculateMonthlySummaries($interestTransaction);

            // Create the Buy transaction config
            $buyConfig = TransactionDetailInvestment::create([
                'account_id' => $config->account_id,
                'investment_id' => $config->investment_id,
                'price' => 1,
                'quantity' => $config->dividend, // quantity equals the dividend amount
                'commission' => null,
                'tax' => null,
                'dividend' => null,
            ]);

            // Calculate Buy cashflow manually
            // For Buy: cashflow = -1 * price * quantity = -1 * 1 * dividend = -dividend
            $buyCashflow = -1 * 1 * $config->dividend;

            $buyTransaction = new Transaction([
                'user_id' => $transaction->user_id,
                'date' => $transaction->date,
                'transaction_type_id' => $buyType->id,
                'config_type' => 'investment',
                'config_id' => $buyConfig->id,
                'schedule' => $transaction->schedule,
                'budget' => $transaction->budget,
                'reconciled' => $transaction->reconciled,
                'comment' => $transaction->comment ? $transaction->comment . ' (Buy)' : 'Buy from ReInvest',
                'currency_id' => $currencyId,
                'cashflow_value' => $buyCashflow,
            ]);
            $buyTransaction->saveQuietly();
            $transactionService->recalculateMonthlySummaries($buyTransaction);

            // Clean up the config that was created for the Interest ReInvest transaction
            // (it won't be used since we're preventing the transaction from being saved)
            if ($config && $config->exists) {
                $config->delete();
            }
        } finally {
            // Reset flag
            self::$processingReInvest = false;
        }
    }
}
