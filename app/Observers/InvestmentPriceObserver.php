<?php

namespace App\Observers;

use App\Models\InvestmentPrice;
use App\Services\InvestmentService;

class InvestmentPriceObserver
{
    /**
     * Handle the InvestmentPrice "created" event.
     */
    public function created(InvestmentPrice $investmentPrice): void
    {
        $this->recalculateRelatedAccounts($investmentPrice);
    }

    /**
     * Handle the InvestmentPrice "updated" event.
     */
    public function updated(InvestmentPrice $investmentPrice): void
    {
        $this->recalculateRelatedAccounts($investmentPrice);
    }

    /**
     * Handle the InvestmentPrice "deleted" event.
     */
    public function deleted(InvestmentPrice $investmentPrice): void
    {
        $this->recalculateRelatedAccounts($investmentPrice);
    }

    private function recalculateRelatedAccounts(InvestmentPrice $investmentPrice): void
    {
        $investment = $investmentPrice->investment;

        // Use the InvestmentService to recalculate the related accounts
        $investmentService = new InvestmentService();
        $investmentService->recalculateRelatedAccounts($investment);
    }
}
