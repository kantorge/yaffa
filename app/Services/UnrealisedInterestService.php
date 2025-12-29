<?php

namespace App\Services;

use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\UkTaxYearTrait;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for calculating unrealised interest on investments
 * Supports daily compound interest calculations
 */
class UnrealisedInterestService
{
    use CurrencyTrait;

    /**
     * Calculate unrealised interest for a single investment across all accounts
     * 
     * @param Investment $investment
     * @param Carbon|null $asOfDate
     * @return array
     */
    public function calculateInvestmentInterest(Investment $investment, Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::today();

        if (!$investment->interest_rate || $investment->interest_rate == 0) {
            return [
                'has_rate' => false,
                'accounts' => [],
                'total_unrealised' => 0,
                'total_realised' => 0,
            ];
        }

        $accounts = $investment->transactionDetailInvestment()
            ->pluck('account_id')
            ->unique()
            ->mapWithKeys(function ($accountId) use ($investment, $asOfDate) {
                $account = AccountEntity::find($accountId);
                if (!$account) {
                    return [];
                }

                $unrealised = $this->calculateUnrealisedInterest($investment, $account, $asOfDate);
                $realised = $this->calculateRealisedInterest($investment, $account);

                return [
                    $accountId => [
                        'account_id' => $accountId,
                        'account_name' => $account->name,
                        'unrealised' => $unrealised,
                        'realised' => $realised,
                    ],
                ];
            });

        $totalUnrealised = $accounts->sum('unrealised');
        $totalRealised = $accounts->sum('realised');

        return [
            'has_rate' => true,
            'investment_id' => $investment->id,
            'investment_name' => $investment->name,
            'interest_rate' => $investment->interest_rate,
            'accounts' => $accounts->values(),
            'total_unrealised' => round($totalUnrealised, 2),
            'total_realised' => round($totalRealised, 2),
            'as_of_date' => $asOfDate,
        ];
    }

    /**
     * Calculate unrealised (accrued but not yet received) interest
     * Using daily compound interest formula: A = P(1 + r/365)^n
     * But we return just the interest amount (A - P)
     */
    public function calculateUnrealisedInterest(Investment $investment, AccountEntity $account, Carbon $asOfDate = null): float
    {
        $asOfDate = $asOfDate ?? Carbon::today();

        if (!$investment->interest_rate || $investment->interest_rate == 0) {
            return 0;
        }

        // Get all buy/add transactions before asOfDate
        $transactions = Transaction::where('transactions.user_id', $account->user_id)
            ->where('transactions.config_type', 'investment')
            ->whereIn('transactions.transaction_type_id', [4, 6]) // Buy, Add shares
            ->where('transactions.date', '<=', $asOfDate)
            ->join('transaction_details_investment as tdi', function ($join) use ($investment, $account) {
                $join->on('transactions.config_id', '=', 'tdi.id')
                    ->where('tdi.investment_id', '=', $investment->id)
                    ->where('tdi.account_id', '=', $account->id);
            })
            ->select(
                'transactions.id',
                'transactions.date',
                'tdi.quantity',
                'tdi.price'
            )
            ->orderBy('transactions.date')
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $totalUnrealised = 0;
        $rate = $investment->interest_rate; // Already stored as decimal (0.07 for 7%)

        foreach ($transactions as $buyTransaction) {
            $principal = $buyTransaction->quantity * $buyTransaction->price;
            $buyDate = Carbon::parse($buyTransaction->date);

            // Find the most recent interest payment on or after the buy date but before asOfDate
            $lastInterestAfterBuy = $this->getLastInterestPaymentDateBetween($investment, $account, $buyDate, $asOfDate);
            
            // If there's an interest payment after this buy, start calculating unrealised from AFTER that payment
            // Otherwise, start from the buy date
            $calculationStartDate = $lastInterestAfterBuy ? $lastInterestAfterBuy->addDay() : $buyDate;

            // Calculate days from calculation start to as-of date
            $days = $calculationStartDate->diffInDays($asOfDate);

            if ($days < 0) {
                continue;
            }

            // Daily compound interest: Interest = Principal × [(1 + r/365)^days - 1]
            $compoundFactor = pow(1 + ($rate / 365), $days);
            $interest = $principal * ($compoundFactor - 1);

            $totalUnrealised += $interest;
        }

        return round($totalUnrealised, 2);
    }

