<?php

namespace App\Http\View\Composers;

use App\Models\InvestmentPriceProvider;
use Illuminate\View\View;

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
        $allInvestmentPriceProviders = InvestmentPriceProvider::pluck('name', 'id')->all();

        $view->with('allInvestmentPriceProviders', $allInvestmentPriceProviders);
    }
}
