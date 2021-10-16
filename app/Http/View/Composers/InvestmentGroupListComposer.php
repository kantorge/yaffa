<?php

namespace App\Http\View\Composers;

use App\Models\InvestmentGroup;
use Illuminate\View\View;

class InvestmentGroupListComposer
{

    /**
     * Bind list of all investment groups to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $allInvestmentGropus = InvestmentGroup::pluck('name', 'id')->all();

        $view->with('allInvestmentGropus', $allInvestmentGropus);
    }
}
