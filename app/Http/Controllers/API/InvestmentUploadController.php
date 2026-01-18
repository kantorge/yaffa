<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\InvestmentTransactionUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;
use InvalidArgumentException;

class InvestmentUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        // Decode config from JSON string if it's present
        $configData = $request->input('config');
        if (is_string($configData)) {
            $configData = json_decode($configData, true);
        }

        $validator = Validator::make(array_merge($request->all(), ['config' => $configData]), [
            'source' => 'required|string|in:WiseAlpha,Trading212,MoneyHub,CompanyJSON',
            'file' => 'required|file|mimes:csv,xlsx,json,yaml,yml|max:10240', // 10MB max
            'default_account_id' => 'required|integer|exists:account_entities,id',
            'config' => 'nullable|array', // Optional custom configuration
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Store the uploaded file for background processing
            $file = $request->file('file');
            $path = $file->storeAs(
                'investment-uploads',
                $request->user()->id . '_' . time() . '_' . $file->getClientOriginalName(),
                'local'
            );

            // Get custom config or use defaults
            $config = $configData ?? [];
            $config['default_account_id'] = $request->input('default_account_id');

            // Create import job record
            $import = \App\Models\ImportJob::create([
                'user_id' => $request->user()->id,
                'account_entity_id' => $request->input('default_account_id'),
                'file_path' => $path,
                'source' => $request->input('source'),
                'status' => 'queued',
                'processed_rows' => 0,
            ]);

            // Dispatch background job to process the file
            \App\Jobs\ProcessInvestmentTransactionImport::dispatch($import->id, $request->input('source'), $config);

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'Upload received. Processing in background...',
                'import_id' => $import->id
            ]);

        } catch (Exception $e) {
            // Clean up the temporary file if it exists
            if (isset($path)) {
                Storage::disk('local')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getMapping(Request $request): JsonResponse
    {
        $source = $request->query('source');

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Source parameter is required'
            ], 400);
        }

        // Create a dummy uploader to get default mapping
        $uploader = new InvestmentTransactionUploader($request->user(), []);
        $mapping = $this->getDefaultMapping($source);

        return response()->json([
            'success' => true,
            'mapping' => $mapping
        ]);
    }

    public function validateFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source' => 'required|string|in:WiseAlpha,Trading212,MoneyHub,CompanyJSON',
            'file' => 'required|file|mimes:csv,xlsx,json,yaml,yml|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Store file temporarily and preview first few rows
            $file = $request->file('file');
            $path = $file->storeAs(
                'investment-uploads',
                'preview_' . $request->user()->id . '_' . time() . '_' . $file->getClientOriginalName(),
                'local'
            );

            $fullPath = Storage::disk('local')->path($path);
            $preview = $this->getFilePreview($fullPath, $request->input('source'));

            // Clean up
            Storage::disk('local')->delete($path);

            return response()->json([
                'success' => true,
                'preview' => $preview
            ]);

        } catch (Exception $e) {
            if (isset($path)) {
                Storage::disk('local')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'File validation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source' => 'required|string|in:WiseAlpha,Trading212,MoneyHub,CompanyJSON',
            'file' => 'required|file|mimes:csv,xlsx,json,yaml,yml|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $path = $file->storeAs(
                'investment-uploads',
                'preview_' . $request->user()->id . '_' . time() . '_' . $file->getClientOriginalName(),
                'local'
            );
            $fullPath = Storage::disk('local')->path($path);

            $uploader = new InvestmentTransactionUploader($request->user(), []);
            $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
            $data = match($extension) {
                'csv' => $uploader->parseCsv($fullPath),
                'xlsx' => $uploader->parseExcel($fullPath),
                'json' => $uploader->parseJson($fullPath),
                'yaml', 'yml' => $uploader->parseYaml($fullPath),
                default => throw new InvalidArgumentException("Unsupported file type: {$extension}")
            };

            $mapping = $uploader->getDefaultMapping($request->input('source'));
            $preview = [];
            foreach (array_slice($data, 0, 5) as $rowIndex => $rawRow) {
                $mapped = $uploader->mapTransaction($rawRow, $mapping, $rowIndex);
                $preview[] = [
                    'raw' => $rawRow,
                    'mapped' => $mapped,
                ];
            }
            Storage::disk('local')->delete($path);
            return response()->json([
                'success' => true,
                'preview' => $preview
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getFilePreview(string $filePath, string $source): array
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $preview = ['headers' => [], 'rows' => []];

        switch ($extension) {
            case 'csv':
                if (($handle = fopen($filePath, "r")) !== false) {
                    $preview['headers'] = fgetcsv($handle);
                    for ($i = 0; $i < 5 && ($row = fgetcsv($handle)) !== false; $i++) {
                        $preview['rows'][] = array_combine($preview['headers'], $row);
                    }
                    fclose($handle);
                }
                break;

            case 'json':
                $data = json_decode(file_get_contents($filePath), true);
                if (isset($data['transactions']) && is_array($data['transactions'])) {
                    $sample = array_slice($data['transactions'], 0, 5);
                    if (!empty($sample)) {
                        $preview['headers'] = array_keys($sample[0]);
                        $preview['rows'] = $sample;
                    }
                }
                break;

            case 'yaml':
            case 'yml':
                $data = \Symfony\Component\Yaml\Yaml::parseFile($filePath);
                if (isset($data['transactions']) && is_array($data['transactions'])) {
                    $sample = array_slice($data['transactions'], 0, 5);
                    if (!empty($sample)) {
                        $preview['headers'] = array_keys($sample[0]);
                        $preview['rows'] = $sample;
                    }
                }
                break;
        }

        return $preview;
    }

    private function getDefaultMapping(string $source): array
    {
        return match($source) {
            'WiseAlpha' => [
                'date' => ['target' => 'date', 'transform' => 'date'],
                'type' => ['target' => '_transaction_type_name'],
                'bond_name' => ['target' => '_symbol'],
                'account' => ['target' => '_account_name'],
                'quantity' => ['target' => 'config.quantity', 'transform' => 'float'],
                'price' => ['target' => 'config.price', 'transform' => 'divide_by_100'],
                'commission' => ['target' => 'config.commission', 'transform' => 'float'],
                'description' => ['target' => 'comment'],
            ],
            'Trading212' => [
                'Action' => ['target' => '_transaction_type_name'],
                'Time' => ['target' => 'date', 'transform' => 'date'],
                'ISIN' => ['target' => '_isin'],
                'Ticker' => ['target' => '_ticker'],
                'Name' => ['target' => '_investment_name'],
                'No. of shares' => ['target' => 'config.quantity', 'transform' => 'float'],
                'Price / share' => ['target' => 'config.price', 'transform' => 'divide_by_100'],
                'Currency (Price / share)' => ['target' => '_currency'],
                'Total' => ['target' => '_total', 'transform' => 'float'],
            ],
            'MoneyHub' => [
                'Date' => ['target' => 'date', 'transform' => 'date'],
                'Type' => ['target' => '_transaction_type_name'],
                'ISIN' => ['target' => '_symbol'],
                'Security Name' => ['target' => 'comment'],
                'Quantity' => ['target' => 'config.quantity', 'transform' => 'float'],
                'Price' => ['target' => 'config.price', 'transform' => 'float'],
                'Currency' => ['target' => '_currency'],
                'Amount' => ['target' => '_total', 'transform' => 'float'],
                'Fee' => ['target' => 'config.commission', 'transform' => 'float'],
                'Account' => ['target' => '_account_name'],
            ],
            default => []
        };
    }
}
