<?php

namespace App\Http\View\Composers;

use App\Models\Investment;
use Illuminate\View\View;

class InstrumentTypeListComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $allInstrumentTypes = Investment::getInstrumentTypes();
        $view->with('allInstrumentTypes', $allInstrumentTypes);
    }
}