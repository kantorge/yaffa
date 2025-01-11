<?php

namespace App\Http\View\Composers;

use Illuminate\View\View;

class DataLayerEventComposer
{
    /**
     * Bind data to the view.
     *
     * @param View $view
     */
    public function compose(View $view): void
    {
        $view->with('dataLayerEvents', session()->get('dataLayer', []));
    }
}