    /**
     * Calculate realised (received) interest
     */
    public function calculateRealisedInterest(Investment $investment, AccountEntity $account): float
    {
        return Transaction::where('transactions.user_id', $account->user_id)
            ->where('transactions.config_type', 'investment')
            ->where('transactions.transaction_type_id', 13) // Interest ReInvest
            ->join('transaction_details_investment as tdi', function ($join) use ($investment, $account) {
                $join->on('transactions.config_id', '=', 'tdi.id')
                    ->where('tdi.investment_id', '=', $investment->id)
                    ->where('tdi.account_id', '=', $account->id);
            })
            ->sum('tdi.dividend');
    }

    /**
     * Get total received interest for a period
     */
    private function getReceivedInterest(Investment $investment, AccountEntity $account, Carbon $startDate = null, Carbon $endDate = null): float
    {
        $query = Transaction::where('transactions.user_id', $account->user_id)
            ->where('transactions.config_type', 'investment')
            ->where('transactions.transaction_type_id', 13) // Interest ReInvest
            ->join('transaction_details_investment as tdi', function ($join) use ($investment, $account) {
                $join->on('transactions.config_id', '=', 'tdi.id')
                    ->where('tdi.investment_id', '=', $investment->id)
                    ->where('tdi.account_id', '=', $account->id);
            });

        if ($startDate) {
            $query->where('transactions.date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('transactions.date', '<=', $endDate);
        }

        return $query->sum('tdi.dividend');
    }

    /**
     * Get the most recent interest payment date between two dates for an investment in an account
     */
    private function getLastInterestPaymentDateBetween(Investment $investment, AccountEntity $account, Carbon $afterDate, Carbon $beforeDate): ?Carbon
    {
        $transaction = Transaction::where('transactions.user_id', $account->user_id)
            ->where('transactions.config_type', 'investment')
            ->where('transactions.transaction_type_id', 13) // Interest ReInvest
            ->whereBetween('transactions.date', [$afterDate, $beforeDate])
            ->join('transaction_details_investment as tdi', function ($join) use ($investment, $account) {
                $join->on('transactions.config_id', '=', 'tdi.id')
                    ->where('tdi.investment_id', '=', $investment->id)
                    ->where('tdi.account_id', '=', $account->id);
            })
            ->orderBy('transactions.date', 'desc')
            ->first('transactions.date');

        return $transaction ? Carbon::parse($transaction->date) : null;
    }

    /**
     * Get unrealised interest report for all investments with interest rates
     * for a specific tax year or period
     */
    public function getUnrealisedInterestReport(int $userId, Carbon $startDate, Carbon $endDate): Collection
    {
        // Get all investments with interest rates
        $investments = Investment::where('user_id', $userId)
            ->where('active', true)
            ->where('interest_rate', '>', 0)
            ->with('currency')
            ->get();

        $baseCurrency = $this->getBaseCurrency();
        $report = collect();

        foreach ($investments as $investment) {
            $interest = $this->calculateInvestmentInterest($investment, $endDate);

            if (!$interest['has_rate']) {
                continue;
            }

            // Group by account
            foreach ($interest['accounts'] as $account) {
                $report->push([
                    'investment_id' => $investment->id,
                    'investment_name' => $investment->name,
                    'account_id' => $account['account_id'],
                    'account_name' => $account['account_name'],
                    'interest_rate' => $investment->interest_rate,
                    'currency' => $investment->currency->code,
                    'unrealised' => $account['unrealised'],
                    'realised' => $account['realised'],
                    'total' => $account['unrealised'] + $account['realised'],
                ]);
            }
        }

        return $report->sortBy('account_name')->values();
    }
}
