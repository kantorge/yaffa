<?php

namespace App\Jobs;

use App\Models\Investment;
use App\Services\InvestmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GetInvestmentPrices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public Investment $investment;

    /**
     * Create a new job instance.
     */
    public function __construct(Investment $investment)
    {
        $this->investment = $investment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Invoke provider's getInvestmentPrice method
        $this->investment->getInvestmentPriceFromProvider();

        // Use the InvestmentService to recalculate the related accounts
        // TODO: this should be done once for all accounts
        $investmentService = new InvestmentService();
        $investmentService->recalculateRelatedAccounts($this->investment);
    }
}
