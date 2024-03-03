<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\TransactionSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateTransactionScheduleActiveFlag implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected Transaction $transaction;

    /**
     * Create a new job instance.
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = $this->transaction->transactionSchedule;
        $schedule->active = $schedule->isActive();
        $schedule->saveQuietly();
    }
}
