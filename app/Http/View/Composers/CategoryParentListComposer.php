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

        $view->with('parents', $parents);
    }
}
