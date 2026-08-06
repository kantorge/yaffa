<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Carbon;
use Throwable;

class BudgetService
{
    public function __construct(private readonly RecurrenceRuleService $recurrenceRuleService)
    {
    }

    public function store(User $user, array $data): Budget
    {
        return $user->budgets()->create($data);
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->fill($data);
        $budget->save();

        return $budget;
    }

    public function delete(Budget $budget): array
    {
        $success = false;
        $error = null;

        try {
            $success = (bool) $budget->delete();

            if (! $success) {
                $error = __('Budget could not be deleted');
            }
        } catch (Throwable $e) {
            report($e);
            $error = __('Database error:') . ' ' . $e->getMessage();
        }

        return [
            'success' => $success,
            'error' => $error,
        ];
    }

    /**
     * Project the calendar dates on which this budget's period recurs within [$from, $to]
     * (inclusive of both ends) - $from is often the budget's own start_date, which must itself
     * be included as the first occurrence.
     *
     * @return Carbon[]
     */
    public function projectOccurrences(Budget $budget, Carbon $from, Carbon $to): array
    {
        $recurrence = $this->recurrenceRuleService->getRecurrence(
            $budget->start_date,
            $budget->frequency,
            $budget->interval ?? 1,
            $budget->end_date,
            $budget->count,
            $from,
            afterDateInclusive: true,
        );

        return collect($recurrence)
            ->map(fn ($occurrence) => Carbon::instance($occurrence->getStart()))
            ->filter(fn (Carbon $date) => $date->lte($to))
            ->values()
            ->all();
    }
}
