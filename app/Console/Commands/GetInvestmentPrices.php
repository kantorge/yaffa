<?php

namespace App\Console\Commands;

use App\Jobs\GetInvestmentPrices as GetInvestmentPricesJob;
use App\Models\Investment;
use Illuminate\Console\Command;

class GetInvestmentPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:investment-prices:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run retrieval of investment prices for all investments with known price providers.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get all active investments of all users, with a price provider and automatic price retrieval enabled
        $investments = Investment::where('auto_update', true)
            ->whereNotNull('investment_price_provider')
            ->withMax('investmentPrices', 'date')
            ->get();

        // Loop through all investments and invoke the price retrieval job
        $investments->each(function ($investment) {
            GetInvestmentPricesJob::dispatch($investment);
        });

        return 0;
    }
}
