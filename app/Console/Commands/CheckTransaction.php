<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Console\Command;

class CheckTransaction extends Command
{
    protected $signature = 'yaffa:check-transaction {id}';
    protected $description = 'Check transaction details and recalculate cashflow';

    public function handle(): int
    {
        $transactionId = $this->argument('id');
        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            $this->error("Transaction {$transactionId} not found");
            return 1;
        }

        $this->info("Transaction Details:");
        $this->line("ID: {$transaction->id}");
        $this->line("Type ID: {$transaction->transaction_type_id}");
        $this->line("Type Name: " . ($transaction->transactionType->name ?? 'N/A'));
        $this->line("Config Type: {$transaction->config_type}");
        $this->line("Date: {$transaction->date}");
        $this->line("Cashflow (current): " . ($transaction->cashflow_value ?? 'NULL'));
        $this->line("Currency ID (current): " . ($transaction->currency_id ?? 'NULL'));
        
        if ($transaction->isInvestment()) {
            $config = $transaction->config;
            $this->line("\nInvestment Config:");
            $this->line("  Account ID: {$config->account_id}");
            $this->line("  Investment ID: {$config->investment_id}");
            $this->line("  Price: " . ($config->price ?? 'NULL'));
            $this->line("  Quantity: " . ($config->quantity ?? 'NULL'));
            $this->line("  Dividend: " . ($config->dividend ?? 'NULL'));
            $this->line("  Tax: " . ($config->tax ?? 'NULL'));
            $this->line("  Commission: " . ($config->commission ?? 'NULL'));
        }

        // Try to recalculate
        $this->info("\nRecalculating...");
        try {
            $transactionService = new TransactionService();
            $newCurrencyId = $transactionService->getTransactionCurrencyId($transaction);
            $newCashflow = $transactionService->getTransactionCashFlow($transaction);
            
            $this->line("Calculated Currency ID: " . ($newCurrencyId ?? 'NULL'));
            $this->line("Calculated Cashflow: " . ($newCashflow ?? 'NULL'));
            
            if ($this->confirm('Update the transaction with these values?', true)) {
                $transaction->currency_id = $newCurrencyId;
                $transaction->cashflow_value = $newCashflow;
                $transaction->saveQuietly();
                $this->info("Transaction updated successfully");
            }
        } catch (\Exception $e) {
            $this->error("Error calculating: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
