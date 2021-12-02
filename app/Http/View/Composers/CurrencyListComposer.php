<?php

namespace App\Http\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CurrencyListComposer
{
    /**
     * Bind list of all currencies to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $allCurrencies = Auth::user()
        ->currencies()
        ->pluck('name', 'id')->all();

        $view->with('allCurrencies', $allCurrencies);
    }
}
