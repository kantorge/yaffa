<?php

namespace App\Http\View\Composers;

use App\Enums\TransactionType;
use App\Http\Traits\CurrencyTrait;
use Illuminate\Support\Facades\Auth;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class JavaScriptVariablesComposer
{
    use CurrencyTrait;

    /**
     * Bind data to the view as JavaScript variables.
     */
    public function compose(): void
    {
        $payload = [
            'YAFFA' => [
                'config' => [
                    // This type of restriction is implemented primarily on server-side, but the UI can also adapt in some cases
                    'sandbox_mode' => config('yaffa.sandbox_mode'),
                    'ai_documents' => [
                        'google_drive' => [
                            'enabled' => config('ai-documents.google_drive.enabled'),
                        ],
                    ],
                    // Transaction types for frontend usage
                    'transactionTypes' => TransactionType::all(),
                    // Date presets for date range pickers
                    'datePresets' => config('yaffa.account_date_presets', []),
                    'translations' => $this->getTranslations(),
                ],
                'userSettings' => [],
            ],
        ];

        $user = Auth::user();
        if ($user) {
            $payload['YAFFA']['userSettings'] = [
                'baseCurrency' => $this->getBaseCurrency(),
                'language' => $user->language,
                'locale' => $user->locale,
                'start_date' => $user->start_date,
                'end_date' => $user->end_date,
                'account_details_date_range' => $user->account_details_date_range,
            ];
        }

        JavaScriptFacade::put($payload);
    }

    private function getTranslations(): array
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
