<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\TransactionDetailInvestment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;

class InvestmentService
{
    public function delete(Investment $investment): array
    {
        $success = false;
        $error = null;

        try {
            $investment->delete();
            $success = true;
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1451) {
                $error = __('Investment is in use, cannot be deleted');
            } else {
                $error = __('Database error:') . ' ' . $e->errorInfo[2];
            }
        }

        return [
            'success' => $success,
            'error' => $error,
        ];
    }

    public function recalculateRelatedAccounts(Investment $investment): void
    {
        // Get all transactions related to this investment
        $transactionConfigs = TransactionDetailInvestment::where('investment_id', $investment->id)->get();

        // Get all distinct accounts related to this investment
        $accounts = $transactionConfigs->map(fn ($transactionConfig) => $transactionConfig->account_id)->unique();

        // Recalculate the summaries for each account
        $accounts->each(function ($accountId) {
            Artisan::call('app:cache:account-monthly-summaries', [
                '--accountEntityId' => $accountId,
                '--transactionType' => 'investment_value'
            ]);
        });
    }
}
