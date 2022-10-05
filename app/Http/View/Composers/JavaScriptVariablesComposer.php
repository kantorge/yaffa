<?php

namespace App\Http\View\Composers;

use App\Http\Traits\CurrencyTrait;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class JavaScriptVariablesComposer
{
    use CurrencyTrait;

    /**
     * Bind data to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        JavaScriptFacade::put([
            'baseCurrency' => $this->getBaseCurrency(),
        ]);
    }
}
