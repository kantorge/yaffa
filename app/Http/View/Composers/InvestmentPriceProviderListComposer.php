<?php

namespace App\Http\View\Composers;

use App\Models\Investment;
use Illuminate\View\View;
use Illuminate\Support\Facades\App;

class InvestmentPriceProviderListComposer
{
    /**
     * Bind list of all investment price providers to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $investment = App::make(Investment::class);
        $allInvestmentPriceProviders = $investment->getAllInvestmentPriceProviders();

        $view->with('allInvestmentPriceProviders', $allInvestmentPriceProviders);
    }
}
