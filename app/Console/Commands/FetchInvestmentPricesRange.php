<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Fetch historical investment prices from AlphaVantage for an investment's holding period.
 * 
 * This command determines the date range by:
 * 1. Finding the first buy/add/reinvest transaction (start date)
 * 2. Finding when holdings went to zero via sell/remove, or today if still holding (end date)
 * 3. Fetching daily prices for that range from AlphaVantage
 * 
 * Limitations:
 * - AlphaVantage free tier only provides ~100 days of history (compact mode)
 * - For full historical data, upgrade to AlphaVantage premium plan
 * - Free tier has 5 API calls/minute and 500 calls/day limits
 * 
 * Usage:
 *   php artisan yaffa:fetch-investment-prices-range {investment_id}
 *   php artisan yaffa:fetch-investment-prices-range 264 --dry-run
 */
class FetchInvestmentPricesRange extends Command
{
    protected $signature = 'yaffa:fetch-investment-prices-range {investment_id} {--dry-run : Show date range without fetching}';
    protected $description = 'Fetch AlphaVantage prices for an investment from first buy/add to first zero sell/remove or today. Note: Free tier only provides ~100 days.';

    public function handle()
    {
        $investmentId = $this->argument('investment_id');
        $investment = Investment::find($investmentId);
        if (!$investment) {
            $this->error('Investment not found.');
            return 1;
        }

        // Get all transactions for this investment, ordered by date
        $transactions = Transaction::where('config_type', 'investment')
            ->join('transaction_details_investment', function($join) use ($investmentId) {
                $join->on('transactions.config_id', '=', 'transaction_details_investment.id')
                     ->where('transaction_details_investment.investment_id', '=', $investmentId);
            })
            ->select('transactions.*')
            ->orderBy('date')
            ->get();

        if ($transactions->isEmpty()) {
            $this->error('No transactions found for this investment.');
            return 1;
        }

        // Transaction types: 4=Buy, 6=Add shares, 5=Sell, 7=Remove shares
        $buyAddTypes = [4, 6, 13]; // Buy, Add shares, Interest ReInvest
        $sellRemoveTypes = [5, 7]; // Sell, Remove shares
        
        // Find first buy/add transaction
        $firstBuy = $transactions->first(function ($t) use ($buyAddTypes) {
            return in_array($t->transaction_type_id, $buyAddTypes);
        });
        if (!$firstBuy) {
            $this->error('No buy/add transaction found.');
            return 1;
        }
        $startDate = Carbon::parse($firstBuy->date);

        // Walk through transactions to find when holding goes to zero
        $holding = 0;
        $endDate = null;
        foreach ($transactions as $t) {
            $shares = optional($t->config)->quantity ?? 0;
            if (in_array($t->transaction_type_id, $buyAddTypes)) {
                $holding += $shares;
            } elseif (in_array($t->transaction_type_id, $sellRemoveTypes)) {
                $holding -= $shares;
                if ($holding <= 0) {
                    $endDate = Carbon::parse($t->date);
                    break;
                }
            }
        }
        if (!$endDate) {
            $endDate = Carbon::today();
        }

        $daysDiff = $startDate->diffInDays($endDate);
        $this->info("Investment: #{$investmentId} {$investment->name} ({$investment->symbol})");
        $this->info("Date range: {$startDate->toDateString()} to {$endDate->toDateString()} ($daysDiff days)");
        
        // If dry-run, just show the info and exit
        if ($this->option('dry-run')) {
            $this->info("Dry run complete. Use without --dry-run to fetch prices.");
            return 0;
        }
        
        // Check if investment has AlphaVantage as price provider
        if ($investment->price_provider !== 'alpha_vantage') {
            $this->warn("Investment price provider is '{$investment->price_provider}', not 'alpha_vantage'");
            if (!$this->confirm('Continue anyway?', false)) {
                return 0;
            }
        }

        // Check for API key
        $apiKey = config('yaffa.alpha_vantage_key');
        if (!$apiKey) {
            $this->error('ALPHA_VANTAGE_KEY not configured in environment.');
            return 1;
        }

        $this->info("Using API key: " . substr($apiKey, 0, 4) . "..." . substr($apiKey, -4));
        
        try {
            // AlphaVantage free tier only allows compact (last 100 days) with outputsize=compact
            // We'll use refill=false to use compact mode
            $this->info("Fetching prices from AlphaVantage (compact mode - last ~100 trading days)...");
            $this->warn("Note: Free tier AlphaVantage only provides ~100 days of history per call.");
            $this->warn("For full historical data ({$daysDiff} days), you need a premium plan.");
            
            $investment->getInvestmentPriceFromAlphaVantage($startDate, false);
            
            $this->info("Successfully fetched prices from AlphaVantage.");
            
            // Show some stats
            $priceCount = \App\Models\InvestmentPrice::where('investment_id', $investmentId)
                ->whereBetween('date', [$startDate, $endDate])
                ->count();
            
            $this->info("Total prices stored in date range: $priceCount");
            
            if ($priceCount < $daysDiff / 2) {
                $this->warn("Price coverage is low. Consider upgrading to AlphaVantage premium for full historical data.");
            }
        } catch (\Exception $e) {
            $this->error("Error fetching prices: {$e->getMessage()}");
            $this->error("Stack trace: {$e->getTraceAsString()}");
            return 1;
        }

        $this->info('Done.');
        return 0;
    }
}
