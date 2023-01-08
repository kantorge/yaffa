<?php

namespace App\Http\View\Composers;

use App\Http\Traits\CurrencyTrait;
use Illuminate\Support\Facades\Auth;
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
        // TODO: move these variables into a private object from global scope
        JavaScriptFacade::put([
            'YAFFA' => [
                'baseCurrency' => $this->getBaseCurrency(),
                'language' => Auth::user()->language,
                'locale' => Auth::user()->locale,
                'translations' => $this->getTranslations(),
            ]
        ]);
    }

    private function getTranslations()
    {
        $translationFile = resource_path('lang/' . app()->getLocale() . '.json');

        if (! is_readable($translationFile)) {
            $translationFile = resource_path('lang/' . config('app.fallback_locale') . '.json');
        }

        return json_decode(file_get_contents($translationFile), true);
    }
}
