<?php

namespace App\Http\View\Composers;

use App\Http\Traits\CurrencyTrait;
use Illuminate\Support\Facades\Auth;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class JavaScriptUserVariablesComposer
{
    use CurrencyTrait;

    /**
     * Bind data to the view as JavaScript variables.
     */
    public function compose(): void
    {
        $user = Auth::user();

        JavaScriptFacade::put([
            'YAFFA' => [
                'baseCurrency' => $this->getBaseCurrency(),
                'language' => $user->language,
                'locale' => $user->locale,
                'translations' => $this->getTranslations(),
                'start_date' => $user->start_date,
                'end_date' => $user->end_date,
                'account_details_date_range' => $user->account_details_date_range,
            ]
        ]);
    }

    private function getTranslations()
    {
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        $candidates = [];
        $candidates[] = lang_path($locale . '.json');
        $candidates[] = lang_path($fallback . '.json');

        $translationFile = null;
        foreach ($candidates as $candidate) {
            if ($candidate && is_readable($candidate)) {
                $translationFile = $candidate;
                break;
            }
        }

        if (!$translationFile) {
            // No translations file found — return empty array so frontend code can handle it.
            return [];
        }

        $contents = file_get_contents($translationFile);
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
