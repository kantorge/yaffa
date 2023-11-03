<?php

namespace App\Http\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountGroupListComposer
{
    /**
     * Bind list of all investment groups to the view.
     */
    public function compose(View $view): void
    {
        $allAccountGroups = Auth::user()
            ->accountGroups()
            ->select('name', 'id')
            ->orderBy('name')
            ->pluck('name', 'id');

        $view->with('allAccountGroups', $allAccountGroups);
    }
}
