<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TransactionItemMergeService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MergeStandardTransactionItemsJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Transaction $transaction,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(TransactionItemMergeService $mergeService): void
    {
        $mergeService->mergeTransactionItems($this->transaction);
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'transaction',
            'merge-standard-transaction-items',
        ];
    }
}
