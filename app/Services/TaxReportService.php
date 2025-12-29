<?php

namespace App\Services;

use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\UkTaxYearTrait;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for calculating UK tax reports
 * Handles dividend income and capital gains calculations
 * All amounts are converted to base currency (GBP) at transaction date rates
 */
class TaxReportService
{
    use UkTaxYearTrait;
    use CurrencyTrait;

    /**
     * Get dividend summary for a tax year
     * Groups by account and investment, sums dividend amounts
     * Converts all amounts to base currency (GBP) at transaction date rates
     */
    public function getDividendSummary(int $userId, Carbon $startDate, Carbon $endDate): Collection
    {
        // Transaction type 8 = Dividend
        $dividends = Transaction::where('transactions.user_id', $userId)
            ->where('transactions.config_type', 'investment')
            ->where('transactions.transaction_type_id', 8)
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->join('transaction_details_investment as tdi', function($join) {
                $join->on('transactions.config_id', '=', 'tdi.id');
            })
            ->join('investments', 'tdi.investment_id', '=', 'investments.id')
            ->join('account_entities as ae', 'tdi.account_id', '=', 'ae.id')
            ->join('accounts', 'ae.config_id', '=', 'accounts.id')
            ->join('investment_groups', 'investments.investment_group_id', '=', 'investment_groups.id')
            ->select(
                'transactions.id as transaction_id',
                'transactions.date as transaction_date',
                DB::raw('COALESCE(transactions.currency_id, accounts.currency_id) as currency_id'),
                'ae.id as account_id',
                'ae.name as account_name',
                'investments.id as investment_id',
                'investments.name as investment_name',
                'investment_groups.name as investment_group_name',
                'ae.tax_exempt',
                'tdi.dividend',
                'tdi.tax'
            )
            ->orderBy('ae.name')
            ->orderBy('investments.name')
            ->get();

        // Get base currency
        $baseCurrency = $this->getBaseCurrency();

        // Group and convert to base currency
        $grouped = $dividends->groupBy(function($item) {
            return $item->account_id . '_' . $item->investment_id;
        })->map(function($group) use ($baseCurrency) {
            $first = $group->first();
            $totalDividendInBase = 0;
            $totalTaxInBase = 0;
            
            foreach ($group as $item) {
                // Convert dividend to base currency at transaction date rate
                $dividendInBase = $this->convertCurrency(
                    $item->dividend,
                    $item->currency_id,
                    $baseCurrency->id,
                    Carbon::parse($item->transaction_date)
                );
                $totalDividendInBase += $dividendInBase;
                
                // Convert tax to base currency at transaction date rate
                if ($item->tax) {
                    $taxInBase = $this->convertCurrency(
                        $item->tax,
                        $item->currency_id,
                        $baseCurrency->id,
                        Carbon::parse($item->transaction_date)
                    );
                    $totalTaxInBase += $taxInBase;
                }
            }
            
            return (object)[
                'account_id' => $first->account_id,
                'account_name' => $first->account_name,
                'investment_id' => $first->investment_id,
                'investment_name' => $first->investment_name,
                'investment_group_name' => $first->investment_group_name,
                'tax_exempt' => $first->tax_exempt,
                'total_dividend' => $totalDividendInBase,
                'total_tax' => $totalTaxInBase,
                'transaction_count' => $group->count(),
                'tax_rate' => $first->tax_exempt ? 0 : null,
                'taxable_amount' => $first->tax_exempt ? 0 : $totalDividendInBase,
            ];
        })->values();

        return $grouped;
    }

