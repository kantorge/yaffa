<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\InvestmentCsvUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMoneyhubImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $importJobId;

    public int $timeout = 1200;

    public function __construct(int $importJobId)
    {
        $this->importJobId = $importJobId;
    }

    public function handle()
    {
        $import = ImportJob::find($this->importJobId);
        if (! $import) {
            return;
        }

        $import->update(['status' => 'started', 'started_at' => now()]);

        $filePath = storage_path('app/' . $import->file_path);

        try {
            if (($import->source ?? null) === 'payslip') {
                // Handle payslip import
                $payslipService = new \App\Services\PayslipParserService();
                $result = $payslipService->processPayslipFile($filePath, $import->user_id, $import->account_entity_id, $import);
            } elseif (($import->source ?? null) === 'wisealpha') {
                // Handle WiseAlpha investment import
                $apiKey = config('services.companies_house.api_key');
                $service = new InvestmentCsvUploadService($apiKey);
                $result = $service->processWiseAlphaStoredFile($filePath, $import->user_id, $import);
            } else {
                // Handle generic investment CSV import
                $apiKey = config('services.companies_house.api_key');
                $service = new InvestmentCsvUploadService($apiKey);
                $result = $service->processFromStoredFile($filePath, $import->user_id, $import);
            }
            $import->update(['status' => 'finished', 'finished_at' => now(), 'errors' => $result['errors'], 'processed_rows' => $result['processed']]);
        } catch (Throwable $e) {
            Log::error('Import job failed: ' . $e->getMessage());
            $import->update(['status' => 'failed', 'errors' => [$e->getMessage()]]);
            throw $e;
        }
    }
}
