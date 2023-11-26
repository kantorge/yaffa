<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class ImportController extends Controller
{
    /**
     * Display UI for importing and parsing CSV files.
     *
     * @return View
     */
    public function importCsv(): View
    {
        /**
         * @get('/import/csv')
         * @name('import.csv')
         * @middlewares('web')
         */
        // Load all active payees of user with config and pass to view as JavaScript variable.
        $payees = Auth::user()
            ->payees()
            ->active()
            ->with('config', 'config.category')
            ->get();

        JavaScriptFacade::put([
            'payees' => $payees,
        ]);

        return view('import.csv');
    }
}
