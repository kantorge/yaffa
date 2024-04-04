<?php

namespace App\Http\Traits;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

trait ScheduleTrait
{
    /**
     * Get scheduled instances for a collection of transactions.
     *
     * @param Collection $transactions Collection of scheduled transactions to process.
     * @param string $startType Indicates if instances are needed for the entire period from the start date, or only from the next planned instance. Accepted values: 'start', 'next', 'custom'.
     * @param Carbon|null $customStart Custom start date for calculation, if start type is 'custom'.
     * @param Carbon|null $maxLookAhead Latest date for calculation, if no end date is present in schedule rules.
     * @param int|null $virtualLimit
     * @return Collection
     */
    public function getScheduleInstances(
        Collection $transactions,
        string $startType,
        ?Carbon $customStart = null,
        ?Carbon $maxLookAhead = null,
        ?int $virtualLimit = 500
    ): Collection {
        // Validate start type
        if (! in_array($startType, ['start', 'next', 'custom'])) {
            throw new InvalidArgumentException('Invalid start type');
        }

        if ($startType === 'custom' && ! $customStart) {
            throw new InvalidArgumentException('Custom start date is required for custom start type');
        }

        $scheduleInstances = new Collection();

        $transactions->each(
            function ($transaction) use (&$scheduleInstances, $startType, $customStart, $maxLookAhead, $virtualLimit) {
                if ($startType === 'start') {
                    $constraintStart = $transaction->transactionSchedule->start_date;
                } elseif ($startType === 'next') {
                    $constraintStart = $transaction->transactionSchedule->next_date;
                } else {
                    // Custom start type
                    $constraintStart = $customStart;
                }

                $scheduleInstances = $scheduleInstances->concat(
                    $transaction->scheduleInstances($constraintStart, $maxLookAhead, $virtualLimit)
                );
            }
        );

        return $scheduleInstances;
    }
}
