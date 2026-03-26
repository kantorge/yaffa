<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth', 'verified'];
    }

    public function settings(): View
    {
        /**
         * @get("/user/settings")
         * @name("user.settings")
         * @middlewares("web", "auth", "verified")
         */

        JavaScript::put([
            'languages' => config('app.available_languages'),
            'locales' => config('app.available_locales'),
            'datePresets' => config('yaffa.account_date_presets'),
        ]);

        return view('user.settings');
    }

    public function aiSettings(): View
    {
        /**
         * @get("/user/ai-settings")
         * @name("user.ai-settings")
         * @middlewares("web", "auth", "verified")
         */

        $incomingReceiptsEmail = config('yaffa.incoming_receipts_email');
        $incomingEmailConfigured = ! empty($incomingReceiptsEmail)
            && false !== filter_var($incomingReceiptsEmail, FILTER_VALIDATE_EMAIL);

        $tesseractAvailable = function_exists('tesseract_is_available')
            ? tesseract_is_available()
            : false;

        JavaScript::put([
            'aiProviders' => config('ai-documents.providers'),
            'aiSettingsPageMeta' => [
                'incoming_email' => [
                    'enabled' => ! empty($incomingReceiptsEmail),
                    'configured' => $incomingEmailConfigured,
                    'recipient' => $incomingEmailConfigured
                        ? $incomingReceiptsEmail
                        : null,
                ],
                'ocr' => [
                    'tesseract_enabled' => (bool) config('ai-documents.ocr.tesseract_enabled', false),
                    'tesseract_available' => $tesseractAvailable,
                    'tesseract_mode' => (string) config('ai-documents.ocr.tesseract_mode', 'binary'),
                ],
            ],
        ]);

        return view('user.ai-settings');
    }
}
