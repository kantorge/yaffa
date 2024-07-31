<?php

namespace App\Console\Commands;

use App\Models\GocardlessRequisition;
use App\Services\GocardlessService;
use Illuminate\Console\Command;

class ClearGocardlessRequisitions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-gocardless-requisitions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command is mainly used in local development to clear all Gocardless requisitions without a proper database reference.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Get all GoCardless requisitions from the API
        $gocardlessService = new GocardlessService();
        $requisitions = $gocardlessService->getAllRequisitions();

        // Get all GoCardless requisitions from the database
        $requisitionsFromDatabase = GocardlessRequisition::all();

        // Loop through all requisitions and delete the ones via API that do not have a proper database reference
        // A correct reference means, that the id of the requisition is matches the requisition_id of a record, and the reference matches the id of the same record
        foreach ($requisitions as $requisition) {
            $requisitionFromDatabase = $requisitionsFromDatabase->where('gocardless_id', $requisition['id'])->first();
            if (!$requisitionFromDatabase || (string)$requisitionFromDatabase->id !== $requisition['reference']) {
                echo "Deleting requisition with ID: {$requisition['id']}\n";

                // As we don't expect too many requisitions, we can delete them one by one
                // If this is not the case, we need to introduce a Job to delete them in the background
                $gocardlessService->deleteRequisition($requisition['id']);
            }
        }
    }
}
