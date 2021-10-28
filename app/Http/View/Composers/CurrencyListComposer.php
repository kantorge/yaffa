<?php

namespace App\Http\View\Composers;

use App\Models\Currency;
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
        $allCurrencies = Currency::pluck('name', 'id')->all();

        $view->with('allCurrencies', $allCurrencies);
    }
}
