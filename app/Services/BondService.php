<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionSchedule;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BondService
{
    /**
     * Calculate and schedule dividend payments for a fractional bond
     */
    public function scheduleOrUpdateDividendPayments(Investment $investment): void
    {
        if (!$investment->isFractionalBond()) {
            return;
        }

        // Clear existing scheduled dividend and maturity transactions for this investment
        $this->clearScheduledTransactions($investment);

        // Get current quantity for the bond
        $currentQuantity = $investment->getCurrentQuantity();
        
        if ($currentQuantity <= 0) {
            return; // No quantity, no need to schedule
        }

        // Schedule dividend payments
        $this->createDividendSchedule($investment, $currentQuantity);
        
        // Schedule maturity event
        $this->createMaturityEvent($investment, $currentQuantity);
    }

    /**
     * Clear existing scheduled dividend and maturity transactions
     */
    private function clearScheduledTransactions(Investment $investment): void
    {
        $scheduledTransactions = Transaction::where('schedule', true)
            ->whereHasMorph('config', [TransactionDetailInvestment::class], function ($query) use ($investment) {
                $query->where('investment_id', $investment->id);
            })
            ->whereHas('transactionType', function ($query) {
                $query->whereIn('name', ['Dividend', 'Mature']);
            })
            ->get();

        foreach ($scheduledTransactions as $transaction) {
            $transaction->transactionSchedule?->delete();
            $transaction->delete();
        }
    }

    /**
     * Create dividend payment schedule based on interest schedule and APR
     */
    private function createDividendSchedule(Investment $investment, float $quantity): void
    {
        if (!$investment->apr || !$investment->interest_schedule || !$investment->maturity_date) {
            return;
        }

        $scheduleFrequency = $this->getScheduleFrequency($investment->interest_schedule);
        $intervalPerYear = $this->getIntervalPerYear($investment->interest_schedule);
        
        // Calculate dividend amount per payment
        $annualDividend = ($quantity * $investment->apr) / 100;
        $dividendPerPayment = $annualDividend / $intervalPerYear;

        // Create dividend transaction
        $dividendType = TransactionType::where('name', 'Dividend')->where('type', 'investment')->first();
        if (!$dividendType) {
            return;
        }

        // Find first account that has this investment (for scheduling purposes)
        $accountId = TransactionDetailInvestment::where('investment_id', $investment->id)
            ->join('transactions', 'transaction_details_investment.id', '=', 'transactions.config_id')
            ->where('transactions.schedule', false)
            ->value('transaction_details_investment.account_id');

        if (!$accountId) {
            return;
        }

        DB::transaction(function () use ($investment, $quantity, $dividendPerPayment, $dividendType, $accountId, $scheduleFrequency) {
            // Create transaction detail
            $transactionDetail = TransactionDetailInvestment::create([
                'account_id' => $accountId,
                'investment_id' => $investment->id,
                'price' => null,
                'quantity' => null,
                'commission' => 0,
                'tax' => 0,
                'dividend' => $dividendPerPayment,
            ]);

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => $investment->user_id,
                'transaction_type_id' => $dividendType->id,
                'config_type' => 'investment',
                'config_id' => $transactionDetail->id,
                'date' => Carbon::now(),
                'comment' => "Scheduled dividend payment for {$investment->name}",
                'schedule' => true,
                'budget' => false,
            ]);

            // Create transaction schedule
            TransactionSchedule::create([
                'transaction_id' => $transaction->id,
                'start_date' => $this->getNextDividendDate($investment->interest_schedule),
                'next_date' => $this->getNextDividendDate($investment->interest_schedule),
                'end_date' => $investment->maturity_date,
                'frequency' => $scheduleFrequency,
                'interval' => $this->getScheduleInterval($investment->interest_schedule),
                'count' => null,
                'inflation' => null,
                'automatic_recording' => false,
            ]);
        });
    }

    /**
     * Create maturity event (mirrors a sell transaction)
     */
    private function createMaturityEvent(Investment $investment, float $quantity): void
    {
        if (!$investment->maturity_date) {
            return;
        }

        $sellType = TransactionType::where('name', 'Sell')->where('type', 'investment')->first();
        if (!$sellType) {
            return;
        }

        // Find account for maturity event
        $accountId = TransactionDetailInvestment::where('investment_id', $investment->id)
            ->join('transactions', 'transaction_details_investment.id', '=', 'transactions.config_id')
            ->where('transactions.schedule', false)
            ->value('transaction_details_investment.account_id');

        if (!$accountId) {
            return;
        }

        // Get latest price for calculation (use 1.0 as default for bonds)
        $price = $investment->getLatestPrice() ?: 1.0;

        DB::transaction(function () use ($investment, $quantity, $sellType, $accountId, $price) {
            // Create transaction detail for maturity
            $transactionDetail = TransactionDetailInvestment::create([
                'account_id' => $accountId,
                'investment_id' => $investment->id,
                'price' => $price,
                'quantity' => $quantity,
                'commission' => 0,
                'tax' => 0,
                'dividend' => null,
            ]);

            // Create maturity transaction
            Transaction::create([
                'user_id' => $investment->user_id,
                'transaction_type_id' => $sellType->id,
                'config_type' => 'investment',
                'config_id' => $transactionDetail->id,
                'date' => $investment->maturity_date,
                'comment' => "Automatic maturity for {$investment->name}",
                'schedule' => false,
                'budget' => false,
            ]);
        });
    }

    /**
     * Get the frequency string for Laravel schedule based on interest schedule
     */
    private function getScheduleFrequency(string $interestSchedule): string
    {
        return match ($interestSchedule) {
            'monthly' => 'MONTHLY',
            'quarterly' => 'MONTHLY', // Will use interval 3
            'half_yearly' => 'MONTHLY', // Will use interval 6  
            'yearly' => 'YEARLY',
            default => 'MONTHLY',
        };
    }

    /**
     * Get the interval for the schedule
     */
    private function getScheduleInterval(string $interestSchedule): int
    {
        return match ($interestSchedule) {
            'monthly' => 1,
            'quarterly' => 3,
            'half_yearly' => 6,
            'yearly' => 1,
            default => 1,
        };
    }

    /**
     * Get number of payments per year
     */
    private function getIntervalPerYear(string $interestSchedule): int
    {
        return match ($interestSchedule) {
            'monthly' => 12,
            'quarterly' => 4,
            'half_yearly' => 2,
            'yearly' => 1,
            default => 12,
        };
    }

    /**
     * Get the next dividend payment date based on interest schedule
     */
    private function getNextDividendDate(string $interestSchedule): Carbon
    {
        $now = Carbon::now();
        
        return match ($interestSchedule) {
            'monthly' => $now->addMonth()->startOfMonth(),
            'quarterly' => $now->addMonths(3)->startOfQuarter(),
            'half_yearly' => $now->addMonths(6)->startOfMonth(),
            'yearly' => $now->addYear()->startOfYear(),
            default => $now->addMonth()->startOfMonth(),
        };
    }
}