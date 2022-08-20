<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use JavaScript;

class ImportController extends Controller
{
    /**
     * Display UI for importing and parsing CSV files.
     *
     * @return \Illuminate\Http\Response
     */
    public function importCsv()
    {
        // Load all active payees of user with config and pass to view as JavaScript variable.
        $payees = Auth::user()
            ->payees()
            ->active()
            ->with('config', 'config.category')
            ->get();

        JavaScript::put([
            'payees' => $payees,
        ]);

        return view('import.csv');
    }
}
