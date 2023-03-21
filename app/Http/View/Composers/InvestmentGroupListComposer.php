<?php

namespace App\Http\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvestmentGroupListComposer
{
    /**
     * Bind list of all investment groups to the view.
     *
     * @param View $view
     */
    public function compose(View $view)
    {
        $allInvestmentGropus = Auth::user()
            ->investmentGroups()
            ->select('name', 'id')
            ->orderBy('name')
            ->pluck('name', 'id');

        $view->with('allInvestmentGropus', $allInvestmentGropus);
    }
}
