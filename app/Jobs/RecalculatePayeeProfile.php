<?php

namespace App\Jobs;

use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Services\PayeeProfileService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rebuilds the stored history profile of one payee. Unique per payee, so a burst of transactions for the
 * same payee queues one recalculation.
 */
class RecalculatePayeeProfile implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function __construct(public readonly int $payeeId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->payeeId;
    }

    public function handle(PayeeProfileService $service): void
    {
        $payee = AccountEntity::query()->payees()->find($this->payeeId);

        if ($payee !== null) {
            $service->calculate($payee);
        }
    }

    /**
     * Queue a recalculation for each payee a standard transaction touches. `$previousConfig` carries the
     * pre-update account IDs, so a payee that was swapped out, or whose transaction stopped being a
     * recorded standard one, is recalculated too.
     *
     * @param  array<string, mixed>  $previousConfig
     */
    public static function dispatchForTransaction(Transaction $transaction, array $previousConfig = []): void
    {
        $entityIds = [$previousConfig['account_from_id'] ?? null, $previousConfig['account_to_id'] ?? null];

        if ($transaction->isStandard() && ! $transaction->schedule) {
            $transaction->loadMissing('config');

            if ($transaction->config instanceof TransactionDetailStandard) {
                $entityIds[] = $transaction->config->account_from_id;
                $entityIds[] = $transaction->config->account_to_id;
            }
        }

        AccountEntity::query()
            ->payees()
            ->whereIn('id', array_unique(array_filter($entityIds)))
            ->pluck('id')
            ->each(fn (int $id) => self::dispatch($id));
    }
}
