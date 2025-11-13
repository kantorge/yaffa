<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

class InvestmentUploadWiseAlphaController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:account_entities,id',
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $account = AccountEntity::findOrFail($request->account_id);
        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('trim', array_shift($rows));
        $created = 0;
        $errors = [];
        $userId = $account->user_id;

        foreach ($rows as $row) {
            $data = array_combine($header, $row);
            try {
                \App\Models\ImportedInvestmentRow::create([
                    'user_id' => $userId,
                    'account_id' => $account->id,
                    'reference' => substr($data['Reference'] ?? '', 0, 64),
                    'date' => $data['Date'] ?? null,
                    'transaction_type' => $data['Transaction Type'] ?? null,
                    'description' => $data['Description'] ?? null,
                    'amount' => $data['Amount'] ?? null,
                    'balance' => $data['Balance'] ?? null,
                    'raw_data' => json_encode($data),
                ]);
                $created++;
            } catch (\Exception $e) {
                $errors[] = ($data['Reference'] ?? '').': '.$e->getMessage();
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => $created.' rows imported for review.' . (count($errors) ? ' Errors: '.implode('; ', $errors) : ''),
        ]);
    }
}
