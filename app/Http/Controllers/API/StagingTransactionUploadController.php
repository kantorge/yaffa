<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\StagingTransactionParser;

class StagingTransactionUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'source' => 'required|string',
        ]);

        $file = $request->file('file');
        $source = $request->input('source');
        $ext = strtolower($file->getClientOriginalExtension());
        $fileType = in_array($ext, ['json', 'csv', 'xlsx']) ? $ext : null;
        if (!$fileType) {
            return response()->json(['success' => false, 'message' => 'Unsupported file type.'], 422);
        }

        $path = $file->storeAs('staging_uploads', uniqid('staging_', true) . '.' . $ext);
        $fullPath = storage_path('app/' . $path);

        try {
            $stagingRows = StagingTransactionParser::parse($fullPath, $fileType, $source);
            // Save as YAML or JSON for review (here using JSON for simplicity)
            $stagingFile = 'staging/' . uniqid('transactions_', true) . '.json';
            Storage::disk('local')->put($stagingFile, json_encode($stagingRows, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'File parsed and staged for review.',
            'staging_file' => $stagingFile,
            'count' => count($stagingRows),
        ]);
    }
}
