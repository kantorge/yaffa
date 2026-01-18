<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMoneyhubUpload;
use App\Models\ImportJob;
use Illuminate\Console\Command;

class RequeueImport extends Command
{
    protected $signature = 'yaffa:requeue-import {import_id : The import job ID to requeue}';

    protected $description = 'Requeue a failed import job';

    public function handle()
    {
        $importId = $this->argument('import_id');
        $import = ImportJob::find($importId);

        if (!$import) {
            $this->error("Import job {$importId} not found");
            return 1;
        }

        if ($import->status === 'finished') {
            $this->error("Import job {$importId} already finished successfully");
            return 1;
        }

        // Reset the import job
        $import->update([
            'status' => 'queued',
            'started_at' => null,
            'finished_at' => null,
            'errors' => null,
            'processed_rows' => 0,
        ]);

        // Dispatch the job
        ProcessMoneyhubUpload::dispatch($importId, $import->account_id);

        $this->info("Import job {$importId} has been requeued");
        $this->info("File: {$import->file_path}");
        $this->info("Queue workers will process it shortly");

        return 0;
    }
}
