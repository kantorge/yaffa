<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

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
     * Handle MoneyHub CSV upload, store file and dispatch background job.
     */
    public function handleMoneyhubUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'account_id' => 'nullable|exists:account_entities,id',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs('transaction-uploads', $request->user()->id . '_' . time() . '_' . $file->getClientOriginalName(), 'local');

        $import = \App\Models\ImportJob::create([
            'user_id' => $request->user()->id,
            'file_path' => $path,
            'status' => 'queued',
            'processed_rows' => 0,
        ]);

        $accountId = $request->input('account_id');
        \App\Jobs\ProcessMoneyhubUpload::dispatch($import->id, $accountId);

        return redirect()->route('import.moneyhub')->with('upload_result', ['queued' => true, 'import_id' => $import->id]);
    }
    /**
     * Display UI for importing and parsing CSV files.
     */
    public function importCsv(Request $request): View
    {
        // Load all active payees of user with config and pass to view as JavaScript variable.
        $payees = $request->user()
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
     * Show a listing of import jobs for the current user.
     */
    public function index()
    {
        $imports = \App\Models\ImportJob::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        // Load account entities in a map for display
        $accountIds = $imports->pluck('account_entity_id')->filter()->unique()->values()->all();
        $accounts = collect([]);
        if (count($accountIds) > 0) {
            $accounts = \App\Models\AccountEntity::whereIn('id', $accountIds)->get()->keyBy('id');
        }

        return view('import.index', [
            'imports' => $imports,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Return JSON status for an ImportJob
     */
    public function importStatus($importId)
    {
        $import = \App\Models\ImportJob::find($importId);
        if (! $import) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // Ensure the current user owns this import or is admin
        if (auth()->id() !== $import->user_id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'processed_rows' => (int) $import->processed_rows,
            'total_rows' => $import->total_rows,
            'errors' => $import->errors ?? [],
            'started_at' => $import->started_at?->toDateTimeString(),
            'finished_at' => $import->finished_at?->toDateTimeString(),
        ]);
    }

    /**
     * Download import errors as JSON file for inspection.
     */
    public function importErrors($importId)
    {
        $import = \App\Models\ImportJob::find($importId);
        if (! $import) {
            return response()->json(['error' => 'not_found'], 404);
        }
        if (auth()->id() !== $import->user_id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $errors = $import->errors ?? [];
        $filename = 'import_' . $import->id . '_errors.json';
        return response()->streamDownload(function () use ($errors) {
            echo json_encode($errors, JSON_PRETTY_PRINT);
        }, $filename, ['Content-Type' => 'application/json']);
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
        $fullPath = Storage::disk('local')->path($path);

        $rows = [];
        if (($handle = fopen($fullPath, 'r')) !== false) {
            $header = fgetcsv($handle);
            // Normalize Hungarian 'Típus' to 'Type' for compatibility
            foreach ($header as &$h) {
                if (mb_trim(mb_strtolower($h)) === 'típus') {
                    $h = 'Type';
                }
            }
            unset($h);
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = array_combine($header, $row);
            }
            fclose($handle);
        }

        Storage::disk('local')->delete($path);

        // Redirect back to import.csv with parsed rows in session
        return redirect()->route('import.csv')->with('parsedRows', $rows);
    }
}
