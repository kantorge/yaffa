<?php

namespace App\Services;

use App\Http\Traits\ScheduleTrait;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;

class InvestmentService
{
    use ScheduleTrait;

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
                'accountEntityId' => $accountId,
                'transactionType' => 'investment_value'
            ]);
        });
    }

    public function enrichInvestmentWithQuantityHistory(Investment $investment): Investment
    {
        $transactions = $investment->transactionsBasic()->get();
        $scheduledTransactions = $investment->transactionsScheduled()
            ->get()
            ->load(['transactionSchedule'])
            ->filter(function ($transaction) {
                return $transaction->transactionSchedule->active;
            });

        // Add all scheduled items to list of transactions
        $scheduleInstances = $this->getScheduleInstances($scheduledTransactions, 'start');
        $transactions = $transactions->concat($scheduleInstances);

        // Calculate historical and scheduled quantity changes for chart
        $runningTotal = 0;
        $runningSchedule = 0;
        $quantities = $transactions
            ->sortBy('date')
            ->map(function (Transaction $transaction) use (&$runningTotal, &$runningSchedule) {
                // Quantity operator can be 1, -1 or null.
                // It's the expected behavior to set the quantity to 0 if the operator is null.
                $quantity = $transaction->transactionType->quantity_multiplier * $transaction->config->quantity;

                $runningSchedule += $quantity;
                if (!$transaction->schedule) {
                    $runningTotal += $quantity;
                }

                return [
                    'date' => $transaction->date->format('Y-m-d'),
                    'quantity' => $runningTotal,
                    'schedule' => $runningSchedule,
                ];
            });

        $investment->quantities = array_values($quantities->toArray());

        return $investment;
    }
}
