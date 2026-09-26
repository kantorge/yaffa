<?php

namespace App\Jobs;

use App\Models\Budget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateBudgetActiveFlag implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected Budget $budget;

    /**
     * Create a new job instance.
     */
    public function __construct(Budget $budget)
    {
        $this->budget = $budget;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->budget->active = $this->budget->isActive();
        $this->budget->saveQuietly();
    }
}
