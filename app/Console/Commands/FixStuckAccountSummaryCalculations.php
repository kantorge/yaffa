<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixStuckAccountSummaryCalculations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yaffa:fix-stuck-calculations 
        {--cleanup : Mark unfinished job batches as failed}
        {--restart : Restart calculations for a specific user}
        {--user= : User ID to restart calculations for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix stuck account summary calculation job batches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanupStuckJobs();
        }

        if ($this->option('restart')) {
            $userId = $this->option('user');
            if (!$userId) {
                $this->error('User ID is required when using --restart');
                return 1;
            }
            return $this->restartCalculationsForUser($userId);
        }

        // Default: show status
        return $this->showJobBatchStatus();
    }

    private function showJobBatchStatus(): int
    {
        // Find old stuck batches
        $stuckBatches = DB::table('job_batches')
            ->where('finished_at', null)
            ->where('created_at', '<', Carbon::now()->subMinutes(30))
            ->get();
            
        // Find empty batches that should be finished
        $emptyBatches = DB::table('job_batches')
            ->where('finished_at', null)
            ->where('total_jobs', 0)
            ->get();

        $totalProblematic = $stuckBatches->count() + $emptyBatches->count();

        if ($totalProblematic === 0) {
            $this->info('No stuck job batches found.');
            return 0;
        }

        if ($stuckBatches->count() > 0) {
            $this->warn('Found ' . $stuckBatches->count() . ' old stuck job batches:');
            
            foreach ($stuckBatches as $batch) {
                $this->line("ID: {$batch->id}");
                $this->line("Name: {$batch->name}");
                $this->line("Created: {$batch->created_at}");
                $this->line("Progress: {$batch->finished_jobs}/{$batch->total_jobs}");
                $this->line('---');
            }
        }
        
        if ($emptyBatches->count() > 0) {
            $this->warn('Found ' . $emptyBatches->count() . ' empty unfinished job batches:');
            
            foreach ($emptyBatches as $batch) {
                $this->line("ID: {$batch->id}");
                $this->line("Name: {$batch->name}");
                $this->line("Created: {$batch->created_at}");
                $this->line("Total jobs: {$batch->total_jobs} (empty batch)");
                $this->line('---');
            }
        }

        $this->info('Use --cleanup to mark these as failed, or --restart --user=ID to restart for a user');
        return 0;
    }

    private function cleanupStuckJobs(): int
    {
        // Find old stuck batches
        $stuckBatches = DB::table('job_batches')
            ->where('finished_at', null)
            ->where('created_at', '<', Carbon::now()->subMinutes(30))
            ->get();
            
        // Find empty batches that should be finished
        $emptyBatches = DB::table('job_batches')
            ->where('finished_at', null)
            ->where('total_jobs', 0)
            ->get();

        $totalCleaned = 0;
        
        // Mark old stuck batches as cancelled
        if ($stuckBatches->count() > 0) {
            $stuckBatchIds = $stuckBatches->pluck('id');
            
            DB::table('job_batches')
                ->whereIn('id', $stuckBatchIds)
                ->update([
                    'cancelled_at' => now()->timestamp,
                    'finished_at' => now()->timestamp,
                ]);
                
            $this->info('Marked ' . $stuckBatches->count() . ' old stuck batches as cancelled.');
            $totalCleaned += $stuckBatches->count();
        }
        
        // Mark empty batches as finished
        if ($emptyBatches->count() > 0) {
            $emptyBatchIds = $emptyBatches->pluck('id');
            
            DB::table('job_batches')
                ->whereIn('id', $emptyBatchIds)
                ->update([
                    'finished_at' => now()->timestamp,
                ]);
                
            $this->info('Marked ' . $emptyBatches->count() . ' empty batches as finished.');
            $totalCleaned += $emptyBatches->count();
        }

        if ($totalCleaned > 0) {
            $this->info("Total batches cleaned up: {$totalCleaned}");
        } else {
            $this->info('No batches needed cleanup.');
        }

        return 0;
    }

    private function restartCalculationsForUser(int $userId): int
    {
        // First clean up any existing batches for this user
        DB::table('job_batches')
            ->where('name', 'like', "%-{$userId}")
            ->where('finished_at', null)
            ->update([
                'finished_at' => Carbon::now(),
                'failed_at' => Carbon::now(),
            ]);

        $this->info("Cleaned up existing batches for user {$userId}");

        // Restart calculations using the existing command
        $this->call('app:cache:account-monthly-summaries', [
            'userId' => $userId
        ]);

        $this->info("Restarted account summary calculations for user {$userId}");
        return 0;
    }
}