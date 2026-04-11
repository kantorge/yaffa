<?php

namespace App\Listeners;

use App\Events\InvestmentPricesUpdated;
use App\Services\InvestmentService;

class RecalculateAccountsForInvestment
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly InvestmentService $investmentService)
    {
    }

    /**
     * Handle the event.
     */
    public function handle(InvestmentPricesUpdated $event): void
    {
        $this->investmentService->recalculateRelatedAccounts($event->investment);
    }
}
