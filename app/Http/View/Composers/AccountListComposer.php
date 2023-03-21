<?php

namespace App\Http\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountListComposer
{
    /**
     * Bind list of all accounts to the view.
     *
     * @param View $view
     */
    public function compose(View $view): void
    {
        $accounts = Auth::user()
            ->accounts()
            ->select('name', 'id')
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id');

        $view->with('accountsForNavbar', $accounts);
    }
}