    /**
     * Get capital gains summary for a tax year
     * Calculates average buy price, average sell price, and gain/loss for each investment
     * Converts all amounts to base currency (GBP) at transaction date rates
     * Capital losses are included in the gain_loss column (as negative values)
     */
    public function getCapitalGainsSummary(int $userId, Carbon $startDate, Carbon $endDate): Collection
    {
        // Get base currency
        $baseCurrency = $this->getBaseCurrency();
        
        // Transaction types: 5 = Sell, 7 = Remove shares
        $sellTransactions = Transaction::where('transactions.user_id', $userId)
            ->where('transactions.config_type', 'investment')
            ->whereIn('transactions.transaction_type_id', [5, 7])
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->join('transaction_details_investment as tdi', function($join) {
                $join->on('transactions.config_id', '=', 'tdi.id');
            })
            ->join('investments', 'tdi.investment_id', '=', 'investments.id')
            ->join('account_entities as ae', 'tdi.account_id', '=', 'ae.id')
            ->join('accounts', 'ae.config_id', '=', 'accounts.id')
            ->select(
                'transactions.id',
                'transactions.date',
                DB::raw('COALESCE(transactions.currency_id, accounts.currency_id) as currency_id'),
                'ae.id as account_id',
                'ae.name as account_name',
                'ae.tax_exempt',
                'investments.id as investment_id',
                'investments.name as investment_name',
                'tdi.quantity as shares_sold',
                'tdi.price as sell_price',
                'tdi.commission as sell_commission'
            )
            ->orderBy('investments.name')
            ->orderBy('transactions.date')
            ->get();

        // Group by investment and calculate average buy price for each
        $grouped = $sellTransactions->groupBy('investment_id')->map(function($sales, $investmentId) use ($userId, $baseCurrency) {
            $firstSale = $sales->first();
            
            // Calculate average purchase cost for this investment in base currency
            // Get all buy transactions up to the last sell date in this tax year
            $lastSellDate = $sales->max('date');
            $avgBuyPriceInBase = $this->getAverageBuyPriceInBaseCurrency($userId, $investmentId, Carbon::parse($lastSellDate), $baseCurrency->id);
            
            // Convert all sell transactions to base currency and sum up
            $totalSharesSold = 0;
            $totalProceedsInBase = 0;
            $totalCommissionInBase = 0;
            $totalGrossProceedsInBase = 0;
            
            foreach ($sales as $sale) {
                $transactionDate = Carbon::parse($sale->date);
                
                // Convert proceeds to base currency
                $grossProceeds = $sale->shares_sold * $sale->sell_price;
                $grossProceedsInBase = $this->convertCurrency(
                    $grossProceeds,
                    $sale->currency_id,
                    $baseCurrency->id,
                    $transactionDate
                );
                
                $commissionInBase = $this->convertCurrency(
                    $sale->sell_commission ?? 0,
                    $sale->currency_id,
                    $baseCurrency->id,
                    $transactionDate
                );
                
                $totalSharesSold += $sale->shares_sold;
                $totalGrossProceedsInBase += $grossProceedsInBase;
                $totalCommissionInBase += $commissionInBase;
                $totalProceedsInBase += ($grossProceedsInBase - $commissionInBase);
            }
            
            $avgSellPriceInBase = $totalSharesSold > 0 ? $totalGrossProceedsInBase / $totalSharesSold : 0;
            $costBasisInBase = $avgBuyPriceInBase * $totalSharesSold;
            $gainLossInBase = $totalProceedsInBase - $costBasisInBase;
            
            // Both gains AND losses go in the gain_loss column
            // For taxable_gain, only positive gains from non-exempt accounts are taxable
            $taxableGain = $firstSale->tax_exempt ? 0 : ($gainLossInBase > 0 ? $gainLossInBase : 0);
            
            return [
                'account_id' => $firstSale->account_id,
                'account_name' => $firstSale->account_name,
                'tax_exempt' => $firstSale->tax_exempt,
                'investment_id' => $investmentId,
                'investment_name' => $firstSale->investment_name,
                'shares_sold' => $totalSharesSold,
                'avg_buy_price' => $avgBuyPriceInBase,
                'avg_sell_price' => $avgSellPriceInBase,
                'cost_basis' => $costBasisInBase,
                'gross_proceeds' => $totalGrossProceedsInBase,
                'net_proceeds' => $totalProceedsInBase,
                'total_commission' => $totalCommissionInBase,
                'gain_loss' => $gainLossInBase, // Now includes both gains AND losses
                'taxable_gain' => $taxableGain,
                'transaction_count' => $sales->count(),
                'transactions' => $sales->toArray(),
            ];
        })->values();

        return $grouped;
    }

    /**
     * Calculate average buy price for an investment up to a specific date in base currency
     * Uses weighted average based on all buy transactions, converted to base currency at transaction dates
     */
    private function getAverageBuyPriceInBaseCurrency(int $userId, int $investmentId, Carbon $upToDate, int $baseCurrencyId): float
    {
        // Transaction types: 4 = Buy, 6 = Add shares, 13 = Interest ReInvest
        $buys = Transaction::where('transactions.user_id', $userId)
            ->where('transactions.config_type', 'investment')
            ->whereIn('transactions.transaction_type_id', [4, 6, 13])
            ->where('transactions.date', '<=', $upToDate)
            ->join('transaction_details_investment as tdi', function($join) use ($investmentId) {
                $join->on('transactions.config_id', '=', 'tdi.id')
                     ->where('tdi.investment_id', '=', $investmentId);
            })
            ->join('account_entities as ae', 'tdi.account_id', '=', 'ae.id')
            ->join('accounts', 'ae.config_id', '=', 'accounts.id')
            ->select(
                'transactions.date',
                DB::raw('COALESCE(transactions.currency_id, accounts.currency_id) as currency_id'),
                'tdi.quantity',
                'tdi.price',
                'tdi.commission'
            )
            ->get();

        if ($buys->isEmpty()) {
            return 0;
        }

        $totalSharesInBase = 0;
        $totalCostInBase = 0;

        foreach ($buys as $buy) {
            $transactionDate = Carbon::parse($buy->date);
            
            // Convert cost to base currency at transaction date
            $cost = ($buy->quantity * $buy->price) + ($buy->commission ?? 0);
            $costInBase = $this->convertCurrency(
                $cost,
                $buy->currency_id,
                $baseCurrencyId,
                $transactionDate
            );
            
            $totalSharesInBase += $buy->quantity;
            $totalCostInBase += $costInBase;
        }

        if ($totalSharesInBase > 0) {
            return $totalCostInBase / $totalSharesInBase;
        }

        return 0;
    }

