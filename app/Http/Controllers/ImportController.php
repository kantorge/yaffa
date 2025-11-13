<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    /**
     * Show MoneyHub upload and preview page.
     */
    public function moneyhubUpload()
    {
        return view('import.moneyhub');
    }
    /**
     * Display UI for importing and parsing CSV files.
     *
     * @return View
     */
    public function importCsv(): View
    {
        // Load all active payees of user with config and pass to view as JavaScript variable.
        $payees = Auth::user()
            ->payees()
            ->active()
            ->with('config', 'config.category')
            ->get();

        JavaScriptFacade::put([
            'payees' => $payees,
        ]);

    $parsedRows = session('parsedRows', null);
    return view('import.csv', compact('parsedRows'));
    }

    /**
     * Handle CSV upload and parse for regular transactions.
     */
    public function uploadCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs('csv-imports', $request->user()->id . '_' . time() . '_' . $file->getClientOriginalName(), 'local');
        $fullPath = \Storage::disk('local')->path($path);

        $rows = [];
        if (($handle = fopen($fullPath, 'r')) !== false) {
            $header = fgetcsv($handle);
            // Normalize Hungarian 'Típus' to 'Type' for compatibility
            foreach ($header as &$h) {
                if (trim(mb_strtolower($h)) === 'típus') {
                    $h = 'Type';
                }
            }
            unset($h);
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = array_combine($header, $row);
            }
            fclose($handle);
        }

        \Storage::disk('local')->delete($path);

        // Redirect back to import.csv with parsed rows in session
        return redirect()->route('import.csv')->with('parsedRows', $rows);
    }
}
