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
     * pre-update account IDs, so a payee that was swapped out is recalculated too.
     *
     * @param  array<string, mixed>  $previousConfig
     */
    public static function dispatchForTransaction(Transaction $transaction, array $previousConfig = []): void
    {
        if (! $transaction->isStandard() || $transaction->schedule) {
            return;
        }

        $transaction->loadMissing('config');

        if (! $transaction->config instanceof TransactionDetailStandard) {
            return;
        }

        $entityIds = array_filter([
            $transaction->config->account_from_id,
            $transaction->config->account_to_id,
            $previousConfig['account_from_id'] ?? null,
            $previousConfig['account_to_id'] ?? null,
        ]);

        AccountEntity::query()
            ->payees()
            ->whereIn('id', array_unique($entityIds))
            ->pluck('id')
            ->each(fn (int $id) => self::dispatch($id));
    }
}
