<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneDuplicateQueueJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:prune-duplicates 
                            {--queue=default : The queue to prune}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate CalculateAccountMonthlySummary jobs from the queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queueName = $this->option('queue');
        $dryRun = $this->option('dry-run');
        
        $this->info("Analyzing queue: {$queueName}");
        
        // Get all jobs from the database queue
        $jobs = \DB::table('jobs')
            ->where('queue', $queueName)
            ->orderBy('id')
            ->get();
            
        if ($jobs->isEmpty()) {
            $this->info('No jobs found in the queue.');
            return 0;
        }
        
        $this->info("Total jobs in queue: {$jobs->count()}");
        
        // Parse jobs and find duplicates
        $jobSignatures = [];
        $duplicateIds = [];
        $jobStats = [];
        
        foreach ($jobs as $job) {
            $payload = json_decode($job->payload, true);
            $jobClass = $payload['displayName'] ?? 'Unknown';
            
            // Track job statistics
            if (!isset($jobStats[$jobClass])) {
                $jobStats[$jobClass] = 0;
            }
            $jobStats[$jobClass]++;
            
            // Focus on CalculateAccountMonthlySummary jobs
            if ($jobClass === 'App\\Jobs\\CalculateAccountMonthlySummary') {
                // Decode the job data
                $data = unserialize($payload['data']['command']);
                
                // Use reflection to access private properties
                $reflection = new \ReflectionClass($data);
                
                $userProp = $reflection->getProperty('user');
                $userProp->setAccessible(true);
                $user = $userProp->getValue($data);
                
                $accountProp = $reflection->getProperty('accountEntity');
                $accountProp->setAccessible(true);
                $account = $accountProp->getValue($data);
                
                $taskProp = $reflection->getProperty('task');
                $taskProp->setAccessible(true);
                $task = $taskProp->getValue($data);
                
                $dateFromProp = $reflection->getProperty('dateFrom');
                $dateFromProp->setAccessible(true);
                $dateFrom = $dateFromProp->getValue($data);
                
                $dateToProp = $reflection->getProperty('dateTo');
                $dateToProp->setAccessible(true);
                $dateTo = $dateToProp->getValue($data);
                
                // Create a unique signature based on job parameters
                $userId = $user->id ?? null;
                $accountId = $account?->id ?? null;
                $dateFromStr = $dateFrom ? $dateFrom->format('Y-m-d') : null;
                $dateToStr = $dateTo ? $dateTo->format('Y-m-d') : null;
                
                $signature = sprintf(
                    'user:%s|account:%s|task:%s|from:%s|to:%s',
                    $userId,
                    $accountId,
                    $task,
                    $dateFromStr,
                    $dateToStr
                );
                
                if (isset($jobSignatures[$signature])) {
                    // This is a duplicate
                    $duplicateIds[] = $job->id;
                } else {
                    // First occurrence, store it
                    $jobSignatures[$signature] = $job->id;
                }
            }
        }
        
        // Display statistics
        $this->newLine();
        $this->info('Job types in queue:');
        foreach ($jobStats as $class => $count) {
            $this->line("  - {$class}: {$count}");
        }
        
        $this->newLine();
        $this->info("Unique CalculateAccountMonthlySummary jobs: " . count($jobSignatures));
        $this->info("Duplicate CalculateAccountMonthlySummary jobs: " . count($duplicateIds));
        
        if (count($duplicateIds) === 0) {
            $this->info('No duplicates found!');
            return 0;
        }
        
        // Delete duplicates
        if ($dryRun) {
            $this->warn("DRY RUN: Would delete " . count($duplicateIds) . " duplicate jobs");
            $this->line("Job IDs: " . implode(', ', $duplicateIds));
        } else {
            if ($this->confirm("Delete " . count($duplicateIds) . " duplicate jobs?", true)) {
                $deleted = \DB::table('jobs')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
                    
                $this->info("Deleted {$deleted} duplicate jobs");
            } else {
                $this->info('Cancelled.');
            }
        }
        
        return 0;
    }
}
