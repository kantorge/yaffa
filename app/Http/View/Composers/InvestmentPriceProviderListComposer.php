<?php

namespace App\Http\View\Composers;

use App\Models\Investment;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

class InvestmentPriceProviderListComposer
{
    /**
     * Bind list of all investment price providers to the view.
     */
    public function compose(View $view): void
    {
        $investment = App::make(Investment::class);
        $allInvestmentPriceProviders = $investment->getAllInvestmentPriceProviders();

        $view->with('allInvestmentPriceProviders', $allInvestmentPriceProviders);
    }
}
