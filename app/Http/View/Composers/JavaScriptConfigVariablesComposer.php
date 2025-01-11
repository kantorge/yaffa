<?php

namespace App\Http\View\Composers;

use App\Http\Traits\CurrencyTrait;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class JavaScriptConfigVariablesComposer
{
    use CurrencyTrait;

    /**
     * Bind data to the view as JavaScript variables.
     */
    public function compose(): void
    {
        JavaScriptFacade::put([
            // This type of restrictions must be implemented primarily on server-side, but the UI can also adapt in some cases
            'sandbox_mode' => config('yaffa.sandbox_mode'),
        ]);
    }
}
