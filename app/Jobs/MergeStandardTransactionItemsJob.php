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
        public readonly int $transactionId,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(TransactionItemMergeService $mergeService): void
    {
        // Re-check eligibility at execution time: the items may have already been
        // merged since the job was dispatched (e.g. a duplicate run). If the
        // transaction no longer passes the scope, there is no work to do.
        $transaction = Transaction::eligibleForItemMerge()->find($this->transactionId);

        if ($transaction === null) {
            return;
        }

        $mergeService->mergeTransactionItems($transaction);
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