    /**
     * Get summary totals for a tax year
     */
    public function getTaxYearSummary(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $dividends = $this->getDividendSummary($userId, $startDate, $endDate);
        $capitalGains = $this->getCapitalGainsSummary($userId, $startDate, $endDate);

        return [
            'total_dividends' => $dividends->sum('total_dividend'),
            'taxable_dividends' => $dividends->sum('taxable_amount'),
            'tax_exempt_dividends' => $dividends->where('tax_exempt', true)->sum('total_dividend'),
            'total_tax_paid' => $dividends->sum('total_tax'),
            'total_proceeds' => $capitalGains->sum('net_proceeds'),
            'total_cost_basis' => $capitalGains->sum('cost_basis'),
            'total_gains' => $capitalGains->sum('gain_loss'),
            'taxable_gains' => $capitalGains->sum('taxable_gain'),
            'tax_exempt_gains' => $capitalGains->where('tax_exempt', true)->sum('gain_loss'),
        ];
    }

    /**
     * Get EIS/SEIS investments with buy events in a tax year
     */
    public function getEisSeisBuys(int $userId, Carbon $startDate, Carbon $endDate): Collection
    {
        // Transaction types: 4 = Buy, 6 = Add shares
        $buys = Transaction::where('transactions.user_id', $userId)
            ->where('transactions.config_type', 'investment')
            ->whereIn('transactions.transaction_type_id', [4, 6])
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->join('transaction_details_investment as tdi', function($join) {
                $join->on('transactions.config_id', '=', 'tdi.id');
            })
            ->join('investments', 'tdi.investment_id', '=', 'investments.id')
            ->join('investment_groups', 'investments.investment_group_id', '=', 'investment_groups.id')
            ->join('account_entities as ae', 'tdi.account_id', '=', 'ae.id')
            ->join('accounts', 'ae.config_id', '=', 'accounts.id')
            ->whereIn(DB::raw('LOWER(investment_groups.name)'), ['eis', 'seis'])
            ->select(
                'transactions.id as transaction_id',
                'transactions.date as transaction_date',
                'investments.id as investment_id',
                'investments.name as investment_name',
                'investment_groups.name as investment_group_name',
                'ae.name as account_name',
                DB::raw('COALESCE(transactions.currency_id, accounts.currency_id) as currency_id'),
                'tdi.quantity',
                'tdi.price',
                'tdi.commission'
            )
            ->orderBy('investment_groups.name')
            ->orderBy('investments.name')
            ->orderBy('transactions.date')
            ->get();

        // Get base currency
        $baseCurrency = $this->getBaseCurrency();

        // Convert amounts to base currency and group by investment
        return $buys->groupBy(function($item) {
            return $item->investment_id;
        })->map(function($group) use ($baseCurrency) {
            $first = $group->first();
            $totalCostInBase = 0;
            $totalQuantity = 0;

            foreach ($group as $item) {
                $cost = ($item->quantity * $item->price) + ($item->commission ?? 0);
                $costInBase = $this->convertCurrency(
                    $cost,
                    $item->currency_id,
                    $baseCurrency->id,
                    Carbon::parse($item->transaction_date)
                );
                $totalCostInBase += $costInBase;
                $totalQuantity += $item->quantity;
            }

            return (object)[
                'investment_id' => $first->investment_id,
                'investment_name' => $first->investment_name,
                'investment_group_name' => $first->investment_group_name,
                'account_name' => $first->account_name,
                'total_quantity' => $totalQuantity,
                'total_cost' => $totalCostInBase,
                'transaction_count' => $group->count(),
                'buy_dates' => $group->map(fn($b) => Carbon::parse($b->transaction_date)->format('d M Y'))->unique()->values(),
            ];
        })->values();
    }
}

