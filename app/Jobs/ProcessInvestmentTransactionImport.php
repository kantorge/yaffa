<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\InvestmentTransactionUploader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInvestmentTransactionImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $importJobId;
    public string $source;
    public array $config;

    public int $timeout = 1800; // 30 minutes for large CSV files

    public function __construct(int $importJobId, string $source, array $config = [])
    {
        $this->importJobId = $importJobId;
        $this->source = $source;
        $this->config = $config;
    }

    public function handle()
    {
        $import = ImportJob::with('user')->find($this->importJobId);
        if (! $import) {
            return;
        }

        $import->update(['status' => 'started', 'started_at' => now()]);

        $filePath = storage_path('app/' . $import->file_path);

        try {
            $user = $import->user;
            if (! $user) {
                throw new \Exception('User not found for import job');
            }

            // Create uploader instance with config
            $uploader = new InvestmentTransactionUploader($user, $this->config);
            
            // Process the file
            $results = $uploader->processFile($filePath, $this->source);
            
            // Update import status with results
            $import->update([
                'status' => 'finished',
                'finished_at' => now(),
                'processed_rows' => $results['processed'],
                'total_rows' => $results['total'] ?? $results['processed'],
                'errors' => $results['errors'] ?? [],
            ]);

            Log::info("Investment import completed", [
                'import_id' => $import->id,
                'source' => $this->source,
                'processed' => $results['processed'],
                'errors' => count($results['errors'] ?? []),
            ]);

        } catch (Throwable $e) {
            Log::error('Investment import job failed: ' . $e->getMessage(), [
                'import_id' => $import->id,
                'source' => $this->source,
                'exception' => $e,
            ]);
            
            $import->update([
                'status' => 'failed',
                'finished_at' => now(),
                'errors' => [$e->getMessage()],
            ]);
            
            throw $e;
        }
    }
}
