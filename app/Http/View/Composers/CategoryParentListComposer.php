<?php

namespace App\Http\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CategoryParentListComposer
{
    /**
     * Bind list of all accounts to the view.
     */
    public function compose(View $view)
    {
        // Get all possible parents
        $parents = Auth::user()
            ->categories()
            ->whereNull('parent_id')
            ->pluck('name', 'id');

        // Parents that switched budget suggestions off exclude their children as well
        $view->with('parents', $parents);
        $view->with('parentsSuggestionsOff', Auth::user()
            ->categories()
            ->whereNull('parent_id')
            ->where('calculate_budget_suggestions', false)
            ->pluck('id')
            ->all());
    }
}
