<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordScheduledTransaction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected TransactionService $transactionService;
    public Transaction $transaction;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(Transaction $transaction)
    {
        $this->transactionService = new TransactionService();
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     *
     */
    public function handle(): void
    {
        $this->transactionService->enterScheduleInstance($this->transaction);
    }

    public function tags(): array
    {
        return [
            'transaction',
            'record-scheduled-transaction',
        ];
    }
}
