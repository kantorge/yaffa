<?php

namespace App\Http\Traits;

use Carbon\Carbon;
use Illuminate\Support\Collection;

trait ScheduleTrait
{
    /**
     * Get scheduled instances for a collection of transactions.
     *
     * @param  Illuminate\Support\Collection $transactions Collection of scheduled transactions to process.
     * @param  string $startType Indicate if instances are needed for entire period or only from next planned instance.
     * @param  Carbon\Carbon $maxLookAhead Latest date for calculation, if no end date is present in schedule rules.
     * @param  int $virtualLimit
     * @return Illuminate\Support\Collection
     */
    public function getScheduleInstances(Collection $transactions, string $startType, ?Carbon $maxLookAhead = null, ?int $virtualLimit = 500): Collection
    {
        $scheduleInstances = new Collection();

        if (!in_array($startType, ['start', 'next'])) {
            return $scheduleInstances;
        }

        $transactions->each(function ($transaction) use (&$scheduleInstances, $startType, $maxLookAhead, $virtualLimit) {
            if ($startType == 'start') {
                $constraintStart = $transaction->transactionSchedule->start_date;
            } elseif ($startType == 'next') {
                $constraintStart = $transaction->transactionSchedule->next_date;
            }

            $scheduleInstances = $scheduleInstances->merge($transaction->scheduleInstances($constraintStart, $maxLookAhead, $virtualLimit));
        });

        return $scheduleInstances;
    }
}