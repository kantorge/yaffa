<?php

namespace App\Http\View\Composers;

use App\Models\AccountEntity;
use Illuminate\View\View;

class AccountListComposer
{
    /**
     * Bind list of all accounts to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $accounts = AccountEntity::select('name', 'id')
            ->where('config_type', 'account')
            ->active()
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id');

        $view->with('accountsForNavbar', $accounts);
    }
}
