<?php

namespace App\Http\Controllers;

use App\Services\InvestmentCsvUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;

class InvestmentCsvUploadController extends Controller
{
    public function showForm()
    {
        // Serve a minimal, no-layout page to avoid heavy layout DB queries that can time out
        return view('investment.upload_csv_plain');
    }

    public function handleUpload(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');

        // Store uploaded file and create an ImportJob record, then dispatch a queued job
        $path = $file->store('imports');

        $import = \App\Models\ImportJob::create([
            'user_id' => Auth::id(),
            'file_path' => $path,
            'status' => 'queued',
            'total_rows' => null,
            'processed_rows' => 0,
        ]);

        // Dispatch a job to process this file asynchronously
        \App\Jobs\ProcessMoneyhubImport::dispatch($import->id);

        Session::flash('upload_result', ['queued' => true, 'import_id' => $import->id]);
        return Redirect::back();
    }
}
