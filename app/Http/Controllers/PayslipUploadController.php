<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMoneyhubImport;
use App\Models\AccountEntity;
use App\Models\ImportJob;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class PayslipUploadController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
    }

    /**
     * Show the payslip upload form.
     */
    public function showForm()
    {
        // Get user's employment accounts (accounts in 'Employers' group)
        $accounts = AccountEntity::where('user_id', auth()->id())
            ->where('config_type', 'account')
            ->whereHasMorph('config', [\App\Models\Account::class], function ($query) {
                $query->whereHas('accountGroup', function ($q) {
                    $q->where('name', 'Employers');
                });
            })
            ->orderBy('name')
            ->get();

        return view('payslip.upload', compact('accounts'));
    }

    /**
     * Handle the payslip JSON file upload.
     */
    public function handleUpload(Request $request)
    {
        $validated = $request->validate([
            'account_entity_id' => 'required|exists:account_entities,id',
            'files.*' => 'required|file|mimes:json|max:10240', // 10MB max per file
        ]);

        $accountEntityId = $validated['account_entity_id'];

        // Verify the account belongs to the user and is an employment account
        $account = AccountEntity::where('id', $accountEntityId)
            ->where('user_id', auth()->id())
            ->where('config_type', 'account')
            ->whereHasMorph('config', [\App\Models\Account::class], function ($query) {
                $query->whereHas('accountGroup', function ($q) {
                    $q->where('name', 'Employers');
                });
            })
            ->first();

        if (!$account) {
            return back()->withErrors(['account_entity_id' => 'Invalid employment account selected.']);
        }

        $uploadedCount = 0;
        $files = $request->file('files');

        foreach ($files as $file) {
            // Store file
            $path = $file->store('imports/payslips', 'local');

            // Create ImportJob record
            $import = ImportJob::create([
                'user_id' => auth()->id(),
                'account_entity_id' => $accountEntityId,
                'file_path' => $path,
                'source' => 'payslip',
                'status' => 'queued',
                'total_rows' => 1, // Payslip is a single transaction
                'processed_rows' => 0,
            ]);

            // Dispatch job to process the file
            ProcessMoneyhubImport::dispatch($import->id);

            $uploadedCount++;
        }

        return redirect()->route('imports.index')
            ->with('success', "Uploaded {$uploadedCount} payslip(s) for processing.");
    }
}
