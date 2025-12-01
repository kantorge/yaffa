<?php

namespace App\Jobs;

use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportInvestmentPricesFromTrades implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Investment $investment;
    protected int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(Investment $investment, int $userId)
    {
        $this->investment = $investment;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting import of prices from trades for investment {$this->investment->id}");

        $imported = 0;
        $skipped = 0;

        // Process transactions in chunks to avoid memory issues
        Transaction::where('transactions.config_type', 'investment')
            ->whereIn('transactions.transaction_type_id', [4, 5]) // Buy and Sell have meaningful prices
            ->join('transaction_details_investment', function ($join) {
                $join->on('transactions.config_id', '=', 'transaction_details_investment.id')
                    ->where('transaction_details_investment.investment_id', '=', $this->investment->id);
            })
            ->select('transactions.date', 'transaction_details_investment.price')
            ->orderBy('transactions.date')
            ->chunk(100, function ($transactions) use (&$imported, &$skipped) {
                foreach ($transactions as $transaction) {
                    $price = $transaction->price;
                    $date = $transaction->date;

                    // Skip if price is null or 0
                    if (!$price || $price == 0) {
                        continue;
                    }

                    // Check if price already exists for this date
                    $existingPrice = InvestmentPrice::where('investment_id', $this->investment->id)
                        ->whereDate('date', $date)
                        ->first();

                    if ($existingPrice) {
                        $skipped++;
                        continue;
                    }

                    // Create new price record
                    InvestmentPrice::create([
                        'investment_id' => $this->investment->id,
                        'date' => $date,
                        'price' => $price,
                    ]);

                    $imported++;
                }
            });

        Log::info("Import completed for investment {$this->investment->id}: {$imported} imported, {$skipped} skipped");
    }
}

