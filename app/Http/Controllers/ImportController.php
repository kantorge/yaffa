<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class ImportController extends Controller
{
    /**
     * Display UI for importing and parsing files.
     */
    public function index(Request $request): View
    {
        /**
         * @get("/import")
         * @name("import.index")
         * @middlewares("web")
         */
        // Load all active payees of user with config and pass to view as JavaScript variable.
        $payees = $request->user()
            ->payees()
            ->active()
            ->with('config', 'config.category')
            ->get();

        $hasAiProvider = $request->user()
            ->aiProviderConfigs()
            ->exists();

        JavaScriptFacade::put([
            'payees' => $payees,
            'hasAiProvider' => $hasAiProvider,
        ]);

        return view('import.index');
    }
}
