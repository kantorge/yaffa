<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use App\Models\ImportJob;
use App\Models\AccountEntity;

class InvestmentStatementUploadController extends Controller
{
    public function showForm()
    {
        // list user's accounts for selection - filter to WiseAlpha accounts only
        $accounts = AccountEntity::where('user_id', Auth::id())
            ->where('config_type', 'account')
            ->where('name', 'like', '%WiseAlpha%')
            ->orderBy('name')
            ->get();

        return view('investment.upload_statements', ['accounts' => $accounts]);
    }

    public function handleUpload(Request $request)
    {
        $request->validate([
            'account_entity_id' => 'required|exists:account_entities,id',
            'files' => 'required|array',
            'files.*' => 'file|mimes:csv,txt',
        ]);

        $accountId = $request->input('account_entity_id');

        foreach ($request->file('files') as $file) {
            $path = $file->store('imports');

            $import = ImportJob::create([
                'user_id' => Auth::id(),
                'account_entity_id' => $accountId,
                'file_path' => $path,
                'source' => 'wisealpha',
                'status' => 'queued',
                'total_rows' => null,
                'processed_rows' => 0,
            ]);

            // Dispatch existing job to process investment CSV
            \App\Jobs\ProcessMoneyhubImport::dispatch($import->id);
        }

        Session::flash('upload_result', ['queued' => true]);
        return Redirect::back();
    }
}
